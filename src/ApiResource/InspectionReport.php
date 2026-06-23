<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Enum\InspectionOutcome;
use App\Enum\InspectionSource;
use App\State\InspectionReportProcessor;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Marquer un point de contrôle « conforme » (parcours « Tout est conforme »).
 *
 * Le pendant positif de DeviationReport : on enregistre que le geste a été bien
 * fait. Comme pour l'écart, le « qui » (operatorRef) vient du CONTEXTE
 * AUTHENTIFIÉ, jamais du client ; la sortie ne contient aucun champ nominatif.
 *
 * MVP : seules les inspections DÉCLARÉES par l'opérateur passent par cet
 * endpoint, avec un résultat CONFORME. Les contrôles surprise (source SPOT) et
 * capteurs (IOT) seront alimentés par d'autres flux (cf. GH-203).
 */
#[ApiResource(
    shortName: 'InspectionReport',
    operations: [
        new Post(
            uriTemplate: '/inspections',
            security: "is_granted('ROLE_OPERATEUR')",
            normalizationContext: ['groups' => ['inspection:read']],
            denormalizationContext: ['groups' => ['inspection:write']],
            processor: InspectionReportProcessor::class,
        ),
    ],
)]
final class InspectionReport
{
    #[Groups(['inspection:read'])]
    public ?int $id = null;

    /** Code du point de contrôle vérifié (ex. "CCP-FROID-01"). */
    #[Assert\NotBlank]
    #[Groups(['inspection:read', 'inspection:write'])]
    public string $controlPointCode = '';

    #[Assert\Length(max: 2000)]
    #[Groups(['inspection:read', 'inspection:write'])]
    public ?string $note = null;

    #[Groups(['inspection:read'])]
    public ?InspectionOutcome $outcome = null;

    #[Groups(['inspection:read'])]
    public ?InspectionSource $source = null;

    #[Groups(['inspection:read'])]
    public ?\DateTimeImmutable $recordedAt = null;
}
