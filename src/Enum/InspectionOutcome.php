<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Résultat d'une inspection d'un point de contrôle.
 * Repris des statuts du prototype (ok / flagged).
 */
enum InspectionOutcome: string
{
    /** Le point est conforme — le geste a été bien fait. */
    case CONFORM = 'conform';

    /** Un écart a été constaté (le détail neutre vit dans Deviation). */
    case DEVIATION = 'deviation';
}
