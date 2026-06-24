<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Enum\Severity;
use App\State\ControlPointCollectionProvider;

/**
 * Vue OPÉRATEUR — checklist des points de contrôle d'un service.
 *
 * Lecture seule, réservée aux utilisateurs authentifiés. Filtrable par procédure
 * (`?procedure=<reference>`) pour borner au service courant. Aucune donnée
 * nominative : ce sont des points de contrôle (référentiel), pas des écarts.
 */
#[ApiResource(
    shortName: 'ControlPointView',
    operations: [
        new GetCollection(
            uriTemplate: '/control-points',
            paginationEnabled: false,
        ),
    ],
    security: "is_granted('ROLE_USER')",
    provider: ControlPointCollectionProvider::class,
)]
final class ControlPointView
{
    public function __construct(
        public readonly string $code,
        public readonly string $label,
        public readonly Severity $severity,
        /** Libellé humain de la gravité (ex. "Critique"). */
        public readonly string $severityLabel,
        public readonly ?LessonView $lesson,
        public readonly string $procedureReference,
        /** Ce point exige une preuve photo (prise en direct) pour être clôturé. */
        public readonly bool $requiresPhoto,
        /**
         * Critères composant le point (les « cases » d'une fiche). Vide = point
         * simple (conforme/écart d'un bloc).
         *
         * @var CriterionView[]
         */
        public readonly array $criteria,
    ) {
    }
}
