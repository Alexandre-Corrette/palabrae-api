<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Severity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Verticale RESTAURATION — point de contrôle (CCP au sens HACCP).
 *
 * Se greffe sur le noyau : rattaché à une Investigation, qui ici représente
 * l'instance de procédure (un service, un site-jour). Le noyau reste générique ;
 * seule cette couche parle « restauration ».
 */
#[ORM\Entity]
#[ORM\Table(name: 'control_point')]
class ControlPoint
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Investigation $procedure;

    #[ORM\Column(length: 64)]
    #[Assert\NotBlank]
    private string $code; // ex. "CCP-FROID-01"

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $label;

    #[ORM\Column(enumType: Severity::class)]
    private Severity $severity;

    /** Leçon contextuelle servie quand un écart est détecté sur ce point. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?MicroLesson $lesson = null;

    public function __construct(Investigation $procedure, string $code, string $label, Severity $severity)
    {
        $this->procedure = $procedure;
        $this->code = $code;
        $this->label = $label;
        $this->severity = $severity;
    }

    public function attachLesson(MicroLesson $lesson): void { $this->lesson = $lesson; }

    public function getId(): ?int { return $this->id; }
    public function getProcedure(): Investigation { return $this->procedure; }
    public function getCode(): string { return $this->code; }
    public function getLabel(): string { return $this->label; }
    public function getSeverity(): Severity { return $this->severity; }
    public function getLesson(): ?MicroLesson { return $this->lesson; }
}
