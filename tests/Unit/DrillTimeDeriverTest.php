<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\SpotCheck\ControlHours;
use App\SpotCheck\DrillTimeDeriver;
use PHPUnit\Framework\TestCase;

/**
 * Garantie centrale de l'objection « coup de feu » : sur un grand échantillon
 * de graines, AUCUN horaire dérivé ne tombe dans une plage interdite.
 */
final class DrillTimeDeriverTest extends TestCase
{
    public function testAucunHoraireDansUnePlageInterdite(): void
    {
        $hours = ControlHours::fromDay('08:00', '22:00', ['12:00-14:00', '19:00-21:00']);
        $deriver = new DrillTimeDeriver();
        $day = new \DateTimeImmutable('2026-06-24');

        for ($s = 0; $s < 400; $s++) {
            $seed = random_bytes(32);
            foreach ($deriver->derive($seed, 4, $day, $hours) as $t) {
                $minute = (int) $t->format('H') * 60 + (int) $t->format('i');
                self::assertTrue(
                    $hours->isAllowed($minute),
                    sprintf('Horaire %s hors plage autorisée (graine %s).', $t->format('H:i'), bin2hex($seed)),
                );
                // Explicite : jamais entre 12:00 et 14:00.
                self::assertFalse($minute >= 720 && $minute < 840, 'Horaire pendant le service du midi.');
            }
        }
    }

    public function testDeterministe(): void
    {
        $hours = ControlHours::fromDay('08:00', '22:00', ['12:00-14:00']);
        $deriver = new DrillTimeDeriver();
        $day = new \DateTimeImmutable('2026-06-24');
        $seed = random_bytes(32);

        $a = array_map(static fn ($t) => $t->format('H:i'), $deriver->derive($seed, 3, $day, $hours));
        $b = array_map(static fn ($t) => $t->format('H:i'), $deriver->derive($seed, 3, $day, $hours));

        self::assertSame($a, $b);
    }

    public function testNombreDHorairesRespecte(): void
    {
        $hours = ControlHours::fromDay('08:00', '22:00', ['12:00-14:00']);
        $times = (new DrillTimeDeriver())->derive(random_bytes(32), 5, new \DateTimeImmutable('2026-06-24'), $hours);

        self::assertCount(5, $times);
    }

    public function testHorairesTries(): void
    {
        $hours = ControlHours::fromDay('08:00', '22:00', ['12:00-14:00']);
        $times = (new DrillTimeDeriver())->derive(random_bytes(32), 6, new \DateTimeImmutable('2026-06-24'), $hours);

        $sorted = $times;
        usort($sorted, static fn (\DateTimeImmutable $a, \DateTimeImmutable $b): int => $a <=> $b);
        self::assertEquals($sorted, $times);
    }
}
