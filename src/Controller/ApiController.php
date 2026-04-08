<?php

namespace App\Controller;

use App\Repository\EventRepository;
use App\Repository\SyncStateRepository;
use App\Service\GoogleSheetsService;
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
    ) {}

    #[Route('/live-visitors', name: 'live_visitors', methods: ['GET'])]
    public function liveVisitors(): JsonResponse
    {
        return $this->json($this->eventRepository->getLiveVisitors());
    }

    #[Route('/recent-visitors', name: 'recent_visitors', methods: ['GET'])]
    public function recentVisitors(#[MapQueryParameter] string $period = 'today'): JsonResponse
    {
        $allowed = ['today', 'yesterday', '3days', 'week', 'month', 'year', 'lastyear', 'all'];
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
            'synced_at' => $state?->getSyncedAt()?->format('c'),
            'rows_synced' => $state?->getRowsSynced() ?? 0,
        ]);
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
