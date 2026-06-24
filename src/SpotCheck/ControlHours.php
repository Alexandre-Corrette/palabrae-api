<?php

declare(strict_types=1);

namespace App\SpotCheck;

/**
 * Plages horaires autorisées pour les exercices, en minutes depuis minuit.
 *
 * Construite en retranchant les plages INTERDITES (le service / coup de feu)
 * d'une amplitude de journée. Les exercices ne peuvent donc tomber que HORS
 * service : aléatoires, mais jamais pendant le rush. C'est ce qui lève
 * l'objection « et si ça tombe en plein service ? ».
 */
final class ControlHours
{
    /** @var list<array{0:int,1:int}> intervalles [start,end) en minutes, triés et disjoints */
    private array $allowed;

    /** @param list<array{0:int,1:int}> $allowed */
    private function __construct(array $allowed)
    {
        $this->allowed = array_values($allowed);
    }

    /**
     * @param list<string> $forbidden plages « HH:MM-HH:MM » à exclure (le service)
     */
    public static function fromDay(string $dayStart, string $dayEnd, array $forbidden): self
    {
        $start = self::clock($dayStart);
        $end = self::clock($dayEnd);
        if ($end <= $start) {
            throw new \InvalidArgumentException('La fin de journée doit suivre le début.');
        }

        $intervals = [[$start, $end]];
        foreach ($forbidden as $f) {
            [$fs, $fe] = self::range($f);
            $intervals = self::subtract($intervals, $fs, $fe);
        }
        if ($intervals === []) {
            throw new \InvalidArgumentException('Aucune plage autorisée après exclusion des interdits.');
        }

        return new self($intervals);
    }

    /** @return list<array{0:int,1:int}> */
    public function allowedIntervals(): array
    {
        return $this->allowed;
    }

    public function totalMinutes(): int
    {
        $t = 0;
        foreach ($this->allowed as [$s, $e]) {
            $t += $e - $s;
        }

        return $t;
    }

    /** Minute-de-journée correspondant au i-ème pas dans l'espace autorisé. */
    public function minuteAt(int $index): int
    {
        $index %= max(1, $this->totalMinutes());
        foreach ($this->allowed as [$s, $e]) {
            $len = $e - $s;
            if ($index < $len) {
                return $s + $index;
            }
            $index -= $len;
        }

        // Inatteignable (index borné par totalMinutes), mais garde un retour sûr.
        return $this->allowed[0][0];
    }

    public function isAllowed(int $minuteOfDay): bool
    {
        foreach ($this->allowed as [$s, $e]) {
            if ($minuteOfDay >= $s && $minuteOfDay < $e) {
                return true;
            }
        }

        return false;
    }

    private static function clock(string $hhmm): int
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', trim($hhmm), $m)) {
            throw new \InvalidArgumentException(sprintf('Heure invalide : "%s".', $hhmm));
        }
        $h = (int) $m[1];
        $min = (int) $m[2];
        if ($h > 23 || $min > 59) {
            throw new \InvalidArgumentException(sprintf('Heure hors bornes : "%s".', $hhmm));
        }

        return $h * 60 + $min;
    }

    /** @return array{0:int,1:int} */
    private static function range(string $r): array
    {
        $parts = explode('-', $r);
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException(sprintf('Plage invalide : "%s".', $r));
        }
        $s = self::clock($parts[0]);
        $e = self::clock($parts[1]);
        if ($e <= $s) {
            throw new \InvalidArgumentException(sprintf('Plage vide ou inversée : "%s".', $r));
        }

        return [$s, $e];
    }

    /**
     * @param list<array{0:int,1:int}> $intervals
     * @return list<array{0:int,1:int}>
     */
    private static function subtract(array $intervals, int $fs, int $fe): array
    {
        $out = [];
        foreach ($intervals as [$s, $e]) {
            if ($fe <= $s || $fs >= $e) {
                $out[] = [$s, $e];
                continue;
            }
            if ($s < $fs) {
                $out[] = [$s, $fs];
            }
            if ($fe < $e) {
                $out[] = [$fe, $e];
            }
        }

        return array_values($out);
    }
}
