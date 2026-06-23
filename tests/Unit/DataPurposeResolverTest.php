<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Enum\DataPurpose;
use App\Security\DataPurposeResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Vérifie que la finalité d'accès dépend UNIQUEMENT du contexte serveur (la
 * route) et jamais d'une donnée fournie par le client.
 */
final class DataPurposeResolverTest extends TestCase
{
    private DataPurposeResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new DataPurposeResolver();
    }

    public function testEspaceCoachingDonneFinaliteCoaching(): void
    {
        self::assertSame(
            DataPurpose::COACHING,
            $this->resolver->resolve(Request::create('/api/coaching/records/42')),
        );
    }

    public function testEspaceComplianceDonneFinaliteCompliance(): void
    {
        self::assertSame(
            DataPurpose::COMPLIANCE,
            $this->resolver->resolve(Request::create('/api/compliance/summary')),
        );
    }

    public function testCheminQuelconqueNaAucuneFinalite(): void
    {
        self::assertNull($this->resolver->resolve(Request::create('/api/deviations')));
    }

    public function testUnParametreClientNePeutPasForcerLaFinalite(): void
    {
        // Le client tente d'injecter la finalité par la query/headers/body :
        // le resolver ne regarde QUE le chemin → aucune élévation possible.
        $forged = Request::create(
            '/api/deviations?data_purpose=coaching',
            'GET',
            ['data_purpose' => 'coaching'],
            [],
            [],
            ['HTTP_X_DATA_PURPOSE' => 'coaching'],
        );

        self::assertNull($this->resolver->resolve($forged));
    }

    public function testDisciplinaryNestJamaisDerivable(): void
    {
        // Aucun chemin de cette application ne produit DISCIPLINARY.
        foreach (['/api/disciplinary', '/api/disciplinary/cases/1', '/api/coaching/disciplinary'] as $path) {
            self::assertNotSame(DataPurpose::DISCIPLINARY, $this->resolver->resolve(Request::create($path)));
        }
    }
}
