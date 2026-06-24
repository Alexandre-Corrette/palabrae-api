<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Severity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Écart détecté sur un point de contrôle. C'est le FAIT PROCÉDURAL neutre
 * (quoi, où, quand, quelle gravité) — ce qui sert à la sécurité alimentaire
 * et au pilotage agrégé.
 *
 * SÉCU / RGPD : operatorRef (QUI) est volontairement isolé. Sa lecture passe
 * par CoachingDataVoter et n'est autorisée qu'à finalité COACHING. Le chemin
 * agrégé (manager, conformité) ne lit jamais ce champ.
 */
#[ORM\Entity]
#[ORM\Table(name: 'deviation')]
class Deviation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ControlPoint $controlPoint;

    #[ORM\Column]
    private \DateTimeImmutable $detectedAt;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    /**
     * Référence opérateur PSEUDONYMISÉE (matricule, pas le nom).
     * Accès strictement gardé — voir CoachingDataVoter.
     */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $operatorRef = null;

    #[ORM\Column]
    private bool $resolved = false;

    /**
     * Gravité EFFECTIVE quand elle diffère de celle du point — ex. écart issu
     * de l'évaluation de critères : on prend la plus haute gravité des critères
     * en défaut. Null = on retombe sur la gravité nominale du point.
     */
    #[ORM\Column(enumType: Severity::class, nullable: true)]
    private ?Severity $severityOverride = null;

    public function __construct(ControlPoint $controlPoint, ?string $operatorRef = null, ?string $note = null)
    {
        $this->controlPoint = $controlPoint;
        $this->operatorRef = $operatorRef;
        $this->note = $note;
        $this->detectedAt = new \DateTimeImmutable();
    }

    public function overrideSeverity(Severity $severity): void
    {
        $this->severityOverride = $severity;
    }

    public function severity(): Severity
    {
        return $this->severityOverride ?? $this->controlPoint->getSeverity();
    }

    public function resolve(): void { $this->resolved = true; }

    public function getId(): ?int { return $this->id; }
    public function getControlPoint(): ControlPoint { return $this->controlPoint; }
    public function getDetectedAt(): \DateTimeImmutable { return $this->detectedAt; }
    public function getNote(): ?string { return $this->note; }
    public function isResolved(): bool { return $this->resolved; }

    /**
     * Accès nominatif réservé. Ne JAMAIS appeler depuis un chemin agrégé/manager.
     * Toujours protéger par un appel isGranted('VIEW_COACHING_DATA', $deviation).
     */
    public function getOperatorRefForCoaching(): ?string { return $this->operatorRef; }
}
