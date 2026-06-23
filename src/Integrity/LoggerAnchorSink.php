<?php

declare(strict_types=1);

namespace App\Integrity;

use Psr\Log\LoggerInterface;

/**
 * Implémentation MVP de l'ancrage externe : on journalise la tête de chaîne.
 *
 * ⚠️ INSUFFISANT EN PRODUCTION. Un log local reste sous le contrôle de
 * l'exploitant — exactement ce dont l'ancrage doit nous protéger. En prod,
 * remplacer par un vrai puits HORS de portée de l'exploitant :
 *   - horodatage tiers RFC 3161 (TSA) ;
 *   - e-mail signé à un auditeur externe ;
 *   - publication sur un registre public / OpenTimestamps.
 *
 * Le but : pouvoir détecter une réécriture EN BLOC du journal (que la chaîne
 * interne seule ne détecte pas, puisque qui contrôle la base peut tout recalculer).
 */
final class LoggerAnchorSink implements AnchorSink
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function anchor(string $headHash, \DateTimeImmutable $at): void
    {
        $this->logger->info('integrity.anchor', [
            'head'      => $headHash,
            'anchoredAt' => $at->format(\DateTimeInterface::ATOM),
        ]);
    }
}
