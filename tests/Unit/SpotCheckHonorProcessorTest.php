<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use ApiPlatform\Metadata\Post;
use App\ApiResource\SpotCheckHonor;
use App\Entity\ControlPoint;
use App\Entity\Investigation;
use App\Entity\SpotCheckPlan;
use App\Entity\SpotCheckSlot;
use App\Entity\User;
use App\Enum\InspectionSource;
use App\Enum\Severity;
use App\Enum\SlotStatus;
use App\State\SpotCheckHonorProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Vérifie le honor d'un contrôle surprise : slot HONORED, inspection SPOT,
 * operatorRef du contexte, et garde-fous (créneau clos / plus de slot).
 */
final class SpotCheckHonorProcessorTest extends TestCase
{
    public function testHonoreLePremierSlotPlanned(): void
    {
        $plan = new SpotCheckPlan('w', 'SITE', 2, 'commit');
        new SpotCheckSlot($plan, 0);
        new SpotCheckSlot($plan, 1);

        $out = $this->processor($plan, $this->controlPoint())->process($this->input(), new Post());

        self::assertSame(InspectionSource::SPOT, $out->source);
        self::assertSame(0, $out->ordinal);
        self::assertNotNull($out->honoredAt);

        $statuses = array_map(
            static fn (SpotCheckSlot $s): SlotStatus => $s->getStatus(),
            $plan->getSlots()->toArray(),
        );
        self::assertContains(SlotStatus::HONORED, $statuses);
    }

    public function testCreneauClosRefuse(): void
    {
        $plan = new SpotCheckPlan('w', 'SITE', 1, 'commit');
        new SpotCheckSlot($plan, 0);
        $plan->reveal('deadbeef'); // passe le plan en REVEALED

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->processor($plan, $this->controlPoint())->process($this->input(), new Post());
    }

    public function testPlusDeSlotDisponibleRefuse(): void
    {
        $plan = new SpotCheckPlan('w', 'SITE', 0, 'commit'); // aucun slot

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->processor($plan, $this->controlPoint())->process($this->input(), new Post());
    }

    private function input(): SpotCheckHonor
    {
        $dto = new SpotCheckHonor();
        $dto->windowRef = 'w';
        $dto->controlPointCode = 'CCP-FROID-01';

        return $dto;
    }

    private function controlPoint(): ControlPoint
    {
        return new ControlPoint(
            new Investigation('ref', 'Service', 'SITE'),
            'CCP-FROID-01',
            'Température chambre froide',
            Severity::ACUTE,
        );
    }

    private function processor(SpotCheckPlan $plan, ControlPoint $cp): SpotCheckHonorProcessor
    {
        $planRepo = $this->createMock(EntityRepository::class);
        $planRepo->method('findOneBy')->willReturn($plan);
        $cpRepo = $this->createMock(EntityRepository::class);
        $cpRepo->method('findOneBy')->willReturn($cp);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(
            static fn (string $class) => $class === SpotCheckPlan::class ? $planRepo : $cpRepo,
        );

        $user = new User('op@demo.test');
        $user->setRoles(['ROLE_OPERATEUR'])->setOperatorRef('MAT-0001');
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        return new SpotCheckHonorProcessor($em, $security);
    }
}
