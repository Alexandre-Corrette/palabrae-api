<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Couche enseignante — le différenciateur. Au lieu de seulement signaler
 * « erreur », on sert une micro-leçon : le GESTE et le POURQUOI, dans le flux.
 * C'est ce qui transforme la surveillance en montée en compétence (gagnant-gagnant).
 */
#[ORM\Entity]
#[ORM\Table(name: 'micro_lesson')]
class MicroLesson
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $title;

    /** Le « pourquoi » (le risque, la règle) — court, lisible en service. */
    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $why;

    /** Le « geste » (l'action correcte concrète). */
    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $how;

    /** Durée cible de la leçon en secondes (doit rester courte). */
    #[ORM\Column]
    #[Assert\Range(max: 120)]
    private int $estimatedSeconds = 30;

    public function __construct(string $title, string $why, string $how, int $estimatedSeconds = 30)
    {
        $this->title = $title;
        $this->why = $why;
        $this->how = $how;
        $this->estimatedSeconds = $estimatedSeconds;
    }

    public function getId(): ?int { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function getWhy(): string { return $this->why; }
    public function getHow(): string { return $this->how; }
    public function getEstimatedSeconds(): int { return $this->estimatedSeconds; }
}
