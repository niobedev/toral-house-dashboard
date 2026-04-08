<?php

namespace App\Controller;

use App\Repository\EventRepository;
use App\Repository\SyncStateRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly SyncStateRepository $syncStateRepository,
    ) {}

    #[Route('/', name: 'app_dashboard')]
    public function index(): Response
    {
        $syncState = $this->syncStateRepository->findOneBy([]);

        return $this->render('dashboard/index.html.twig', [
            'sync_state' => $syncState,
        ]);
    }

    #[Route('/avatar/{key}', name: 'app_avatar', requirements: ['key' => '[0-9a-f\-]+'])]
    public function avatar(string $key): Response
    {
        $stats = $this->eventRepository->getAvatarStats($key);
        if (!$stats) {
            throw $this->createNotFoundException('Avatar not found.');
        }

        return $this->render('dashboard/avatar.html.twig', [
            'stats' => $stats,
            'avatar_key' => $key,
        ]);
    }
}
