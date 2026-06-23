<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use ApiPlatform\Metadata\Post;
use App\ApiResource\DeviationReport;
use App\Entity\ControlPoint;
use App\Entity\Investigation;
use App\Entity\MicroLesson;
use App\Entity\User;
use App\Enum\Severity;
use App\Service\DeviationHandler;
use App\State\DeviationReportProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Vérifie le flux de signalement : escalade proportionnée à la gravité,
 * operatorRef issu du contexte authentifié, et sortie sans donnée nominative.
 */
final class DeviationReportProcessorTest extends TestCase
{
    public function testCritiqueDeclencheTouteLEscalade(): void
    {
        $cp = $this->controlPoint('CCP-ALLERG-03', Severity::CRITICAL, withLesson: true);
        $processor = $this->processor($cp, operatorRef: 'MAT-0001');

        $out = $processor->process($this->input('CCP-ALLERG-03'), new Post());

        self::assertSame(Severity::CRITICAL, $out->severity);
        self::assertContains('HARD_STOP', $out->actions);
        self::assertContains('NOTIFY_MANAGER_AGGREGATED', $out->actions);
        self::assertContains('IMMUTABLE_RECORD', $out->actions);
        self::assertContains('COACHING_EMITTED', $out->actions);
        self::assertContains('LESSON_SERVED', $out->actions);
    }

    public function testCosmetiqueEstUnSimpleNudge(): void
    {
        $cp = $this->controlPoint('PROP-SALLE-01', Severity::COSMETIC);
        $processor = $this->processor($cp, operatorRef: 'MAT-0001');

        $out = $processor->process($this->input('PROP-SALLE-01'), new Post());

        self::assertSame(Severity::COSMETIC, $out->severity);
        self::assertContains('NUDGE_ONLY', $out->actions);
        self::assertNotContains('HARD_STOP', $out->actions);
        self::assertNotContains('NOTIFY_MANAGER_AGGREGATED', $out->actions);
    }

    public function testOperatorRefVientDuContexteAuthentifie(): void
    {
        // Avec un opérateur identifié → coaching émis (nominatif, mais muré).
        $cp = $this->controlPoint('CCP-FROID-01', Severity::ACUTE);
        $withUser = $this->processor($cp, operatorRef: 'MAT-0001');
        self::assertContains('COACHING_EMITTED', $withUser->process($this->input('CCP-FROID-01'), new Post())->actions);

        // Sans operatorRef dans le contexte → aucun coaching nominatif émis.
        $cp2 = $this->controlPoint('CCP-FROID-01', Severity::ACUTE);
        $withoutUser = $this->processor($cp2, operatorRef: null);
        self::assertNotContains('COACHING_EMITTED', $withoutUser->process($this->input('CCP-FROID-01'), new Post())->actions);
    }

    public function testSortieNeContientAucunChampNominatif(): void
    {
        self::assertFalse(property_exists(DeviationReport::class, 'operatorRef'));
    }

    public function testCodeInconnuRenvoie422(): void
    {
        $processor = $this->processor(controlPoint: null, operatorRef: 'MAT-0001');

        $this->expectException(UnprocessableEntityHttpException::class);
        $processor->process($this->input('CODE-INEXISTANT'), new Post());
    }

    // ——— Helpers ———

    private function input(string $code): DeviationReport
    {
        $dto = new DeviationReport();
        $dto->controlPointCode = $code;
        $dto->note = 'note de test';

        return $dto;
    }

    private function controlPoint(string $code, Severity $severity, bool $withLesson = false): ControlPoint
    {
        $procedure = new Investigation('ref:'.$code, 'Service test', 'SITE-TEST');
        $cp = new ControlPoint($procedure, $code, 'Libellé '.$code, $severity);
        if ($withLesson) {
            $cp->attachLesson(new MicroLesson('Titre', 'Pourquoi', 'Le geste'));
        }

        return $cp;
    }

    private function processor(?ControlPoint $controlPoint, ?string $operatorRef): DeviationReportProcessor
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($controlPoint);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $user = new User('op@demo.test');
        $user->setRoles(['ROLE_OPERATEUR'])->setOperatorRef($operatorRef);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        return new DeviationReportProcessor($em, new DeviationHandler($em), $security);
    }
}
