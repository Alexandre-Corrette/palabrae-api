<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\ReceptionCheck;
use App\Entity\ControlCriterion;
use App\Entity\ControlPoint;
use App\Entity\Deviation;
use App\Entity\Inspection;
use App\Entity\Reception;
use App\Entity\ReceptionLine;
use App\Entity\User;
use App\Enum\InspectionOutcome;
use App\Enum\InspectionSource;
use App\Enum\Severity;
use App\Service\CriterionEvaluator;
use App\Service\DeviationHandler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Contrôle d'un bon de livraison (BL) multi-lignes.
 *
 * Chaque ligne produit est évaluée sur les critères du point (T°, DLC, …). Le
 * SERVEUR applique les règles métier ; la gravité globale = la plus haute des
 * critères en défaut, toutes lignes confondues, et pilote l'escalade. Le BL et
 * ses lignes sont persistés (preuve d'audit).
 *
 * @implements ProcessorInterface<ReceptionCheck, ReceptionCheck>
 */
final class ReceptionCheckProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CriterionEvaluator $evaluator,
        private readonly DeviationHandler $handler,
        private readonly Security $security,
    ) {
    }

    /**
     * @param ReceptionCheck $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ReceptionCheck
    {
        $controlPoint = $this->em->getRepository(ControlPoint::class)->findOneBy(['code' => $data->controlPointCode]);
        if (!$controlPoint instanceof ControlPoint) {
            throw new UnprocessableEntityHttpException(sprintf('Point de contrôle inconnu : "%s".', $data->controlPointCode));
        }
        if (!$controlPoint->hasCriteria()) {
            throw new UnprocessableEntityHttpException('Ce point de contrôle n\'a pas de critères.');
        }
        if ($data->lines === []) {
            throw new UnprocessableEntityHttpException('Au moins une ligne produit est requise.');
        }

        $user = $this->security->getUser();
        $operatorRef = $user instanceof User ? $user->getOperatorRef() : null;

        $reception = new Reception($controlPoint, $data->blNumber, $operatorRef);

        /** @var ControlCriterion[] $criteria */
        $criteria = $controlPoint->getCriteria()->toArray();

        /** @var ControlCriterion[] $failedAll */
        $failedAll = [];
        $nonConformProducts = [];
        $lineResultsOut = [];

        foreach ($data->lines as $line) {
            $productLabel = trim((string) ($line['productLabel'] ?? ''));
            if ($productLabel === '') {
                $productLabel = trim((string) ($line['productCode'] ?? '')) ?: 'Produit';
            }
            $productCode = isset($line['productCode']) && $line['productCode'] !== '' ? (string) $line['productCode'] : null;
            $answers = is_array($line['answers'] ?? null) ? $line['answers'] : [];

            $results = [];
            $lineConform = true;
            foreach ($criteria as $criterion) {
                $answer = $this->normalize($answers[$criterion->getCode()] ?? null);
                $conform = $this->evaluator->isConform($criterion, $answer);
                $results[] = ['criterion' => $criterion->getCode(), 'label' => $criterion->getLabel(), 'value' => $answer, 'conform' => $conform];
                if (!$conform) {
                    $lineConform = false;
                    $failedAll[] = $criterion;
                }
            }

            new ReceptionLine($reception, $productLabel, $productCode, $results, $lineConform);
            if (!$lineConform) {
                $nonConformProducts[] = $productLabel;
            }
            $lineResultsOut[] = ['productLabel' => $productLabel, 'productCode' => $productCode, 'conform' => $lineConform, 'results' => $results];
        }

        $deviation = $failedAll !== [];
        $effective = $deviation ? $this->maxSeverity($failedAll) : null;
        $outcome = $deviation ? InspectionOutcome::DEVIATION : InspectionOutcome::CONFORM;

        $reception->conclude($outcome, $effective);
        $this->em->persist($reception);
        $this->em->persist(new Inspection($controlPoint, $outcome, InspectionSource::DECLARED, $operatorRef));

        $out = new ReceptionCheck();
        $out->controlPointCode = $controlPoint->getCode();
        $out->blNumber = $data->blNumber;
        $out->lineResults = $lineResultsOut;

        if (!$deviation || $effective === null) {
            $this->em->flush();
            $out->outcome = 'conform';
            $out->actions = [];

            return $out;
        }

        $dev = new Deviation(
            $controlPoint,
            $operatorRef,
            sprintf('Réception BL %s — non conforme : %s', $data->blNumber, implode(', ', array_unique($nonConformProducts))),
        );
        $dev->overrideSeverity($effective);
        $this->em->persist($dev);

        $out->actions = $this->handler->handle($dev); // flush inclus
        $out->outcome = 'deviation';
        $out->severity = $effective;
        $out->severityLabel = $effective->label();

        return $out;
    }

    /**
     * @param ControlCriterion[] $failed
     */
    private function maxSeverity(array $failed): Severity
    {
        $max = Severity::COSMETIC;
        foreach ($failed as $criterion) {
            if ($criterion->getSeverity()->value > $max->value) {
                $max = $criterion->getSeverity();
            }
        }

        return $max;
    }

    private function normalize(mixed $value): bool|int|float|null
    {
        if (is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        if ($value === 'true') {
            return true;
        }
        if ($value === 'false') {
            return false;
        }
        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }
}
