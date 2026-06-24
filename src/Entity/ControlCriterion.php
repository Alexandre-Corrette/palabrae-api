<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Comparator;
use App\Enum\CriterionType;
use App\Enum\Severity;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un critère d'un point de contrôle — une « case » d'une fiche (ex. la fiche
 * réception METRO : T°, DLC/DLUO, fraîcheur, emballage, étiquettes sanitaires,
 * quantité).
 *
 * C'est la traduction d'une fiche papier en RÈGLE MÉTIER exécutable : chaque
 * critère porte sa propre gravité (si en défaut) et, pour les mesures, son seuil.
 * Le logiciel tranche conforme/non conforme — plus le jugement de l'opérateur.
 */
#[ORM\Entity]
#[ORM\Table(name: 'control_criterion')]
class ControlCriterion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'criteria')]
    #[ORM\JoinColumn(nullable: false)]
    private ControlPoint $controlPoint;

    #[ORM\Column(length: 64)]
    private string $code;

    #[ORM\Column(length: 255)]
    private string $label;

    #[ORM\Column(enumType: CriterionType::class)]
    private CriterionType $type;

    /** Gravité de l'écart si ce critère est en défaut. */
    #[ORM\Column(enumType: Severity::class)]
    private Severity $severity;

    /** Comparateur de la règle de mesure (null pour un critère booléen). */
    #[ORM\Column(enumType: Comparator::class, nullable: true)]
    private ?Comparator $comparator = null;

    /** Seuil de la règle de mesure (null pour un critère booléen). */
    #[ORM\Column(nullable: true)]
    private ?float $threshold = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $unit = null;

    #[ORM\Column]
    private int $position = 0;

    public function __construct(
        ControlPoint $controlPoint,
        string $code,
        string $label,
        CriterionType $type,
        Severity $severity,
        int $position = 0,
    ) {
        $this->controlPoint = $controlPoint;
        $this->code = $code;
        $this->label = $label;
        $this->type = $type;
        $this->severity = $severity;
        $this->position = $position;
        $controlPoint->addCriterion($this);
    }

    /** Pose la règle de mesure (ex. ≤ 4 °C). Retour fluide pour les fixtures. */
    public function withRule(Comparator $comparator, float $threshold, ?string $unit = null): static
    {
        $this->comparator = $comparator;
        $this->threshold = $threshold;
        $this->unit = $unit;

        return $this;
    }

    public function getId(): ?int { return $this->id; }
    public function getControlPoint(): ControlPoint { return $this->controlPoint; }
    public function getCode(): string { return $this->code; }
    public function getLabel(): string { return $this->label; }
    public function getType(): CriterionType { return $this->type; }
    public function getSeverity(): Severity { return $this->severity; }
    public function getComparator(): ?Comparator { return $this->comparator; }
    public function getThreshold(): ?float { return $this->threshold; }
    public function getUnit(): ?string { return $this->unit; }
    public function getPosition(): int { return $this->position; }

    /** Libellé lisible de la règle (ex. « ≤ 4 °C »), ou null si booléen. */
    public function ruleLabel(): ?string
    {
        if ($this->comparator === null || $this->threshold === null) {
            return null;
        }
        $threshold = rtrim(rtrim(sprintf('%.2f', $this->threshold), '0'), '.');

        return trim(sprintf('%s %s %s', $this->comparator->label(), $threshold, $this->unit ?? ''));
    }
}
