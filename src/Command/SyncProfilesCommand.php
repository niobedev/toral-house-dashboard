<?php

namespace App\Command;

use App\Repository\EventRepository;
use App\Service\SecondLifeProfileService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync-profiles',
    description: 'Refresh Second Life profile data (bio + picture) for all known avatars',
)]
class SyncProfilesCommand extends Command
{
    public function __construct(
        private readonly SecondLifeProfileService $profileService,
        private readonly EventRepository $eventRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'delay',
            'd',
            InputOption::VALUE_REQUIRED,
            'Delay between requests in milliseconds (increase if you hit rate limits)',
            1500,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io      = new SymfonyStyle($input, $output);
        $delayMs = max(0, (int) $input->getOption('delay'));

        $keys  = $this->eventRepository->findDistinctAvatarKeys();
        $total = count($keys);

        if ($total === 0) {
            $io->warning('No avatars found in the database.');
            return Command::SUCCESS;
        }

        $io->title('Syncing Second Life profiles');
        $io->text(sprintf(
            'Found <info>%d</info> avatars  •  delay between requests: <info>%d ms</info>',
            $total,
            $delayMs,
        ));
        $io->newLine();

        ProgressBar::setFormatDefinition('profile_sync', ' %current%/%max% [%bar%] %percent:3s%%  <comment>%message%</comment>');

        $progressBar = new ProgressBar($output, $total);
        $progressBar->setFormat('profile_sync');
        $progressBar->setMessage('starting…');
        $progressBar->start();

        $updated = $failed = 0;

        foreach ($keys as $i => $key) {
            $progressBar->setMessage($key);

            try {
                $result = $this->profileService->fetchProfile($key, forceRefresh: true);
                $result !== null ? $updated++ : $failed++;
            } catch (\Throwable $e) {
                $failed++;
            }

            $progressBar->advance();

            // Throttle — skip the sleep after the very last request
            if ($delayMs > 0 && $i < $total - 1) {
                usleep($delayMs * 1_000);
            }
        }

        $progressBar->setMessage('done');
        $progressBar->finish();
        $io->newLine(2);

        if ($failed === 0) {
            $io->success(sprintf('All %d profiles updated successfully.', $updated));
        } else {
            $io->warning(sprintf('Updated: %d  •  Failed / unavailable: %d', $updated, $failed));
        }

        return Command::SUCCESS;
    }
}
