<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use ApiPlatform\Metadata\Post;
use App\ApiResource\InspectionReport;
use App\Entity\ControlPoint;
use App\Entity\Investigation;
use App\Entity\User;
use App\Enum\InspectionOutcome;
use App\Enum\InspectionSource;
use App\Enum\Severity;
use App\State\InspectionReportProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Vérifie le parcours « Tout est conforme » : résultat CONFORME, source
 * DÉCLARÉE, operatorRef issu du contexte, sortie sans champ nominatif.
 */
final class InspectionReportProcessorTest extends TestCase
{
    public function testMarqueLePointConforme(): void
    {
        $out = $this->processor('CCP-FROID-01', operatorRef: 'MAT-0001')
            ->process($this->input('CCP-FROID-01'), new Post());

        self::assertSame(InspectionOutcome::CONFORM, $out->outcome);
        self::assertSame(InspectionSource::DECLARED, $out->source);
        self::assertNotNull($out->recordedAt);
    }

    public function testSortieNeContientAucunChampNominatif(): void
    {
        self::assertFalse(property_exists(InspectionReport::class, 'operatorRef'));
    }

    public function testCodeInconnuRenvoie422(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->processor(controlPointCode: null, operatorRef: 'MAT-0001')
            ->process($this->input('INEXISTANT'), new Post());
    }

    private function input(string $code): InspectionReport
    {
        $dto = new InspectionReport();
        $dto->controlPointCode = $code;

        return $dto;
    }

    private function processor(?string $controlPointCode, ?string $operatorRef): InspectionReportProcessor
    {
        $cp = $controlPointCode === null
            ? null
            : new ControlPoint(
                new Investigation('ref', 'Service test', 'SITE-TEST'),
                $controlPointCode,
                'Libellé',
                Severity::ACUTE,
            );

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($cp);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $user = new User('op@demo.test');
        $user->setRoles(['ROLE_OPERATEUR'])->setOperatorRef($operatorRef);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        return new InspectionReportProcessor($em, $security);
    }
}
