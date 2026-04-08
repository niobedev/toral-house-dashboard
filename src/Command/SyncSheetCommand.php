<?php

namespace App\Command;

use App\Service\SheetSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:sync-sheet', description: 'Sync new rows from Google Sheet into the database')]
class SyncSheetCommand extends Command
{
    public function __construct(
        private readonly SheetSyncService $syncService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('full', null, InputOption::VALUE_NONE, 'Perform a full re-sync from row 2 (wipes existing data)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $result = $this->syncService->sync(full: $input->getOption('full'));

        if ($result['imported'] === 0 && $result['skipped'] === 0) {
            $io->success('No new rows to sync.');
        } else {
            $io->success(sprintf(
                'Synced %d rows (%d skipped). Last row is now %d.',
                $result['imported'],
                $result['skipped'],
                $result['lastRow'],
            ));
        }

        return Command::SUCCESS;
    }
}
