<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Enum\Severity;
use App\State\ReceptionCheckProcessor;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Évaluation d'un contrôle composé de critères (ex. la fiche réception).
 *
 * L'opérateur fournit ses réponses par critère ; le SERVEUR applique les règles
 * métier (seuils, gravités), décide conforme/non conforme par critère, et
 * dérive la gravité globale (la plus haute des critères en défaut) + l'escalade.
 * La photo et la décision ne dépendent plus du jugement de l'opérateur.
 */
#[ApiResource(
    shortName: 'ReceptionCheck',
    operations: [
        new Post(
            uriTemplate: '/reception-check',
            security: "is_granted('ROLE_OPERATEUR')",
            normalizationContext: ['groups' => ['reception:read']],
            denormalizationContext: ['groups' => ['reception:write']],
            processor: ReceptionCheckProcessor::class,
        ),
    ],
)]
final class ReceptionCheck
{
    #[Assert\NotBlank]
    #[Groups(['reception:read', 'reception:write'])]
    public string $controlPointCode = '';

    /** Numéro du bon de livraison. */
    #[Assert\NotBlank]
    #[Groups(['reception:read', 'reception:write'])]
    public string $blNumber = '';

    /**
     * Lignes produit du BL. Chaque ligne :
     *   { productLabel, productCode?, answers: { codeCritère: bool|nombre } }
     *
     * @var array<int, array<string, mixed>>
     */
    #[Assert\Count(min: 1)]
    #[Groups(['reception:write'])]
    public array $lines = [];

    /** 'conform' | 'deviation' */
    #[Groups(['reception:read'])]
    public ?string $outcome = null;

    /** Gravité effective (la plus haute des critères en défaut), null si conforme. */
    #[Groups(['reception:read'])]
    public ?Severity $severity = null;

    #[Groups(['reception:read'])]
    public ?string $severityLabel = null;

    /**
     * Verdict par ligne produit :
     *   { productLabel, productCode, conform, results: [{code, label, conform}] }
     *
     * @var list<array<string, mixed>>
     */
    #[Groups(['reception:read'])]
    public array $lineResults = [];

    /**
     * Actions d'escalade déclenchées (sans donnée nominative).
     *
     * @var string[]
     */
    #[Groups(['reception:read'])]
    public array $actions = [];
}
