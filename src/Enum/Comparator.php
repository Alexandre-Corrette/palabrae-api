<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Comparateur d'une règle de mesure. C'est le cœur de la « règle métier » :
 * la fiche papier dit « ≤ 4 °C », on l'encode ici de façon exécutable.
 */
enum Comparator: string
{
    case LESS_OR_EQUAL = '<=';
    case LESS = '<';
    case GREATER_OR_EQUAL = '>=';
    case GREATER = '>';
    case EQUAL = '==';

    /** La valeur mesurée satisfait-elle la règle « valeur <comparateur> seuil » ? */
    public function satisfies(float $value, float $threshold): bool
    {
        return match ($this) {
            self::LESS_OR_EQUAL => $value <= $threshold,
            self::LESS => $value < $threshold,
            self::GREATER_OR_EQUAL => $value >= $threshold,
            self::GREATER => $value > $threshold,
            self::EQUAL => abs($value - $threshold) < 1e-9,
        };
    }

    /** Libellé lisible pour l'écran (ex. « ≤ 4 °C »). */
    public function label(): string
    {
        return match ($this) {
            self::LESS_OR_EQUAL => '≤',
            self::LESS => '<',
            self::GREATER_OR_EQUAL => '≥',
            self::GREATER => '>',
            self::EQUAL => '=',
        };
    }
}
