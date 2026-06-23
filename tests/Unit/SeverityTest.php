<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Enum\Severity;
use PHPUnit\Framework\TestCase;

/**
 * Vérifie l'escalade PROPORTIONNÉE — le cœur du produit. Un changement de seuil
 * (ex. notifier le manager dès COSMETIC) casserait la promesse non punitive.
 */
final class SeverityTest extends TestCase
{
    public function testCosmeticEstUnSimpleNudge(): void
    {
        self::assertFalse(Severity::COSMETIC->notifiesManager());
        self::assertFalse(Severity::COSMETIC->requiresHardStop());
        self::assertFalse(Severity::COSMETIC->isImmutable());
    }

    public function testSanitaryNotifieMaisNArretePas(): void
    {
        self::assertTrue(Severity::SANITARY->notifiesManager());
        self::assertFalse(Severity::SANITARY->requiresHardStop());
    }

    public function testAcuteDeclencheUnArretDur(): void
    {
        self::assertTrue(Severity::ACUTE->requiresHardStop());
        self::assertTrue(Severity::ACUTE->notifiesManager());
        self::assertFalse(Severity::ACUTE->isImmutable());
    }

    public function testCriticalEstImmuable(): void
    {
        self::assertTrue(Severity::CRITICAL->isImmutable());
        self::assertTrue(Severity::CRITICAL->requiresHardStop());
        self::assertTrue(Severity::CRITICAL->notifiesManager());
    }

    public function testGradientMonotone(): void
    {
        self::assertSame([1, 2, 3, 4], array_map(
            static fn (Severity $s): int => $s->value,
            [Severity::COSMETIC, Severity::SANITARY, Severity::ACUTE, Severity::CRITICAL],
        ));
    }
}
