<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\InspectionReport;
use App\Entity\ControlPoint;
use App\Entity\Inspection;
use App\Entity\User;
use App\Enum\InspectionOutcome;
use App\Enum\InspectionSource;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Enregistre une inspection CONFORME déclarée par l'opérateur.
 *
 * Sécurité : operatorRef vient du contexte authentifié, jamais du client ; la
 * sortie ne renvoie aucun champ nominatif.
 *
 * @implements ProcessorInterface<InspectionReport, InspectionReport>
 */
final class InspectionReportProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
    ) {
    }

    /**
     * @param InspectionReport $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): InspectionReport
    {
        $controlPoint = $this->em->getRepository(ControlPoint::class)
            ->findOneBy(['code' => $data->controlPointCode]);

        if (!$controlPoint instanceof ControlPoint) {
            throw new UnprocessableEntityHttpException(
                sprintf('Point de contrôle inconnu : "%s".', $data->controlPointCode),
            );
        }

        $user = $this->security->getUser();
        $operatorRef = $user instanceof User ? $user->getOperatorRef() : null;

        $inspection = new Inspection(
            $controlPoint,
            InspectionOutcome::CONFORM,
            InspectionSource::DECLARED,
            $operatorRef,
            $data->note,
        );
        $this->em->persist($inspection);
        $this->em->flush();

        $out = new InspectionReport();
        $out->id = $inspection->getId();
        $out->controlPointCode = $controlPoint->getCode();
        $out->note = $inspection->getNote();
        $out->outcome = $inspection->getOutcome();
        $out->source = $inspection->getSource();
        $out->recordedAt = $inspection->getRecordedAt();

        return $out;
    }
}
