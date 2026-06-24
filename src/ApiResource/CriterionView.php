<?php

declare(strict_types=1);

namespace App\ApiResource;

/**
 * Projection lecture seule d'un critère, pour afficher la « case » côté front.
 */
final class CriterionView
{
    public function __construct(
        public readonly string $code,
        public readonly string $label,
        /** 'boolean' (case à cocher) ou 'measure' (saisie chiffrée). */
        public readonly string $type,
        public readonly ?string $unit,
        /** Règle lisible (ex. « ≤ 4 °C »), null si booléen. */
        public readonly ?string $rule,
        public readonly string $severityLabel,
    ) {
    }
}
