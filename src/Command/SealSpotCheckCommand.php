<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\SealedPlanner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Ouvre un créneau de contrôle surprise (schéma commit-reveal).
 *
 * « Personne ne tient le bouton » : cette commande est appelée par un
 * SCHEDULER (cron / Symfony Scheduler) à l'ouverture du créneau, jamais par un
 * humain ni un endpoint. Elle tire une graine CSPRNG, dérive un nombre de
 * contrôles et ne publie QUE le commitment ; la graine part au coffre.
 *
 * Exemple cron (ouverture du service du midi, 11h, en semaine) :
 *   0 11 * * 1-5  bin/console palabrae:spotcheck:seal SITE-LEOLAGRANGE --label=midi
 */
#[AsCommand(
    name: 'palabrae:spotcheck:seal',
    description: 'Scelle un créneau de contrôle surprise (commit). Déclenché par le scheduler.',
)]
final class SealSpotCheckCommand extends Command
{
    public function __construct(private readonly SealedPlanner $planner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('site', InputArgument::REQUIRED, 'Référence du site (ex. SITE-LEOLAGRANGE)')
            ->addOption('label', null, InputOption::VALUE_REQUIRED, 'Libellé du créneau (ex. midi)', 'midi')
            ->addOption('min', null, InputOption::VALUE_REQUIRED, 'Nombre minimum de contrôles', '1')
            ->addOption('max', null, InputOption::VALUE_REQUIRED, 'Nombre maximum de contrôles', '3')
            ->addOption('window-seconds', null, InputOption::VALUE_REQUIRED, 'Durée du créneau en secondes', '7200');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $site = (string) $input->getArgument('site');
        $label = (string) $input->getOption('label');
        $min = (int) $input->getOption('min');
        $max = (int) $input->getOption('max');
        $windowSeconds = (int) $input->getOption('window-seconds');

        if ($min < 1 || $max < $min) {
            $io->error('Paramètres invalides : il faut 1 <= min <= max.');

            return Command::INVALID;
        }

        $windowRef = sprintf('%s:%s:%s', $site, (new \DateTimeImmutable())->format('Y-m-d'), $label);

        try {
            $plan = $this->planner->seal($windowRef, $site, $min, $max, $windowSeconds);
        } catch (\Throwable $e) {
            $io->error(sprintf('Échec du scellement (%s déjà ouvert ?) : %s', $windowRef, $e->getMessage()));

            return Command::FAILURE;
        }

        $io->success('Créneau scellé.');
        $io->definitionList(
            ['windowRef' => $plan->getWindowRef()],
            ['contrôles engagés' => (string) $plan->getCount()],
            ['commitment' => $plan->getCommitment()],
        );
        $io->note('La graine et les horaires restent secrets jusqu\'à la clôture (reveal).');

        return Command::SUCCESS;
    }
}
