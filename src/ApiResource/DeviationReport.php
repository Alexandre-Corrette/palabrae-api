<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Enum\Severity;
use App\State\DeviationReportProcessor;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Signalement d'un écart sur un point de contrôle — premier flux métier écrivant.
 *
 * En entrée, l'opérateur ne fournit que le point concerné et une note libre :
 * le « qui » (operatorRef) est dérivé du CONTEXTE AUTHENTIFIÉ côté serveur,
 * jamais transmis par le client. En sortie, on renvoie la gravité et les actions
 * déclenchées (escalade), SANS aucune donnée nominative.
 *
 * L'entité Deviation n'est volontairement pas auto-exposée : tout passe par ce
 * DTO + son processor, qui orchestre DeviationHandler (escalade proportionnée,
 * coaching muré, journal d'intégrité).
 */
#[ApiResource(
    shortName: 'DeviationReport',
    operations: [
        new Post(
            uriTemplate: '/deviations',
            security: "is_granted('ROLE_OPERATEUR')",
            normalizationContext: ['groups' => ['deviation:read']],
            denormalizationContext: ['groups' => ['deviation:write']],
            processor: DeviationReportProcessor::class,
        ),
    ],
)]
final class DeviationReport
{
    #[Groups(['deviation:read'])]
    public ?int $id = null;

    /** Code du point de contrôle concerné (ex. "CCP-FROID-01"). */
    #[Assert\NotBlank]
    #[Groups(['deviation:read', 'deviation:write'])]
    public string $controlPointCode = '';

    /** Note libre de l'opérateur (optionnelle). */
    #[Assert\Length(max: 2000)]
    #[Groups(['deviation:read', 'deviation:write'])]
    public ?string $note = null;

    /** Gravité du point (dérivée côté serveur, non modifiable par le client). */
    #[Groups(['deviation:read'])]
    public ?Severity $severity = null;

    #[Groups(['deviation:read'])]
    public ?\DateTimeImmutable $detectedAt = null;

    /**
     * Actions d'escalade déclenchées (sans donnée nominative).
     *
     * @var string[]
     */
    #[Groups(['deviation:read'])]
    public array $actions = [];
}
