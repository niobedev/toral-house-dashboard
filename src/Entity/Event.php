<?php

namespace App\Entity;

use App\Repository\EventRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\Table(name: 'event')]
#[ORM\Index(columns: ['event_ts'], name: 'idx_event_ts')]
#[ORM\Index(columns: ['avatar_key'], name: 'idx_avatar_key')]
#[ORM\Index(columns: ['action', 'event_ts'], name: 'idx_action_ts')]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'event_ts', type: 'datetime_immutable')]
    private \DateTimeImmutable $eventTs;

    #[ORM\Column(length: 10)]
    private string $action; // 'join' | 'quit'

    #[ORM\Column(name: 'avatar_key', length: 36)]
    private string $avatarKey;

    #[ORM\Column(name: 'display_name', length: 100)]
    private string $displayName;

    #[ORM\Column(length: 100)]
    private string $username;

    public function getId(): ?int { return $this->id; }

    public function getEventTs(): \DateTimeImmutable { return $this->eventTs; }
    public function setEventTs(\DateTimeImmutable $eventTs): static { $this->eventTs = $eventTs; return $this; }

    public function getAction(): string { return $this->action; }
    public function setAction(string $action): static { $this->action = $action; return $this; }

    public function getAvatarKey(): string { return $this->avatarKey; }
    public function setAvatarKey(string $avatarKey): static { $this->avatarKey = $avatarKey; return $this; }

    public function getDisplayName(): string { return $this->displayName; }
    public function setDisplayName(string $displayName): static { $this->displayName = $displayName; return $this; }

    public function getUsername(): string { return $this->username; }
    public function setUsername(string $username): static { $this->username = $username; return $this; }
}
