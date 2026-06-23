<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\DataPurpose;
use Doctrine\ORM\Mapping as ORM;

/**
 * Trace d'accompagnement individuel. Finalité FIXÉE à COACHING.
 *
 * Garanties structurelles de la promesse « sans douleur » :
 *  - purpose immuable = COACHING (jamais réaffectable à du disciplinaire) ;
 *  - purgeAfter = TTL court (minimisation RGPD : la donnée nominative ne
 *    s'accumule pas en dossier à charge) ;
 *  - lecture gardée par CoachingDataVoter.
 */
#[ORM\Entity]
#[ORM\Table(name: 'coaching_record')]
class CoachingRecord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Deviation $deviation;

    #[ORM\Column(length: 64)]
    private string $operatorRef;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?MicroLesson $lessonServed = null;

    #[ORM\Column]
    private bool $acknowledged = false;

    #[ORM\Column(enumType: DataPurpose::class)]
    private DataPurpose $purpose = DataPurpose::COACHING; // verrouillé

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** Date de purge automatique (minimisation). */
    #[ORM\Column]
    private \DateTimeImmutable $purgeAfter;

    public function __construct(Deviation $deviation, string $operatorRef, ?MicroLesson $lessonServed, int $ttlDays = 30)
    {
        $this->deviation = $deviation;
        $this->operatorRef = $operatorRef;
        $this->lessonServed = $lessonServed;
        $this->createdAt = new \DateTimeImmutable();
        $this->purgeAfter = $this->createdAt->modify("+{$ttlDays} days");
    }

    public function acknowledge(): void { $this->acknowledged = true; }

    public function isExpired(?\DateTimeImmutable $now = null): bool
    {
        return ($now ?? new \DateTimeImmutable()) >= $this->purgeAfter;
    }

    public function getId(): ?int { return $this->id; }
    public function getDeviation(): Deviation { return $this->deviation; }
    public function getOperatorRef(): string { return $this->operatorRef; }
    public function getLessonServed(): ?MicroLesson { return $this->lessonServed; }
    public function isAcknowledged(): bool { return $this->acknowledged; }
    public function getPurpose(): DataPurpose { return $this->purpose; }
    public function getPurgeAfter(): \DateTimeImmutable { return $this->purgeAfter; }
}
