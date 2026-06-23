<?php

declare(strict_types=1);

namespace App\Integrity;

/**
 * Graine via CSPRNG du système (random_bytes). Cryptographiquement sûre :
 * non prédictible, non reproductible. NE JAMAIS remplacer par mt_rand / un PRNG
 * seedé : ce serait anticipable, donc la triche redeviendrait possible.
 */
final class CsprngRandomnessSource implements RandomnessSource
{
    public function seed(): string
    {
        return random_bytes(32);
    }
}
