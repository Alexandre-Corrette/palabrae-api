<?php

declare(strict_types=1);

namespace App\SpotCheck;

/**
 * Dérive les horaires d'un exercice à partir de la graine, TOUS dans les plages
 * autorisées du site (donc hors service).
 *
 * Le hasard vit dans la graine (CSPRNG, imprévisible) ; la dérivation est
 * déterministe (donc vérifiable publiquement après révélation). Comme les
 * horaires se recalculent à partir de la graine, ils n'ont jamais besoin d'être
 * stockés en base tant que le plan est SEALED : l'anti-triche du commit-reveal
 * reste intact.
 */
final class DrillTimeDeriver
{
    /**
     * @return list<\DateTimeImmutable> horaires triés, tous hors plages interdites
     */
    public function derive(string $seed, int $count, \DateTimeImmutable $day, ControlHours $hours): array
    {
        $total = $hours->totalMinutes();
        if ($total <= 0) {
            throw new \InvalidArgumentException('Aucune plage autorisée pour ce site.');
        }

        $midnight = $day->setTime(0, 0, 0);
        $times = [];
        for ($i = 0; $i < $count; $i++) {
            $h = hash('sha256', $seed . ':drill:' . $i, true);
            $n = unpack('J', substr($h, 0, 8))[1] & 0x7FFFFFFFFFFFFFFF;
            $minute = $hours->minuteAt($n % $total);
            $times[] = $midnight->modify(sprintf('+%d minutes', $minute));
        }

        usort($times, static fn (\DateTimeImmutable $a, \DateTimeImmutable $b): int => $a <=> $b);

        return $times;
    }
}
