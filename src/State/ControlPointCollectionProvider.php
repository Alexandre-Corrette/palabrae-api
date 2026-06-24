<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\ControlPointView;
use App\ApiResource\CriterionView;
use App\ApiResource\LessonView;
use App\Entity\ControlCriterion;
use App\Entity\ControlPoint;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Provider de la checklist opérateur. Lecture seule, triée par gravité
 * décroissante (le plus risqué d'abord). Filtre optionnel par procédure.
 *
 * @implements ProviderInterface<ControlPointView>
 */
final class ControlPointCollectionProvider implements ProviderInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * @return ControlPointView[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $procedureRef = $context['filters']['procedure'] ?? null;

        $qb = $this->em->getRepository(ControlPoint::class)->createQueryBuilder('cp')
            ->innerJoin('cp.procedure', 'proc')
            ->leftJoin('cp.lesson', 'l')->addSelect('l')
            ->orderBy('cp.severity', 'DESC')
            ->addOrderBy('cp.code', 'ASC');

        if (is_string($procedureRef) && $procedureRef !== '') {
            $qb->andWhere('proc.reference = :ref')->setParameter('ref', $procedureRef);
        }

        /** @var ControlPoint[] $points */
        $points = $qb->getQuery()->getResult();

        return array_map(
            static function (ControlPoint $cp): ControlPointView {
                $lesson = $cp->getLesson();

                return new ControlPointView(
                    code: $cp->getCode(),
                    label: $cp->getLabel(),
                    severity: $cp->getSeverity(),
                    severityLabel: $cp->getSeverity()->label(),
                    lesson: $lesson === null ? null : new LessonView(
                        title: $lesson->getTitle(),
                        why: $lesson->getWhy(),
                        how: $lesson->getHow(),
                        estimatedSeconds: $lesson->getEstimatedSeconds(),
                    ),
                    procedureReference: $cp->getProcedure()->getReference(),
                    requiresPhoto: $cp->requiresPhoto(),
                    criteria: array_map(
                        static fn (ControlCriterion $c): CriterionView => new CriterionView(
                            code: $c->getCode(),
                            label: $c->getLabel(),
                            type: $c->getType()->value,
                            unit: $c->getUnit(),
                            rule: $c->ruleLabel(),
                            severityLabel: $c->getSeverity()->label(),
                        ),
                        $cp->getCriteria()->toArray(),
                    ),
                );
            },
            $points,
        );
    }
}
