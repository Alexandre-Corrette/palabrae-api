<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\SealedPlanner;
use PHPUnit\Framework\TestCase;

/**
 * Vérifie la propriété centrale de défendabilité : avec la graine révélée,
 * n'importe quel tiers (auditeur, DDPP) recalcule le commitment et constate que
 * le plan de contrôle était fixé À L'AVANCE — sans accès au système.
 */
final class SealedPlannerVerifyTest extends TestCase
{
    /** Doit correspondre à SealedPlanner::DOMAIN (contrat de vérification publique). */
    private const DOMAIN = 'aplomb.spotcheck.v1';

    public function testCommitmentValideEstAccepte(): void
    {
        $windowRef = 'cantine:2026-06-23:midi';
        $count = 3;
        $seed = random_bytes(32);
        $seedHex = bin2hex($seed);

        $commitment = hash('sha256', self::DOMAIN.'|'.$windowRef.'|'.$count.'|'.$seedHex);

        self::assertTrue(
            SealedPlanner::verifyPublic($windowRef, $count, $seedHex, $commitment),
            'Un commitment honnête doit être vérifiable publiquement.',
        );
    }

    public function testCommitmentFalsifieEstRejete(): void
    {
        $windowRef = 'cantine:2026-06-23:midi';
        $count = 3;
        $seedHex = bin2hex(random_bytes(32));
        $commitment = hash('sha256', self::DOMAIN.'|'.$windowRef.'|'.$count.'|'.$seedHex);

        // On change le nombre de contrôles après coup : le commitment ne tient plus.
        self::assertFalse(
            SealedPlanner::verifyPublic($windowRef, 5, $seedHex, $commitment),
            'Modifier le nombre de contrôles a posteriori doit casser la vérification.',
        );
    }

    public function testGraineQuiNeConcordePasEstRejetee(): void
    {
        // Graine hexa valide mais sans rapport avec le commitment fourni.
        $seedHex = bin2hex(random_bytes(32));
        self::assertFalse(
            SealedPlanner::verifyPublic('w', 1, $seedHex, str_repeat('0', 64)),
        );
    }
}
