<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Enum\Severity;
use App\State\ComplianceSummaryProvider;

/**
 * Vue RESPONSABLE — AGRÉGÉE et ANONYME, par conception.
 *
 * C'est la seule lecture des écarts offerte au pilotage : un décompte par point
 * de contrôle et par gravité. AUCUN champ nominatif (operatorRef) n'est, ni ne
 * peut être, exposé ici. Le « qui » reste muré côté coaching (CoachingDataVoter)
 * et n'est jamais traversé par ce chemin.
 *
 * Réservé à ROLE_RESPONSABLE : un opérateur n'a pas à voir le pilotage agrégé.
 */
#[ApiResource(
    shortName: 'ComplianceSummary',
    operations: [
        new GetCollection(
            uriTemplate: '/compliance/summary',
            paginationEnabled: false,
        ),
    ],
    security: "is_granted('ROLE_RESPONSABLE')",
    provider: ComplianceSummaryProvider::class,
)]
final class ComplianceSummary
{
    public function __construct(
        public readonly string $controlPointCode,
        public readonly string $controlPointLabel,
        public readonly Severity $severity,
        public readonly int $deviationCount,
        public readonly int $unresolvedCount,
        public readonly int $conformCount,
    ) {
    }
}
