<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PlanStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Plan scellé d'un créneau de service (schéma commit-reveal).
 *
 * À l'ouverture : on s'engage sur un NOMBRE de contrôles et un timing tirés au
 * hasard, en ne publiant que le `commitment` (un hash). La graine reste secrète.
 * À la clôture : on révèle la graine ; n'importe qui recalcule et vérifie que
 * le plan correspond au commitment — preuve qu'il était fixé À L'AVANCE, donc
 * ni avancé, ni masqué, ni rétro-ajusté.
 *
 * La base ne contient JAMAIS la graine ni les horaires tant que SEALED :
 * pas de pré-image → personne ne peut anticiper le moment du contrôle.
 */
#[ORM\Entity]
#[ORM\Table(name: 'spotcheck_plan')]
class SpotCheckPlan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 128, unique: true)]
    private string $windowRef;

    #[ORM\Column(length: 64)]
    private string $siteRef;

    #[ORM\Column]
    private int $count;

    /** Engagement public = hash de (windowRef, count, graine). Aucune pré-image. */
    #[ORM\Column(length: 64)]
    private string $commitment;

    #[ORM\Column(length: 16)]
    private string $algo = 'sha256';

    #[ORM\Column(enumType: PlanStatus::class)]
    private PlanStatus $status = PlanStatus::SEALED;

    #[ORM\Column]
    private \DateTimeImmutable $sealedAt;

    /** Renseignée seulement à la révélation (hex). Permet la vérification publique. */
    #[ORM\Column(length: 128, nullable: true)]
    private ?string $revealedSeed = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $revealedAt = null;

    /** @var Collection<int, SpotCheckSlot> */
    #[ORM\OneToMany(targetEntity: SpotCheckSlot::class, mappedBy: 'plan', cascade: ['persist'])]
    private Collection $slots;

    public function __construct(string $windowRef, string $siteRef, int $count, string $commitment)
    {
        $this->windowRef = $windowRef;
        $this->siteRef = $siteRef;
        $this->count = $count;
        $this->commitment = $commitment;
        $this->sealedAt = new \DateTimeImmutable();
        $this->slots = new ArrayCollection();
    }

    public function addSlot(SpotCheckSlot $s): void
    {
        if (!$this->slots->contains($s)) {
            $this->slots->add($s);
        }
    }

    /** Révèle la graine après clôture (la vérification du commitment se fait dans le planner). */
    public function reveal(string $seedHex): void
    {
        if ($this->status === PlanStatus::REVEALED) {
            throw new \LogicException('Plan déjà révélé.');
        }
        $this->revealedSeed = $seedHex;
        $this->revealedAt = new \DateTimeImmutable();
        $this->status = PlanStatus::REVEALED;
    }

    public function getId(): ?int { return $this->id; }
    public function getWindowRef(): string { return $this->windowRef; }
    public function getSiteRef(): string { return $this->siteRef; }
    public function getCount(): int { return $this->count; }
    public function getCommitment(): string { return $this->commitment; }
    public function getAlgo(): string { return $this->algo; }
    public function getStatus(): PlanStatus { return $this->status; }
    public function getSealedAt(): \DateTimeImmutable { return $this->sealedAt; }
    public function getRevealedSeed(): ?string { return $this->revealedSeed; }
    public function getRevealedAt(): ?\DateTimeImmutable { return $this->revealedAt; }

    /** @return Collection<int, SpotCheckSlot> */
    public function getSlots(): Collection { return $this->slots; }
}
