<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\ComplianceSummary;
use App\Entity\ControlPoint;
use App\Entity\Deviation;
use App\Entity\Inspection;
use App\Enum\InspectionOutcome;
use App\Enum\Severity;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Provider du résumé de conformité. Construit l'agrégat directement en SQL/DQL
 * de comptage : on ne SELECT JAMAIS operatorRef. La requête ne peut donc pas
 * fuir de donnée nominative, même par erreur de développement ultérieure.
 *
 * @implements ProviderInterface<ComplianceSummary>
 */
final class ComplianceSummaryProvider implements ProviderInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * @return ComplianceSummary[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        // Comptage agrégé par point de contrôle. Aucune projection du « qui ».
        $rows = $this->em->createQueryBuilder()
            ->select('cp.code AS code', 'cp.label AS label', 'cp.severity AS severity')
            ->addSelect('COUNT(d.id) AS total')
            ->addSelect('SUM(CASE WHEN d.resolved = false THEN 1 ELSE 0 END) AS unresolved')
            ->from(ControlPoint::class, 'cp')
            ->leftJoin(Deviation::class, 'd', 'WITH', 'd.controlPoint = cp')
            ->groupBy('cp.id')
            ->addGroupBy('cp.code')
            ->addGroupBy('cp.label')
            ->addGroupBy('cp.severity')
            ->orderBy('cp.severity', 'DESC')
            ->getQuery()
            ->getResult();

        // Conformes : requête SÉPARÉE (sinon produit cartésien avec les écarts).
        // Toujours du comptage anonyme — operatorRef n'est jamais projeté.
        $conformByCode = $this->conformCountByControlPointCode();

        return array_map(
            static fn (array $r): ComplianceSummary => new ComplianceSummary(
                controlPointCode: $r['code'],
                controlPointLabel: $r['label'],
                // En hydratation scalaire, un champ enumType revient en valeur
                // brute : on reconstruit l'enum explicitement.
                severity: $r['severity'] instanceof Severity ? $r['severity'] : Severity::from((int) $r['severity']),
                deviationCount: (int) $r['total'],
                unresolvedCount: (int) $r['unresolved'],
                conformCount: $conformByCode[$r['code']] ?? 0,
            ),
            $rows,
        );
    }

    /**
     * Décompte des inspections conformes par code de point de contrôle.
     *
     * @return array<string, int>
     */
    private function conformCountByControlPointCode(): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('cp.code AS code', 'COUNT(i.id) AS conform')
            ->from(ControlPoint::class, 'cp')
            ->leftJoin(
                Inspection::class,
                'i',
                'WITH',
                'i.controlPoint = cp AND i.outcome = :conform',
            )
            ->setParameter('conform', InspectionOutcome::CONFORM->value)
            ->groupBy('cp.id')
            ->addGroupBy('cp.code')
            ->getQuery()
            ->getResult();

        $byCode = [];
        foreach ($rows as $r) {
            $byCode[$r['code']] = (int) $r['conform'];
        }

        return $byCode;
    }
}
