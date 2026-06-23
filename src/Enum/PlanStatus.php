<?php

declare(strict_types=1);

namespace App\Enum;

/** Cycle de vie d'un plan scellé. */
enum PlanStatus: string
{
    case SEALED = 'sealed';     // engagement publié, graine secrète
    case REVEALED = 'revealed'; // créneau clos, graine révélée, vérifiable par tous
}
