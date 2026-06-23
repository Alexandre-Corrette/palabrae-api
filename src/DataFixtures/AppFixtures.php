<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\ControlPoint;
use App\Entity\Investigation;
use App\Entity\MicroLesson;
use App\Entity\User;
use App\Enum\Severity;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Données d'amorçage, reprises de la verticale restauration du prototype.
 * Codes CCP, gravités et micro-leçons issus de POINTS (prototype.jsx).
 */
final class AppFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        // ——— Comptes de démo (mots de passe à changer hors démo) ———
        $operateur = (new User('operateur@demo.test'))->setRoles(['ROLE_OPERATEUR'])->setOperatorRef('MAT-0001');
        $operateur->setPassword($this->hasher->hashPassword($operateur, 'demo-operateur'));

        $responsable = (new User('responsable@demo.test'))->setRoles(['ROLE_RESPONSABLE']);
        $responsable->setPassword($this->hasher->hashPassword($responsable, 'demo-responsable'));

        $manager->persist($operateur);
        $manager->persist($responsable);

        // ——— Une instance de procédure (noyau générique) ———
        $procedure = new Investigation(
            reference: 'cantine-leo-lagrange:'.date('Y-m-d').':midi',
            label: 'Cantine Léo-Lagrange · Service midi',
            siteRef: 'SITE-LEOLAGRANGE',
        );
        $manager->persist($procedure);

        // ——— Points de contrôle + micro-leçons (verticale HACCP) ———
        $points = [
            ['CCP-ALLERG-03', 'Étiquetage allergènes du jour', Severity::CRITICAL,
                "Une erreur d'allergène peut être mortelle. 14 allergènes sont à déclaration obligatoire, et en cantine le public est captif.",
                "Compare la fiche du jour aux composants réels du plat. Affiche-la. Dans le doute, ne sers pas le plat concerné."],
            ['CCP-FROID-01', 'Température chambre froide', Severity::ACUTE,
                "Au-dessus de 4 °C, Listeria et salmonelles se multiplient vite. Le public en collectivité est fragile.",
                "Relève la température. Si > 4 °C : isole les denrées sensibles, préviens le responsable, ne sers pas."],
            ['CCP-CUISSON-04', 'Température à cœur (cuisson)', Severity::ACUTE,
                "Une cuisson à cœur insuffisante laisse survivre les pathogènes, même si l'extérieur paraît cuit.",
                "Sonde à cœur. Atteins la cible (63–75 °C selon le plat). Re-cuis si le seuil n'est pas atteint."],
            ['CCP-RECEP-02', 'Contrôle à réception', Severity::SANITARY,
                "Une rupture de la chaîne du froid à la livraison contamine en amont, avant même la cuisine.",
                "Contrôle température et aspect à l'arrivée. Refuse et note la livraison si elle n'est pas conforme."],
            ['CCP-HUILE-05', 'Contrôle huile de friture', Severity::SANITARY,
                "Une huile dégradée produit des composés nocifs et altère le goût.",
                "Contrôle visuel et test. Filtre ou change l'huile au-delà du seuil."],
            ['PROP-SALLE-01', 'Propreté des sanitaires', Severity::COSMETIC,
                "Des sanitaires sales rebutent le convive et signalent un relâchement de l'hygiène.",
                "Nettoie et horodate ton passage."],
        ];

        foreach ($points as [$code, $label, $severity, $why, $how]) {
            $lesson = new MicroLesson($label, $why, $how);
            $manager->persist($lesson);

            $cp = new ControlPoint($procedure, $code, $label, $severity);
            $cp->attachLesson($lesson);
            $manager->persist($cp);
        }

        $manager->flush();
    }
}
