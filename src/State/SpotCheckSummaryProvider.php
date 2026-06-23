<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\SpotCheckSummary;
use App\Entity\Inspection;
use App\Entity\SpotCheckPlan;
use App\Entity\SpotCheckSlot;
use App\Enum\InspectionSource;
use App\Enum\PlanStatus;
use App\Enum\SlotStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Provider du signal d'intégrité contrôle surprise. Comptage pur, anonyme :
 * operatorRef n'est jamais projeté.
 *
 * @implements ProviderInterface<SpotCheckSummary>
 */
final class SpotCheckSummaryProvider implements ProviderInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * @return SpotCheckSummary[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        return [
            new SpotCheckSummary(
                plansSealed: $this->countPlans(PlanStatus::SEALED),
                plansRevealed: $this->countPlans(PlanStatus::REVEALED),
                controlsHonored: $this->countSlots(SlotStatus::HONORED),
                controlsMissed: $this->countSlots(SlotStatus::MISSED),
                spotInspections: $this->countInspections(InspectionSource::SPOT),
                iotDetections: $this->countInspections(InspectionSource::IOT),
            ),
        ];
    }

    private function countPlans(PlanStatus $status): int
    {
        return $this->countBy(SpotCheckPlan::class, 'status', $status->value);
    }

    private function countSlots(SlotStatus $status): int
    {
        return $this->countBy(SpotCheckSlot::class, 'status', $status->value);
    }

    private function countInspections(InspectionSource $source): int
    {
        return $this->countBy(Inspection::class, 'source', $source->value);
    }

    /**
     * @param class-string $entity
     */
    private function countBy(string $entity, string $field, string $value): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(e.id)')
            ->from($entity, 'e')
            ->where(sprintf('e.%s = :v', $field))
            ->setParameter('v', $value)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
