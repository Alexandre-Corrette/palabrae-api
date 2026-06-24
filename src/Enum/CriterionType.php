<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Type d'un critère de contrôle (une « case » d'une fiche).
 */
enum CriterionType: string
{
    /** Vérification visuelle / présence : conforme ou non. */
    case BOOLEAN = 'boolean';

    /** Mesure chiffrée comparée à un seuil (ex. température ≤ 4 °C). */
    case MEASURE = 'measure';
}
