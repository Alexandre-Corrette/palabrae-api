<?php

declare(strict_types=1);

namespace App\Integrity;

/**
 * Ancrage externe de la tête du journal. Une chaîne de hash interne se prouve
 * elle-même MAIS peut être entièrement recalculée par qui contrôle la base. Il
 * faut donc déposer périodiquement la tête de chaîne hors de portée de
 * l'exploitant : horodatage tiers (RFC 3161), e-mail à un auditeur, dépôt public.
 *
 * Chaîne interne + ancrage externe = on détecte l'édition ponctuelle ET la
 * réécriture en bloc.
 */
interface AnchorSink
{
    public function anchor(string $headHash, \DateTimeImmutable $at): void;
}
