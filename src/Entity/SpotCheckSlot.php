<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\SlotStatus;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un créneau de contrôle engagé dans le plan. Son existence est comptée DANS le
 * commitment : à la clôture, un slot resté PLANNED devient MISSED, et comme
 * l'attente était scellée à l'avance, l'absence est PROUVABLE (on ne peut pas
 * cacher un oubli).
 *
 * SÉCU/RGPD : operatorRef (l'employé désigné) est donnée coaching, derrière le
 * même mur que Deviation (CoachingDataVoter). Le décompte manqué qui remonte au
 * responsable reste agrégé et anonyme.
 */
#[ORM\Entity]
#[ORM\Table(name: 'spotcheck_slot')]
class SpotCheckSlot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'slots')]
    #[ORM\JoinColumn(nullable: false)]
    private SpotCheckPlan $plan;

    #[ORM\Column]
    private int $ordinal;

    #[ORM\Column(enumType: SlotStatus::class)]
    private SlotStatus $status = SlotStatus::PLANNED;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $honoredAt = null;

    /** Employé désigné au moment du tir (coaching-only). */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $operatorRef = null;

    /** Point de contrôle tiré au sort au moment du tir. */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $controlPointCode = null;

    public function __construct(SpotCheckPlan $plan, int $ordinal)
    {
        $this->plan = $plan;
        $this->ordinal = $ordinal;
        $plan->addSlot($this);
    }

    public function honor(string $operatorRef, string $controlPointCode): void
    {
        if ($this->status !== SlotStatus::PLANNED) {
            throw new \LogicException('Slot déjà clôturé.');
        }
        $this->operatorRef = $operatorRef;
        $this->controlPointCode = $controlPointCode;
        $this->honoredAt = new \DateTimeImmutable();
        $this->status = SlotStatus::HONORED;
    }

    public function miss(): void
    {
        if ($this->status === SlotStatus::PLANNED) {
            $this->status = SlotStatus::MISSED;
        }
    }

    public function getId(): ?int { return $this->id; }
    public function getPlan(): SpotCheckPlan { return $this->plan; }
    public function getOrdinal(): int { return $this->ordinal; }
    public function getStatus(): SlotStatus { return $this->status; }
    public function getHonoredAt(): ?\DateTimeImmutable { return $this->honoredAt; }
    public function getControlPointCode(): ?string { return $this->controlPointCode; }

    /** Accès nominatif réservé coaching — protéger via isGranted('VIEW_COACHING_DATA', ...). */
    public function getOperatorRefForCoaching(): ?string { return $this->operatorRef; }
}
