<?php

namespace App\Entity;

use App\Repository\SyncStateRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SyncStateRepository::class)]
#[ORM\Table(name: 'sync_state')]
class SyncState
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Last synced sheet row (1-based, header is row 1, data starts at row 2) */
    #[ORM\Column(name: 'last_row', type: 'integer')]
    private int $lastRow = 1;

    #[ORM\Column(name: 'synced_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $syncedAt = null;

    #[ORM\Column(name: 'rows_synced', type: 'integer')]
    private int $rowsSynced = 0;

    public function getId(): ?int { return $this->id; }

    public function getLastRow(): int { return $this->lastRow; }
    public function setLastRow(int $lastRow): static { $this->lastRow = $lastRow; return $this; }

    public function getSyncedAt(): ?\DateTimeImmutable { return $this->syncedAt; }
    public function setSyncedAt(\DateTimeImmutable $syncedAt): static { $this->syncedAt = $syncedAt; return $this; }

    public function getRowsSynced(): int { return $this->rowsSynced; }
    public function setRowsSynced(int $rowsSynced): static { $this->rowsSynced = $rowsSynced; return $this; }
}
