<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\SpotCheck\ControlHoursResolver;
use PHPUnit\Framework\TestCase;

/**
 * Vérifie la résolution des plages PAR SITE, avec repli sur _default.
 */
final class ControlHoursResolverTest extends TestCase
{
    private function resolver(): ControlHoursResolver
    {
        return new ControlHoursResolver([
            '_default' => ['day' => ['08:00', '22:00'], 'forbidden' => ['12:00-14:00']],
            'SITE-A' => ['day' => ['07:00', '19:00'], 'forbidden' => ['11:30-14:30']],
        ]);
    }

    public function testSiteConnuUtiliseSesPlages(): void
    {
        $hours = $this->resolver()->forSite('SITE-A');

        // 11:45 est interdit pour SITE-A (11:30-14:30) mais autorisé par défaut.
        self::assertFalse($hours->isAllowed(11 * 60 + 45));
        self::assertTrue($hours->isAllowed(7 * 60 + 30));
    }

    public function testSiteInconnuTombeSurDefault(): void
    {
        $hours = $this->resolver()->forSite('SITE-INCONNU');

        // Défaut : interdit 12-14, autorisé à 11:45.
        self::assertTrue($hours->isAllowed(11 * 60 + 45));
        self::assertFalse($hours->isAllowed(13 * 60));
    }

    public function testSansDefaultNiSiteLeve(): void
    {
        $resolver = new ControlHoursResolver([]);

        $this->expectException(\RuntimeException::class);
        $resolver->forSite('SITE-A');
    }
}
