<?php

declare(strict_types=1);

namespace App\Integrity;

/**
 * Implémentation MVP, en mémoire. SUFFISANTE POUR LE PROTOTYPE UNIQUEMENT.
 *
 * ⚠️ En production : ne pas conserver la graine en mémoire applicative ni en
 * base. Utiliser un secret manager externe (chiffrement au repos, accès audité,
 * rotation). Si la graine fuit avant la fin du créneau, le secret du timing est
 * perdu et l'anti-triche tombe.
 */
final class InMemorySealStore implements SealStore
{
    /** @var array<string, string> */
    private array $seeds = [];

    public function put(string $planRef, string $seed): void
    {
        $this->seeds[$planRef] = $seed;
    }

    public function get(string $planRef): string
    {
        if (!isset($this->seeds[$planRef])) {
            throw new \RuntimeException("Graine absente pour le plan {$planRef}.");
        }

        return $this->seeds[$planRef];
    }

    public function forget(string $planRef): void
    {
        unset($this->seeds[$planRef]);
    }
}
