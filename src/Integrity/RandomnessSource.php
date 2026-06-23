<?php

declare(strict_types=1);

namespace App\Integrity;

/**
 * Source d'aléa pour sceller un créneau. Abstraite à dessein : le MVP utilise
 * un CSPRNG local ; la version blindée branche un beacon public imprévisible
 * (type drand) pour qu'AUCUN acteur — y compris l'exploitant — ne puisse
 * choisir une graine « commode ».
 */
interface RandomnessSource
{
    /** Renvoie une graine binaire de haute entropie (>= 32 octets). */
    public function seed(): string;
}
