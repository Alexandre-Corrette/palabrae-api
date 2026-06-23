<?php

declare(strict_types=1);

namespace App\Integrity;

/**
 * Coffre où vit la graine PENDANT le créneau. Point crucial : la base de
 * données ne contient QUE le commitment (le hash), jamais la graine ni les
 * horaires. Sans pré-image en base, personne — même avec un accès lecture à la
 * DB — ne peut anticiper quand tombera un contrôle.
 *
 * En prod : secret manager (Vault, KMS, Secrets Manager), pas la DB applicative.
 */
interface SealStore
{
    public function put(string $planRef, string $seed): void;

    /** @return string graine binaire. Lève si absente. */
    public function get(string $planRef): string;

    public function forget(string $planRef): void;
}
