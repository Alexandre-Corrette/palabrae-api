<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CoachingRecord;
use App\Entity\Deviation;
use App\Enum\Severity;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Traitement SYNCHRONE d'un écart (choix MVP, cohérent avec ConfidenceScorer).
 *
 * Deux choses se jouent ici, et elles sont DISJOINTES par conception :
 *  1) la réponse SÉCURITÉ, proportionnelle à la gravité (l'avertisseur ADAS) ;
 *  2) la réponse PÉDAGOGIQUE non punitive (la couche enseignante).
 *
 * Ce qui remonte au manager est AGRÉGÉ et anonyme ; ce qui est nominatif part
 * exclusivement dans un CoachingRecord muré (finalité COACHING, TTL).
 */
final class DeviationHandler
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * @return string[] actions déclenchées (pour log/UI), sans donnée nominative
     */
    public function handle(Deviation $deviation): array
    {
        $severity = $deviation->severity();
        $actions = [];

        // 1) Escalade SÉCURITÉ proportionnelle — l'« avertisseur ».
        if ($severity->requiresHardStop()) {
            $actions[] = 'HARD_STOP'; // bloquer le lot / le service (ACUTE+)
        }
        if ($severity->notifiesManager()) {
            // Notification AGRÉGÉE : on remonte le point de contrôle et la
            // gravité, jamais l'opérateur. (En prod : push/Messenger.)
            $actions[] = 'NOTIFY_MANAGER_AGGREGATED';
        }
        if ($severity->isImmutable()) {
            $actions[] = 'IMMUTABLE_RECORD'; // boîte noire : défense litige/contrôle
        }
        if ($severity === Severity::COSMETIC) {
            $actions[] = 'NUDGE_ONLY';
        }

        // 2) Couche PÉDAGOGIQUE — non punitive, nominative mais murée.
        $operator = $deviation->getOperatorRefForCoaching();
        if ($operator !== null) {
            $lesson = $deviation->getControlPoint()->getLesson();
            $coaching = new CoachingRecord($deviation, $operator, $lesson, ttlDays: 30);
            $this->em->persist($coaching);
            $actions[] = 'COACHING_EMITTED';
            if ($lesson !== null) {
                $actions[] = 'LESSON_SERVED';
            }
        }

        $this->em->flush();

        return $actions;
    }
}
