<?php

namespace App\Controller;

use App\Repository\AvatarReminderRepository;
use App\Repository\EventRepository;
use App\Repository\SyncStateRepository;
use App\Service\GoogleSheetsService;
use App\Service\SecondLifeProfileService;
use App\Command\SyncSheetCommand;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;

#[Route('/api', name: 'api_')]
class ApiController extends AbstractController
{
    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly SyncStateRepository $syncStateRepository,
        private readonly SyncSheetCommand $syncSheetCommand,
        private readonly AvatarReminderRepository $reminderRepository,
        private readonly SecondLifeProfileService $profileService,
    ) {}

    #[Route('/live-visitors', name: 'live_visitors', methods: ['GET'])]
    public function liveVisitors(): JsonResponse
    {
        return $this->json($this->eventRepository->getLiveVisitors());
    }

    #[Route('/recent-visitors', name: 'recent_visitors', methods: ['GET'])]
    public function recentVisitors(#[MapQueryParameter] string $period = 'today'): JsonResponse
    {
        $allowed = ['today', 'yesterday', 'week', 'month', 'year', 'all'];
        if (!in_array($period, $allowed, true)) {
            $period = 'today';
        }
        return $this->json($this->eventRepository->getRecentVisitors($period));
    }

    #[Route('/leaderboard', name: 'leaderboard', methods: ['GET'])]
    public function leaderboard(#[MapQueryParameter] int $limit = 25): JsonResponse
    {
        return $this->json($this->eventRepository->getLeaderboard($limit));
    }

    #[Route('/heatmap', name: 'heatmap', methods: ['GET'])]
    public function heatmap(): JsonResponse
    {
        return $this->json($this->eventRepository->getHeatmap());
    }

    #[Route('/hourly', name: 'hourly', methods: ['GET'])]
    public function hourly(): JsonResponse
    {
        return $this->json($this->eventRepository->getHourlyHistogram());
    }

    #[Route('/daily', name: 'daily', methods: ['GET'])]
    public function daily(): JsonResponse
    {
        return $this->json($this->eventRepository->getDailyStats());
    }

    #[Route('/weekday', name: 'weekday', methods: ['GET'])]
    public function weekday(): JsonResponse
    {
        return $this->json($this->eventRepository->getDayOfWeekStats());
    }

    #[Route('/concurrent', name: 'concurrent', methods: ['GET'])]
    public function concurrent(#[MapQueryParameter] int $days = 90): JsonResponse
    {
        return $this->json($this->eventRepository->getConcurrentPresence($days));
    }

    #[Route('/duration-distribution', name: 'duration_distribution', methods: ['GET'])]
    public function durationDistribution(): JsonResponse
    {
        return $this->json($this->eventRepository->getDurationDistribution());
    }

    #[Route('/frequency-vs-duration', name: 'frequency_vs_duration', methods: ['GET'])]
    public function frequencyVsDuration(): JsonResponse
    {
        return $this->json($this->eventRepository->getFrequencyVsDuration());
    }

    #[Route('/new-vs-returning', name: 'new_vs_returning', methods: ['GET'])]
    public function newVsReturning(): JsonResponse
    {
        return $this->json($this->eventRepository->getNewVsReturning());
    }

    /**
     * Background profile refresh endpoint — fetches fresh data from SL and returns it as JSON.
     * Called by the avatar page JS when the cached profile is stale (stale-while-revalidate).
     */
    #[Route('/avatar/{key}/profile', name: 'avatar_profile', methods: ['GET'], requirements: ['key' => '[0-9a-f\-]+'])]
    public function avatarProfile(string $key): JsonResponse
    {
        $profile = $this->profileService->fetchProfile($key, forceRefresh: true);
        if ($profile === null) {
            return $this->json(['error' => 'Profile not available'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'bio_html'  => $profile['bioHtml'],
            'image_url' => $profile['imageUrl'],
            'synced_at' => $profile['syncedAt']->getTimestamp(),
        ]);
    }

    #[Route('/avatar/{key}', name: 'avatar', methods: ['GET'], requirements: ['key' => '[0-9a-f\-]+'])]
    public function avatar(string $key): JsonResponse
    {
        $stats = $this->eventRepository->getAvatarStats($key);
        if (!$stats) {
            return $this->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'stats' => $stats,
            'hourly' => $this->eventRepository->getAvatarHourlyHistogram($key),
            'history' => $this->eventRepository->getAvatarVisitHistory($key),
        ]);
    }

    #[Route('/sync-status', name: 'sync_status', methods: ['GET'])]
    public function syncStatus(): JsonResponse
    {
        $state = $this->syncStateRepository->findOneBy([]);
        return $this->json([
            'last_row' => $state?->getLastRow() ?? 1,
            'synced_at' => $state?->getSyncedAt()?->getTimestamp(),
            'rows_synced' => $state?->getRowsSynced() ?? 0,
        ]);
    }

    #[Route('/reminders/active', name: 'reminders_active', methods: ['GET'])]
    public function remindersActive(): JsonResponse
    {
        $reminders = $this->reminderRepository->findAllActive();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $this->json(array_map(fn($r) => [
            'id'          => $r->getId(),
            'avatar_key'  => $r->getAvatarKey(),
            'content'     => $r->getContent(),
            'reminder_at' => $r->getReminderAt()->getTimestamp(),
            'is_overdue'  => $r->getReminderAt() < $now,
            'author'      => $r->getAuthor()->getUsername(),
        ], $reminders));
    }

    #[Route('/sync', name: 'sync', methods: ['POST'])]
    public function sync(): JsonResponse
    {
        try {
            $input = new ArrayInput([]);
            $output = new BufferedOutput();
            $this->syncSheetCommand->run($input, $output);
            return $this->json(['success' => true, 'output' => $output->fetch()]);
        } catch (\Throwable $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
