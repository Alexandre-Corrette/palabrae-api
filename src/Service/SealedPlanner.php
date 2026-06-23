<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\SpotCheckPlan;
use App\Entity\SpotCheckSlot;
use App\Enum\SlotStatus;
use App\Integrity\RandomnessSource;
use App\Integrity\SealStore;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Cœur de la défendabilité. Implémente le schéma commit-reveal :
 *
 *  seal()   — à l'ouverture du créneau : tire une graine CSPRNG, en dérive un
 *             nombre et des horaires de contrôle, publie SEULEMENT le commitment
 *             (hash). La graine part au coffre (SealStore), pas en base.
 *  reveal() — à la clôture : révèle la graine, re-vérifie le commitment, et
 *             journalise tout. Dès lors, n'importe qui peut recalculer et
 *             constater que le plan était fixé à l'avance.
 *  close()  — sweep : tout contrôle engagé non honoré devient MISSED. Comme le
 *             nombre était scellé, l'absence est PROUVABLE — un oubli ne peut
 *             pas être caché.
 *
 * Le hasard vit dans la graine (imprévisible) ; la dérivation des horaires est
 * déterministe (donc vérifiable). Personne ne tient de bouton : c'est le
 * Scheduler/cron qui appelle seal() à l'ouverture, sans qu'aucun humain ne
 * connaisse les horaires.
 */
final class SealedPlanner
{
    private const DOMAIN = 'aplomb.spotcheck.v1';

    public function __construct(
        private readonly RandomnessSource $rng,
        private readonly SealStore $sealStore,
        private readonly IntegrityJournal $journal,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function seal(string $windowRef, string $siteRef, int $minK, int $maxK, int $windowSeconds): SpotCheckPlan
    {
        $seed = $this->rng->seed();
        $count = random_int($minK, $maxK);                 // cible variable, pas un nombre fixe
        $commitment = $this->commit($windowRef, $count, $seed);

        $plan = new SpotCheckPlan($windowRef, $siteRef, $count, $commitment);
        for ($i = 0; $i < $count; $i++) {
            new SpotCheckSlot($plan, $i);                  // s'auto-rattache au plan
        }
        $this->em->persist($plan);
        $this->em->flush();

        // La graine et les horaires ne touchent JAMAIS la base tant que SEALED.
        $this->sealStore->put($windowRef, $seed);

        // Engagement journalisé : preuve horodatée que le plan est fixé maintenant.
        $this->journal->append('PLAN_SEALED', [
            'window' => $windowRef, 'site' => $siteRef, 'count' => $count, 'commitment' => $commitment,
        ]);

        return $plan;
    }

    /**
     * Horaires de contrôle dérivés de façon déterministe de la graine.
     * CSPRNG pour l'imprévisibilité, dérivation reproductible pour la preuve.
     *
     * @return int[] offsets (secondes depuis l'ouverture), triés
     */
    public function deriveOffsets(string $seed, int $count, int $windowSeconds): array
    {
        $offsets = [];
        for ($i = 0; $i < $count; $i++) {
            $h = hash('sha256', $seed . ':' . $i, true);
            $n = unpack('J', substr($h, 0, 8))[1] & 0x7FFFFFFFFFFFFFFF;
            $offsets[] = $n % max(1, $windowSeconds);
        }
        sort($offsets);

        return $offsets;
    }

    public function reveal(SpotCheckPlan $plan, bool $windowClosed): void
    {
        if (!$windowClosed) {
            // Révéler avant la clôture détruirait le secret du timing.
            throw new \LogicException('Révélation interdite avant la fin du créneau.');
        }

        $seed = $this->sealStore->get($plan->getWindowRef());
        $recomputed = $this->commit($plan->getWindowRef(), $plan->getCount(), $seed);

        if (!hash_equals($plan->getCommitment(), $recomputed)) {
            // Le plan en base ne correspond plus à l'engagement → alarme.
            $this->journal->append('INTEGRITY_ALARM', [
                'window' => $plan->getWindowRef(), 'reason' => 'commitment_mismatch',
            ]);
            throw new \RuntimeException('Commitment non vérifié : plan altéré.');
        }

        $plan->reveal(bin2hex($seed));
        $this->em->flush();
        $this->sealStore->forget($plan->getWindowRef());

        $this->journal->append('PLAN_REVEALED', [
            'window' => $plan->getWindowRef(),
            'seed' => bin2hex($seed),
            'count' => $plan->getCount(),
        ]);
    }

    /**
     * Vérification publique : avec la graine révélée, recalcule le commitment.
     * Toute partie (auditeur, DDPP) peut l'exécuter sans accès au système.
     */
    public static function verifyPublic(string $windowRef, int $count, string $seedHex, string $commitment): bool
    {
        $seed = hex2bin($seedHex);

        return $seed !== false && hash_equals($commitment, hash('sha256', self::DOMAIN . '|' . $windowRef . '|' . $count . '|' . bin2hex($seed)));
    }

    /**
     * Sweep de clôture : les contrôles engagés non honorés deviennent MISSED et
     * sont journalisés. L'attente ayant été scellée, chaque manque est prouvable.
     *
     * @return array{committed:int, honored:int, missed:int}
     */
    public function close(SpotCheckPlan $plan): array
    {
        $honored = 0;
        $missed = 0;

        foreach ($plan->getSlots() as $slot) {
            if ($slot->getStatus() === SlotStatus::HONORED) {
                $honored++;
                continue;
            }
            $slot->miss();
            $missed++;
            // Manqué = écart d'intégrité (remonte en agrégé, sans nominatif).
            $this->journal->append('CONTROL_MISSED', [
                'window' => $plan->getWindowRef(), 'ordinal' => $slot->getOrdinal(),
            ]);
        }
        $this->em->flush();

        return ['committed' => $plan->getCount(), 'honored' => $honored, 'missed' => $missed];
    }

    private function commit(string $windowRef, int $count, string $seed): string
    {
        return hash('sha256', self::DOMAIN . '|' . $windowRef . '|' . $count . '|' . bin2hex($seed));
    }
}
