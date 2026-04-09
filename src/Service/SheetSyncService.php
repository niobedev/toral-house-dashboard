<?php

namespace App\Service;

use App\Repository\SyncStateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class SheetSyncService
{
    private const BATCH_SIZE = 500;

    public function __construct(
        private readonly GoogleSheetsService $sheetsService,
        private readonly SyncStateRepository $syncStateRepository,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'GOOGLE_SHEET_RANGE')]
        private readonly string $sheetRange,
        #[Autowire(env: 'SHEET_TIMEZONE')]
        private readonly string $sheetTimezone,
    ) {
    }

    /**
     * @return array{imported: int, skipped: int, lastRow: int}
     */
    public function sync(bool $full = false): array
    {
        ini_set('memory_limit', '512M');

        $state = $this->syncStateRepository->getOrCreate();

        if ($full) {
            $this->logger->warning('Full re-sync requested — wiping all existing events.');
            $this->em->getConnection()->executeStatement('DELETE FROM event');
            $state->setLastRow(1);
        }

        $fromRow = $state->getLastRow() + 1;
        $this->logger->info('Fetching rows from row {row} using range: {range}', [
            'row' => $fromRow,
            'range' => $this->sheetRange,
        ]);

        $rows = $this->sheetsService->fetchRows($fromRow, $this->sheetRange);

        if (empty($rows)) {
            $this->logger->info('No new rows to sync.');
            return ['imported' => 0, 'skipped' => 0, 'lastRow' => $state->getLastRow()];
        }

        $this->logger->info('Found {count} new row(s).', ['count' => count($rows)]);

        $imported = 0;
        $skipped = 0;
        $conn = $this->em->getConnection();

        foreach (array_chunk($rows, self::BATCH_SIZE) as $chunk) {
            $batchValues = [];
            $batchParams = [];

            foreach ($chunk as $row) {
                if (count($row) < 5) {
                    $skipped++;
                    continue;
                }

                [$dateStr, $action, $avatarKey, $displayName, $username] = $row;

                $action = strtolower(trim($action));
                if (!in_array($action, ['join', 'quit'], true)) {
                    $skipped++;
                    continue;
                }

                try {
                    $eventTs = (new \DateTimeImmutable($dateStr, new \DateTimeZone($this->sheetTimezone)))
                        ->setTimezone(new \DateTimeZone('UTC'));
                } catch (\Exception) {
                    $skipped++;
                    continue;
                }

                $batchValues[] = '(?, ?, ?, ?, ?)';
                array_push($batchParams,
                    $eventTs->format('Y-m-d H:i:s'),
                    $action,
                    trim($avatarKey),
                    trim($displayName),
                    trim($username),
                );
                $imported++;
            }

            if ($batchValues) {
                $conn->executeStatement(
                    'INSERT INTO event (event_ts, action, avatar_key, display_name, username) VALUES ' . implode(',', $batchValues),
                    $batchParams,
                );
            }
        }

        $state->setLastRow($fromRow + count($rows) - 1);
        $state->setSyncedAt(new \DateTimeImmutable());
        $state->setRowsSynced($state->getRowsSynced() + $imported);
        $this->em->persist($state);
        $this->em->flush();

        $this->logger->info('Synced {imported} rows ({skipped} skipped). Last row is now {lastRow}.', [
            'imported' => $imported,
            'skipped' => $skipped,
            'lastRow' => $state->getLastRow(),
        ]);

        return ['imported' => $imported, 'skipped' => $skipped, 'lastRow' => $state->getLastRow()];
    }
}
