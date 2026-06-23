<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\InvestigationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * NOYAU GÉNÉRIQUE — instance de procédure.
 *
 * Nom hérité de la méthode (Marc Bloch) : on « instruit » le déroulé d'une
 * procédure, on ne juge pas un fait. Le moteur reste neutre et dépolitisé ;
 * il vérifie l'INTÉGRITÉ DU GESTE (a-t-on suivi les bonnes étapes, dans le bon
 * ordre ?), pas la vérité d'un fait.
 *
 * En restauration, une Investigation = une instance concrète de procédure :
 * un service (midi) sur un site (cantine), un jour donné. Les ControlPoint
 * (couche verticale HACCP) s'y rattachent.
 */
#[ORM\Entity(repositoryClass: InvestigationRepository::class)]
#[ORM\Table(name: 'investigation')]
class Investigation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Référence métier stable de l'instance (ex. "cantine-leo-lagrange:2026-06-23:midi"). */
    #[ORM\Column(length: 128, unique: true)]
    #[Assert\NotBlank]
    private string $reference;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $label;

    /** Référence du site (pseudonymisée si besoin). */
    #[ORM\Column(length: 64)]
    #[Assert\NotBlank]
    private string $siteRef;

    #[ORM\Column]
    private \DateTimeImmutable $openedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $closedAt = null;

    /** @var Collection<int, ControlPoint> */
    #[ORM\OneToMany(targetEntity: ControlPoint::class, mappedBy: 'procedure')]
    private Collection $controlPoints;

    public function __construct(string $reference, string $label, string $siteRef)
    {
        $this->reference = $reference;
        $this->label = $label;
        $this->siteRef = $siteRef;
        $this->openedAt = new \DateTimeImmutable();
        $this->controlPoints = new ArrayCollection();
    }

    public function close(): void
    {
        $this->closedAt ??= new \DateTimeImmutable();
    }

    public function isOpen(): bool
    {
        return $this->closedAt === null;
    }

    public function getId(): ?int { return $this->id; }
    public function getReference(): string { return $this->reference; }
    public function getLabel(): string { return $this->label; }
    public function getSiteRef(): string { return $this->siteRef; }
    public function getOpenedAt(): \DateTimeImmutable { return $this->openedAt; }
    public function getClosedAt(): ?\DateTimeImmutable { return $this->closedAt; }

    /** @return Collection<int, ControlPoint> */
    public function getControlPoints(): Collection { return $this->controlPoints; }
}
