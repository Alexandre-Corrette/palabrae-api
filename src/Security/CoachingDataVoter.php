<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\CoachingRecord;
use App\Entity\Deviation;
use App\Enum\DataPurpose;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Rend la promesse « sans douleur » STRUCTURELLE plutôt que déclarative.
 *
 * La donnée d'erreur nominative (operatorRef d'une Deviation, contenu d'un
 * CoachingRecord) n'est lisible QUE si la finalité d'accès courante est
 * COACHING. Toute finalité COMPLIANCE ou DISCIPLINARY est refusée — donc on
 * ne peut PAS, par construction, reconstituer un dossier à charge depuis le
 * coaching.
 *
 * La finalité provient EXCLUSIVEMENT du contexte serveur : elle est posée sur
 * le jeton par DataPurposeContextListener (seul écrivain), à partir de
 * DataPurposeResolver (la route). Jamais lue d'un claim JWT ou d'une donnée
 * cliente — toute valeur cliente est écrasée à chaque requête.
 *
 * @extends Voter<string, Deviation|CoachingRecord>
 */
final class CoachingDataVoter extends Voter
{
    public const VIEW_COACHING_DATA = 'VIEW_COACHING_DATA';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::VIEW_COACHING_DATA
            && ($subject instanceof Deviation || $subject instanceof CoachingRecord);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $purpose = $this->resolvePurpose($token);

        // Règle unique et non contournable : seul COACHING lit le nominatif.
        if (!$purpose?->canReadIndividualCoachingData()) {
            return false;
        }

        // Donnée de coaching expirée (TTL dépassé) = non lisible (minimisation).
        if ($subject instanceof CoachingRecord && $subject->isExpired()) {
            return false;
        }

        return true;
    }

    private function resolvePurpose(TokenInterface $token): ?DataPurpose
    {
        // Le contexte d'accès est posé explicitement (jamais inféré).
        $attributes = method_exists($token, 'getAttributes') ? $token->getAttributes() : [];
        $raw = $attributes['data_purpose'] ?? null;

        return $raw instanceof DataPurpose ? $raw : null;
    }
}
