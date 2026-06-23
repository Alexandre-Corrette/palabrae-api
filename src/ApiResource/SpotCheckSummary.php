<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\State\SpotCheckSummaryProvider;

/**
 * Vue RESPONSABLE — signal d'intégrité des contrôles surprise, agrégé et anonyme.
 *
 * Montre que le registre n'est pas que de l'auto-déclaratif : combien de plans
 * scellés/révélés, combien de contrôles honorés vs manqués (prouvables), et le
 * volume de détections automatiques (capteur). Aucun nominatif.
 *
 * Réservé à ROLE_RESPONSABLE.
 */
#[ApiResource(
    shortName: 'SpotCheckSummary',
    operations: [
        new GetCollection(
            uriTemplate: '/spot-checks/summary',
            paginationEnabled: false,
        ),
    ],
    security: "is_granted('ROLE_RESPONSABLE')",
    provider: SpotCheckSummaryProvider::class,
)]
final class SpotCheckSummary
{
    public function __construct(
        public readonly int $plansSealed,
        public readonly int $plansRevealed,
        public readonly int $controlsHonored,
        public readonly int $controlsMissed,
        public readonly int $spotInspections,
        public readonly int $iotDetections,
    ) {
    }
}
