<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\InspectionOutcome;
use App\Enum\Severity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un contrôle à réception = un BON DE LIVRAISON contrôlé, avec plusieurs lignes
 * produit. C'est l'équivalent numérique (et prouvable) de la fiche METRO N°5
 * remplie à l'arrivée d'une livraison.
 *
 * Le résultat global (conforme / écart + gravité effective) est dérivé de
 * l'évaluation de chaque ligne sur les critères du point de contrôle.
 */
#[ORM\Entity]
#[ORM\Table(name: 'reception')]
class Reception
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ControlPoint $controlPoint;

    /** Numéro du bon de livraison. */
    #[ORM\Column(length: 64)]
    private string $blNumber;

    /** Référence opérateur pseudonymisée (qui a réceptionné) — audit/mérite. */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $operatorRef = null;

    #[ORM\Column]
    private \DateTimeImmutable $recordedAt;

    #[ORM\Column(enumType: InspectionOutcome::class)]
    private InspectionOutcome $outcome = InspectionOutcome::CONFORM;

    /** Gravité effective si écart (la plus haute des critères en défaut). */
    #[ORM\Column(enumType: Severity::class, nullable: true)]
    private ?Severity $severity = null;

    /** @var Collection<int, ReceptionLine> */
    #[ORM\OneToMany(targetEntity: ReceptionLine::class, mappedBy: 'reception', cascade: ['persist'])]
    private Collection $lines;

    public function __construct(ControlPoint $controlPoint, string $blNumber, ?string $operatorRef = null)
    {
        $this->controlPoint = $controlPoint;
        $this->blNumber = $blNumber;
        $this->operatorRef = $operatorRef;
        $this->recordedAt = new \DateTimeImmutable();
        $this->lines = new ArrayCollection();
    }

    public function addLine(ReceptionLine $line): void
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
        }
    }

    public function conclude(InspectionOutcome $outcome, ?Severity $severity): void
    {
        $this->outcome = $outcome;
        $this->severity = $severity;
    }

    public function getId(): ?int { return $this->id; }
    public function getControlPoint(): ControlPoint { return $this->controlPoint; }
    public function getBlNumber(): string { return $this->blNumber; }
    public function getRecordedAt(): \DateTimeImmutable { return $this->recordedAt; }
    public function getOutcome(): InspectionOutcome { return $this->outcome; }
    public function getSeverity(): ?Severity { return $this->severity; }

    /** @return Collection<int, ReceptionLine> */
    public function getLines(): Collection { return $this->lines; }

    /** Accès nominatif réservé (audit). */
    public function getOperatorRefForAudit(): ?string { return $this->operatorRef; }
}
