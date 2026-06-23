<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\IntegrityLogEntry;
use App\Integrity\AnchorSink;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Journal d'intégrité — la boîte noire. Append-only, chaîné par hash.
 *
 * Garantit deux choses : (1) on ne peut pas éditer une ligne passée sans casser
 * la chaîne en aval ; (2) avec l'ancrage externe, on ne peut pas non plus
 * réécrire tout le journal en bloc sans que la tête ancrée ne le trahisse.
 */
final class IntegrityJournal
{
    /**
     * Clé du verrou consultatif PostgreSQL pour la section critique d'append.
     * Valeur arbitraire mais STABLE : tous les processus doivent partager la
     * même clé pour se sérialiser entre eux. ('PLBR' = 0x504C4252.)
     */
    private const APPEND_LOCK_KEY = 0x504C4252;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AnchorSink $anchorSink,
    ) {
    }

    /**
     * Ajoute un maillon. Le payload est canonicalisé (clés triées) avant hash
     * pour que la même donnée produise toujours la même empreinte.
     *
     * CONCURRENCE : la lecture de la tête (seq max) puis l'insertion forment une
     * section critique. Sans sérialisation, deux append simultanés (PHP-FPM,
     * workers Messenger) liraient la même tête → collision sur `seq` unique ou
     * fourche de chaîne. On prend donc un verrou consultatif TRANSACTIONNEL
     * (pg_advisory_xact_lock) au début d'une transaction englobante : les autres
     * append attendent, et le verrou est libéré automatiquement à la fin de la
     * transaction (commit ou rollback). Spécifique PostgreSQL.
     *
     * @param array<string, mixed> $payload
     */
    public function append(string $type, array $payload): IntegrityLogEntry
    {
        $entry = $this->em->wrapInTransaction(function () use ($type, $payload): IntegrityLogEntry {
            // Sérialise tous les append entre eux. Bloque jusqu'à obtention.
            $this->em->getConnection()->executeStatement(
                'SELECT pg_advisory_xact_lock(?)',
                [self::APPEND_LOCK_KEY],
                [ParameterType::INTEGER],
            );

            // Lecture de la tête APRÈS acquisition du verrou : en READ COMMITTED,
            // on voit le maillon de l'append concurrent qui vient de committer.
            $last = $this->em->getRepository(IntegrityLogEntry::class)->findOneBy([], ['seq' => 'DESC']);

            $seq = $last ? $last->getSeq() + 1 : 1;
            $prevHash = $last ? $last->getEntryHash() : IntegrityLogEntry::GENESIS;
            $payloadHash = hash('sha256', $this->canonical($payload));

            $entry = new IntegrityLogEntry($seq, $type, $payloadHash, $prevHash);
            $this->em->persist($entry);
            $this->em->flush();

            return $entry;
        });

        \assert($entry instanceof IntegrityLogEntry);

        return $entry;
    }

    public function head(): string
    {
        $last = $this->em->getRepository(IntegrityLogEntry::class)->findOneBy([], ['seq' => 'DESC']);

        return $last ? $last->getEntryHash() : IntegrityLogEntry::GENESIS;
    }

    /**
     * Rejoue la chaîne et retourne les seq des maillons rompus (vide = intègre).
     *
     * @return int[]
     */
    public function verifyChain(): array
    {
        $entries = $this->em->getRepository(IntegrityLogEntry::class)->findBy([], ['seq' => 'ASC']);
        $breaks = [];
        $prev = IntegrityLogEntry::GENESIS;

        foreach ($entries as $e) {
            $expected = IntegrityLogEntry::computeHash($e->getSeq(), $e->getRecordedAt(), $e->getType(), $e->getPayloadHash(), $e->getPrevHash());
            // hash_equals : comparaison à temps constant (hygiène anti-oracle).
            if (!hash_equals($expected, $e->getEntryHash()) || !hash_equals($prev, $e->getPrevHash())) {
                $breaks[] = $e->getSeq();
            }
            $prev = $e->getEntryHash();
        }

        return $breaks;
    }

    /** Dépose la tête de chaîne hors de portée de l'exploitant (RFC 3161, e-mail auditeur…). */
    public function anchor(): void
    {
        $this->anchorSink->anchor($this->head(), new \DateTimeImmutable());
    }

    /** @param array<string, mixed> $payload */
    private function canonical(array $payload): string
    {
        ksort($payload);

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
