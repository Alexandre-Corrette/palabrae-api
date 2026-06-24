<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une ligne produit d'un bon de livraison contrôlé (une ligne de la fiche
 * réception). Porte la désignation/code du produit et le verdict par critère.
 */
#[ORM\Entity]
#[ORM\Table(name: 'reception_line')]
class ReceptionLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false)]
    private Reception $reception;

    #[ORM\Column(length: 191)]
    private string $productLabel;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $productCode = null;

    /**
     * Verdict par critère : [{criterion, label, value, conform}].
     *
     * @var list<array{criterion: string, label: string, value: mixed, conform: bool}>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $results = [];

    #[ORM\Column]
    private bool $conform = true;

    /**
     * @param list<array{criterion: string, label: string, value: mixed, conform: bool}> $results
     */
    public function __construct(Reception $reception, string $productLabel, ?string $productCode, array $results, bool $conform)
    {
        $this->reception = $reception;
        $this->productLabel = $productLabel;
        $this->productCode = $productCode;
        $this->results = $results;
        $this->conform = $conform;
        $reception->addLine($this);
    }

    public function getId(): ?int { return $this->id; }
    public function getReception(): Reception { return $this->reception; }
    public function getProductLabel(): string { return $this->productLabel; }
    public function getProductCode(): ?string { return $this->productCode; }

    /** @return list<array{criterion: string, label: string, value: mixed, conform: bool}> */
    public function getResults(): array { return $this->results; }

    public function isConform(): bool { return $this->conform; }
}
