<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Origine d'une inspection. Détermine la « culture juste » : une détection
 * automatique (capteur IoT) est un événement équipement et ne met aucun
 * opérateur en cause.
 */
enum InspectionSource: string
{
    /** L'opérateur déclare lui-même son contrôle. */
    case DECLARED = 'declared';

    /** Contrôle surprise : employé + point tirés au sort, par aucun humain. */
    case SPOT = 'spot';

    /** Capteur IoT : événement équipement, sans intervention humaine. */
    case IOT = 'iot';

    /** Une source automatique ne désigne pas de fautif. */
    public function attributesToOperator(): bool
    {
        return $this === self::DECLARED || $this === self::SPOT;
    }
}
