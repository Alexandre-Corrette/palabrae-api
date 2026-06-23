<?php

declare(strict_types=1);

namespace App\Enum;

/** État d'un créneau de contrôle engagé. */
enum SlotStatus: string
{
    case PLANNED = 'planned';  // engagé, pas encore honoré
    case HONORED = 'honored';  // contrôle réalisé à temps
    case MISSED = 'missed';    // non honoré à la clôture → écart prouvable
}
