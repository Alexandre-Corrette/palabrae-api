<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\SpotCheckHonor;
use App\Entity\ControlPoint;
use App\Entity\Inspection;
use App\Entity\SpotCheckPlan;
use App\Entity\SpotCheckSlot;
use App\Entity\User;
use App\Enum\InspectionOutcome;
use App\Enum\InspectionSource;
use App\Enum\PlanStatus;
use App\Enum\SlotStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Honore un slot d'un plan scellé et enregistre l'inspection SPOT associée.
 *
 * Sécurité : operatorRef vient du contexte authentifié ; la sortie ne contient
 * aucun champ nominatif.
 *
 * @implements ProcessorInterface<SpotCheckHonor, SpotCheckHonor>
 */
final class SpotCheckHonorProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
    ) {
    }

    /**
     * @param SpotCheckHonor $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SpotCheckHonor
    {
        $plan = $this->em->getRepository(SpotCheckPlan::class)->findOneBy(['windowRef' => $data->windowRef]);
        if (!$plan instanceof SpotCheckPlan) {
            throw new UnprocessableEntityHttpException(sprintf('Créneau inconnu : "%s".', $data->windowRef));
        }
        if ($plan->getStatus() !== PlanStatus::SEALED) {
            throw new UnprocessableEntityHttpException('Le créneau est clos : on ne peut plus honorer de contrôle.');
        }

        $controlPoint = $this->em->getRepository(ControlPoint::class)->findOneBy(['code' => $data->controlPointCode]);
        if (!$controlPoint instanceof ControlPoint) {
            throw new UnprocessableEntityHttpException(sprintf('Point de contrôle inconnu : "%s".', $data->controlPointCode));
        }

        $slot = $this->firstPlannedSlot($plan);
        if ($slot === null) {
            throw new UnprocessableEntityHttpException('Tous les contrôles engagés de ce créneau sont déjà honorés.');
        }

        $user = $this->security->getUser();
        $operatorRef = $user instanceof User ? $user->getOperatorRef() : null;

        // Marque le slot honoré et trace l'inspection (source SPOT, conforme par défaut).
        $slot->honor($operatorRef ?? 'inconnu', $controlPoint->getCode());
        $inspection = new Inspection(
            $controlPoint,
            InspectionOutcome::CONFORM,
            InspectionSource::SPOT,
            $operatorRef,
        );
        $this->em->persist($inspection);
        $this->em->flush();

        $out = new SpotCheckHonor();
        $out->windowRef = $plan->getWindowRef();
        $out->controlPointCode = $controlPoint->getCode();
        $out->ordinal = $slot->getOrdinal();
        $out->source = InspectionSource::SPOT;
        $out->honoredAt = $slot->getHonoredAt();

        return $out;
    }

    private function firstPlannedSlot(SpotCheckPlan $plan): ?SpotCheckSlot
    {
        foreach ($plan->getSlots() as $slot) {
            if ($slot->getStatus() === SlotStatus::PLANNED) {
                return $slot;
            }
        }

        return null;
    }
}
