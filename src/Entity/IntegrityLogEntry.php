<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Maillon du journal d'intégrité (boîte noire). Chaque entrée contient
 * l'empreinte de la précédente : modifier une ligne passée casse toute la
 * chaîne en aval.
 *
 * SÉCU : append-only. Aucun setter, aucune mise à jour, aucune suppression.
 * Idéalement renforcé en base (révocation des privilèges UPDATE/DELETE sur la
 * table pour le rôle applicatif). L'entryHash est recalculé au constructeur
 * pour qu'un maillon ne puisse pas être fabriqué incohérent.
 */
#[ORM\Entity]
#[ORM\Table(name: 'integrity_log')]
class IntegrityLogEntry
{
    public const GENESIS = 'GENESIS';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(unique: true)]
    private int $seq;

    #[ORM\Column]
    private \DateTimeImmutable $recordedAt;

    #[ORM\Column(length: 48)]
    private string $type;

    /** Hash du payload canonicalisé (le contenu lui-même peut vivre ailleurs). */
    #[ORM\Column(length: 64)]
    private string $payloadHash;

    #[ORM\Column(length: 64)]
    private string $prevHash;

    #[ORM\Column(length: 64)]
    private string $entryHash;

    public function __construct(int $seq, string $type, string $payloadHash, string $prevHash)
    {
        $this->seq = $seq;
        $this->type = $type;
        $this->payloadHash = $payloadHash;
        $this->prevHash = $prevHash;
        $this->recordedAt = new \DateTimeImmutable();
        $this->entryHash = self::computeHash($seq, $this->recordedAt, $type, $payloadHash, $prevHash);
    }

    public static function computeHash(int $seq, \DateTimeImmutable $at, string $type, string $payloadHash, string $prevHash): string
    {
        return hash('sha256', $seq . '|' . $at->format('Y-m-d\TH:i:s.uP') . '|' . $type . '|' . $payloadHash . '|' . $prevHash);
    }

    public function getId(): ?int { return $this->id; }
    public function getSeq(): int { return $this->seq; }
    public function getRecordedAt(): \DateTimeImmutable { return $this->recordedAt; }
    public function getType(): string { return $this->type; }
    public function getPayloadHash(): string { return $this->payloadHash; }
    public function getPrevHash(): string { return $this->prevHash; }
    public function getEntryHash(): string { return $this->entryHash; }
}
