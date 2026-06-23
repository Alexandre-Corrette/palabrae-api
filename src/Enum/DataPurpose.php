<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Finalité d'accès à la donnée (RGPD art. 5.1.b — limitation des finalités).
 *
 * C'est l'ossature de la promesse « sans douleur » : la donnée d'erreur
 * individuelle est COACHING par défaut. Elle n'est JAMAIS lisible à des fins
 * DISCIPLINARY. Le manager n'a accès qu'à du COMPLIANCE agrégé (sans
 * attribution nominative).
 *
 * Rendre cette règle structurelle (enum + Voter) plutôt qu'une charte = la
 * seule façon que la main-d'œuvre volatile y croie et ne contourne pas l'outil.
 */
enum DataPurpose: string
{
    case COACHING = 'coaching';       // accompagnement individuel, non punitif
    case COMPLIANCE = 'compliance';   // pilotage agrégé, anonymisé
    case DISCIPLINARY = 'disciplinary'; // RH/sanction — JAMAIS nourri par le coaching

    /** Seule la finalité COACHING peut lire la donnée d'erreur nominative. */
    public function canReadIndividualCoachingData(): bool
    {
        return $this === self::COACHING;
    }
}
