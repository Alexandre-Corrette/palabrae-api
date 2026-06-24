<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use ApiPlatform\Metadata\Post;
use App\ApiResource\ReceptionCheck;
use App\Entity\ControlCriterion;
use App\Entity\ControlPoint;
use App\Entity\Investigation;
use App\Entity\User;
use App\Enum\Comparator;
use App\Enum\CriterionType;
use App\Enum\Severity;
use App\Service\CriterionEvaluator;
use App\Service\DeviationHandler;
use App\State\ReceptionCheckProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * BL multi-lignes : la gravité globale = la plus haute des critères en défaut,
 * toutes lignes confondues.
 */
final class ReceptionCheckProcessorTest extends TestCase
{
    public function testToutConformeSurPlusieursLignes(): void
    {
        $out = $this->processor()->process($this->input([
            ['productLabel' => 'Filet de poulet', 'answers' => ['temperature' => 3.0, 'dlc' => true, 'quantite' => true]],
            ['productLabel' => 'Yaourts', 'answers' => ['temperature' => 4.0, 'dlc' => true, 'quantite' => true]],
        ]), new Post());

        self::assertSame('conform', $out->outcome);
        self::assertNull($out->severity);
        self::assertCount(2, $out->lineResults);
    }

    public function testUneLigneHorsSeuilEscaladeToutLeBL(): void
    {
        $out = $this->processor()->process($this->input([
            ['productLabel' => 'Filet de poulet', 'answers' => ['temperature' => 3.0, 'dlc' => true, 'quantite' => true]],
            ['productLabel' => 'Crevettes', 'answers' => ['temperature' => 8.0, 'dlc' => true, 'quantite' => true]],
        ]), new Post());

        self::assertSame('deviation', $out->outcome);
        self::assertSame(Severity::ACUTE, $out->severity);
        self::assertContains('HARD_STOP', $out->actions);
    }

    public function testSeuleLaQuantiteKoResteCosmetique(): void
    {
        $out = $this->processor()->process($this->input([
            ['productLabel' => 'Pain', 'answers' => ['temperature' => 3.0, 'dlc' => true, 'quantite' => false]],
        ]), new Post());

        self::assertSame('deviation', $out->outcome);
        self::assertSame(Severity::COSMETIC, $out->severity);
        self::assertContains('NUDGE_ONLY', $out->actions);
        self::assertNotContains('HARD_STOP', $out->actions);
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     */
    private function input(array $lines): ReceptionCheck
    {
        $dto = new ReceptionCheck();
        $dto->controlPointCode = 'CCP-RECEP-02';
        $dto->blNumber = 'BL-2026-0042';
        $dto->lines = $lines;

        return $dto;
    }

    private function processor(): ReceptionCheckProcessor
    {
        $cp = new ControlPoint(new Investigation('r', 'S', 'SITE'), 'CCP-RECEP-02', 'Réception', Severity::ACUTE);
        (new ControlCriterion($cp, 'temperature', 'Température', CriterionType::MEASURE, Severity::ACUTE, 0))
            ->withRule(Comparator::LESS_OR_EQUAL, 4.0, '°C');
        new ControlCriterion($cp, 'dlc', 'DLC/DLUO', CriterionType::BOOLEAN, Severity::ACUTE, 1);
        new ControlCriterion($cp, 'quantite', 'Quantité', CriterionType::BOOLEAN, Severity::COSMETIC, 2);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($cp);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $user = new User('op@demo.test');
        $user->setRoles(['ROLE_OPERATEUR'])->setOperatorRef('MAT-0001');
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        return new ReceptionCheckProcessor($em, new CriterionEvaluator(), new DeviationHandler($em), $security);
    }
}
