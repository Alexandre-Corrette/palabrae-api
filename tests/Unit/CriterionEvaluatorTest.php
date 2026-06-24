<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\ControlCriterion;
use App\Entity\ControlPoint;
use App\Entity\Investigation;
use App\Enum\Comparator;
use App\Enum\CriterionType;
use App\Enum\Severity;
use App\Service\CriterionEvaluator;
use PHPUnit\Framework\TestCase;

/**
 * Vérifie la règle métier d'un critère : booléen et seuil de mesure.
 */
final class CriterionEvaluatorTest extends TestCase
{
    private CriterionEvaluator $evaluator;
    private ControlPoint $cp;

    protected function setUp(): void
    {
        $this->evaluator = new CriterionEvaluator();
        $this->cp = new ControlPoint(new Investigation('r', 'S', 'SITE'), 'CCP-RECEP-02', 'Réception', Severity::ACUTE);
    }

    public function testBooleenConformeSiVrai(): void
    {
        $c = new ControlCriterion($this->cp, 'dlc', 'DLC', CriterionType::BOOLEAN, Severity::ACUTE);

        self::assertTrue($this->evaluator->isConform($c, true));
        self::assertFalse($this->evaluator->isConform($c, false));
        self::assertFalse($this->evaluator->isConform($c, null));
    }

    public function testMesureRespecteLeSeuil(): void
    {
        $c = (new ControlCriterion($this->cp, 'temperature', 'T°', CriterionType::MEASURE, Severity::ACUTE))
            ->withRule(Comparator::LESS_OR_EQUAL, 4.0, '°C');

        self::assertTrue($this->evaluator->isConform($c, 3.0));
        self::assertTrue($this->evaluator->isConform($c, 4.0));
        self::assertFalse($this->evaluator->isConform($c, 8.0));
    }

    public function testMesureManquanteEstNonConforme(): void
    {
        $c = (new ControlCriterion($this->cp, 'temperature', 'T°', CriterionType::MEASURE, Severity::ACUTE))
            ->withRule(Comparator::LESS_OR_EQUAL, 4.0, '°C');

        self::assertFalse($this->evaluator->isConform($c, null));
    }
}
