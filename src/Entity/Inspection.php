<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\InspectionOutcome;
use App\Enum\InspectionSource;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Trace d'un CONTRÔLE effectué sur un point — conforme ou non.
 *
 * Complète Deviation : là où Deviation est le « fait d'écart » neutre,
 * Inspection enregistre CHAQUE vérification (y compris conforme), avec sa
 * SOURCE (déclarée / contrôle surprise / capteur). Elle alimente le décompte
 * « conformes » du responsable et matérialise le mérite — « ce qui est bien
 * fait » — autant que l'écart.
 *
 * SÉCU / RGPD : operatorRef (le QUI) est isolé comme dans Deviation. Le chemin
 * agrégé (responsable) ne lit jamais ce champ ; il ne compte que des totaux.
 */
#[ORM\Entity]
#[ORM\Table(name: 'inspection')]
class Inspection
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ControlPoint $controlPoint;

    #[ORM\Column(enumType: InspectionOutcome::class)]
    private InspectionOutcome $outcome;

    #[ORM\Column(enumType: InspectionSource::class)]
    private InspectionSource $source;

    /** Référence opérateur PSEUDONYMISÉE (matricule). Accès réservé, jamais agrégé nominativement. */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $operatorRef = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[ORM\Column]
    private \DateTimeImmutable $recordedAt;

    public function __construct(
        ControlPoint $controlPoint,
        InspectionOutcome $outcome,
        InspectionSource $source,
        ?string $operatorRef = null,
        ?string $note = null,
    ) {
        $this->controlPoint = $controlPoint;
        $this->outcome = $outcome;
        $this->source = $source;
        $this->operatorRef = $operatorRef;
        $this->note = $note;
        $this->recordedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getControlPoint(): ControlPoint { return $this->controlPoint; }
    public function getOutcome(): InspectionOutcome { return $this->outcome; }
    public function getSource(): InspectionSource { return $this->source; }
    public function getNote(): ?string { return $this->note; }
    public function getRecordedAt(): \DateTimeImmutable { return $this->recordedAt; }

    /** Accès nominatif réservé (audit/mérite). Ne jamais lire depuis un chemin agrégé. */
    public function getOperatorRefForAudit(): ?string { return $this->operatorRef; }
}
