<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Enum\InspectionSource;
use App\State\SpotCheckHonorProcessor;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Honorer un contrôle surprise : l'opérateur réalise effectivement le contrôle
 * tiré au sort.
 *
 * Effet : un slot PLANNED du plan scellé passe HONORED, et une Inspection
 * source SPOT est enregistrée (conforme par défaut). Si l'opérateur constate un
 * écart, il le signale en plus via POST /api/deviations.
 *
 * Comme l'attente était scellée à l'avance, un slot non honoré à la clôture
 * devient MISSED — l'absence est donc prouvable, et le honor matérialise le
 * mérite du geste réalisé.
 */
#[ApiResource(
    shortName: 'SpotCheckHonor',
    operations: [
        new Post(
            uriTemplate: '/spot-checks/honor',
            security: "is_granted('ROLE_OPERATEUR')",
            normalizationContext: ['groups' => ['spotcheck_honor:read']],
            denormalizationContext: ['groups' => ['spotcheck_honor:write']],
            processor: SpotCheckHonorProcessor::class,
        ),
    ],
)]
final class SpotCheckHonor
{
    /** Créneau scellé concerné (ex. "SITE-...:2026-06-23:midi"). */
    #[Assert\NotBlank]
    #[Groups(['spotcheck_honor:read', 'spotcheck_honor:write'])]
    public string $windowRef = '';

    /** Point de contrôle effectivement vérifié. */
    #[Assert\NotBlank]
    #[Groups(['spotcheck_honor:read', 'spotcheck_honor:write'])]
    public string $controlPointCode = '';

    #[Groups(['spotcheck_honor:read'])]
    public ?int $ordinal = null;

    #[Groups(['spotcheck_honor:read'])]
    public ?InspectionSource $source = null;

    #[Groups(['spotcheck_honor:read'])]
    public ?\DateTimeImmutable $honoredAt = null;
}
