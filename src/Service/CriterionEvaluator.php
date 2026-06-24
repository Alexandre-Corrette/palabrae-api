<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ControlCriterion;
use App\Enum\CriterionType;

/**
 * Applique la règle métier d'un critère à la réponse de l'opérateur.
 *
 * Booléen : conforme = réponse « true » (case cochée OK).
 * Mesure   : conforme = la valeur satisfait « valeur <comparateur> seuil »
 *            (ex. température ≤ 4 °C). Une mesure manquante = non conforme.
 */
final class CriterionEvaluator
{
    public function isConform(ControlCriterion $criterion, bool|int|float|null $answer): bool
    {
        if ($criterion->getType() === CriterionType::BOOLEAN) {
            return $answer === true;
        }

        // MEASURE
        if (!is_int($answer) && !is_float($answer)) {
            return false; // valeur non fournie ou non numérique → non conforme
        }
        $comparator = $criterion->getComparator();
        $threshold = $criterion->getThreshold();
        if ($comparator === null || $threshold === null) {
            return true; // mesure sans règle → on ne bloque pas
        }

        return $comparator->satisfies((float) $answer, $threshold);
    }
}
