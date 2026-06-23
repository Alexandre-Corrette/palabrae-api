<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\SpotCheckPlan;
use App\Service\SealedPlanner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Clôt un créneau de contrôle surprise : révèle la graine puis balaie les
 * contrôles non honorés.
 *
 * Appelée par le SCHEDULER à la fin du créneau. `reveal()` vérifie le commitment
 * et journalise (toute partie peut alors recalculer et constater que le plan
 * était fixé d'avance) ; `close()` passe les slots non honorés en MISSED — et
 * comme l'attente était scellée, chaque manque est PROUVABLE.
 *
 * Exemple cron (clôture du service du midi, 14h) :
 *   0 14 * * 1-5  bin/console palabrae:spotcheck:reveal SITE-LEOLAGRANGE:2026-06-23:midi
 */
#[AsCommand(
    name: 'palabrae:spotcheck:reveal',
    description: 'Révèle et clôt un créneau de contrôle surprise. Déclenché par le scheduler.',
)]
final class RevealSpotCheckCommand extends Command
{
    public function __construct(
        private readonly SealedPlanner $planner,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('windowRef', InputArgument::REQUIRED, 'Référence du créneau (ex. SITE-...:2026-06-23:midi)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $windowRef = (string) $input->getArgument('windowRef');

        $plan = $this->em->getRepository(SpotCheckPlan::class)->findOneBy(['windowRef' => $windowRef]);
        if (!$plan instanceof SpotCheckPlan) {
            $io->error(sprintf('Aucun plan scellé pour le créneau "%s".', $windowRef));

            return Command::FAILURE;
        }

        try {
            // Le créneau est clos (le scheduler appelle reveal après sa fin).
            $this->planner->reveal($plan, windowClosed: true);
            $stats = $this->planner->close($plan);
        } catch (\Throwable $e) {
            $io->error(sprintf('Échec de la clôture : %s', $e->getMessage()));

            return Command::FAILURE;
        }

        $io->success('Créneau révélé et clôturé.');
        $io->definitionList(
            ['windowRef' => $plan->getWindowRef()],
            ['graine révélée (hex)' => (string) $plan->getRevealedSeed()],
            ['contrôles engagés' => (string) $stats['committed']],
            ['honorés' => (string) $stats['honored']],
            ['manqués (prouvables)' => (string) $stats['missed']],
        );
        $io->note('La graine est désormais publique : n\'importe qui peut recalculer le commitment et vérifier que le plan était fixé d\'avance.');

        return Command::SUCCESS;
    }
}
