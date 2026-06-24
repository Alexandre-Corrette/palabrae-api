<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Severity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

    /**
     * Ce point exige-t-il une PREUVE PHOTO (prise en direct) pour être clôturé ?
     * Ex. température frigo, huile de friture, étiquette du jour. La validation
     * est bloquée tant que la photo n'est pas fournie.
     */
    #[ORM\Column]
    private bool $requiresPhoto = false;

    /**
     * Critères composant ce point (les « cases » d'une fiche). Vide = point
     * simple (conforme/écart d'un bloc).
     *
     * @var Collection<int, ControlCriterion>
     */
    #[ORM\OneToMany(targetEntity: ControlCriterion::class, mappedBy: 'controlPoint', cascade: ['persist'])]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $criteria;

    public function __construct(Investigation $procedure, string $code, string $label, Severity $severity)
    {
        $this->procedure = $procedure;
        $this->code = $code;
        $this->label = $label;
        $this->severity = $severity;
        $this->criteria = new ArrayCollection();
    }

    public function attachLesson(MicroLesson $lesson): void { $this->lesson = $lesson; }

    public function setRequiresPhoto(bool $requiresPhoto): void { $this->requiresPhoto = $requiresPhoto; }

    public function addCriterion(ControlCriterion $criterion): void
    {
        if (!$this->criteria->contains($criterion)) {
            $this->criteria->add($criterion);
        }
    }

    /** @return Collection<int, ControlCriterion> */
    public function getCriteria(): Collection { return $this->criteria; }

    public function hasCriteria(): bool { return !$this->criteria->isEmpty(); }

    public function getId(): ?int { return $this->id; }
    public function getProcedure(): Investigation { return $this->procedure; }
    public function getCode(): string { return $this->code; }
    public function getLabel(): string { return $this->label; }
    public function getSeverity(): Severity { return $this->severity; }
    public function getLesson(): ?MicroLesson { return $this->lesson; }
    public function requiresPhoto(): bool { return $this->requiresPhoto; }
}
