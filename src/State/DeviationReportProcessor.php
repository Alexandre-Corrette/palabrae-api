<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\DeviationReport;
use App\Entity\ControlPoint;
use App\Entity\Deviation;
use App\Entity\User;
use App\Service\DeviationHandler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Orchestration du signalement d'écart.
 *
 * Points de sécurité :
 *  - operatorRef vient du CONTEXTE AUTHENTIFIÉ (Security::getUser()), jamais du
 *    client : impossible de signaler « au nom » de quelqu'un d'autre.
 *  - la sortie est construite à la main et ne contient AUCUN champ nominatif.
 *  - l'escalade (proportionnée à la gravité) et le coaching muré sont délégués à
 *    DeviationHandler, seul détenteur de cette logique.
 *
 * @implements ProcessorInterface<DeviationReport, DeviationReport>
 */
final class DeviationReportProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DeviationHandler $handler,
        private readonly Security $security,
    ) {
    }

    /**
     * @param DeviationReport $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): DeviationReport
    {
        $controlPoint = $this->em->getRepository(ControlPoint::class)
            ->findOneBy(['code' => $data->controlPointCode]);

        if (!$controlPoint instanceof ControlPoint) {
            // Code inconnu = entrée invalide (422), pas une 500.
            throw new UnprocessableEntityHttpException(
                sprintf('Point de contrôle inconnu : "%s".', $data->controlPointCode),
            );
        }

        // Le « qui » est strictement dérivé du jeton authentifié.
        $user = $this->security->getUser();
        $operatorRef = $user instanceof User ? $user->getOperatorRef() : null;

        $deviation = new Deviation($controlPoint, $operatorRef, $data->note);
        $this->em->persist($deviation);

        // Escalade + coaching muré + (à venir) journal. handle() flush lui-même.
        $actions = $this->handler->handle($deviation);

        // Sortie SANS nominatif : on ne renvoie jamais operatorRef.
        $out = new DeviationReport();
        $out->id = $deviation->getId();
        $out->controlPointCode = $controlPoint->getCode();
        $out->note = $deviation->getNote();
        $out->severity = $deviation->severity();
        $out->detectedAt = $deviation->getDetectedAt();
        $out->actions = $actions;

        return $out;
    }
}
