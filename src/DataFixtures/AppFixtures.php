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
        // Vocabulaire calé sur les fiches METRO (réception, huiles de friture,
        // plan de nettoyage cuisine & labo) pour que le gérant reconnaisse SES
        // documents. Dernier booléen : preuve photo (prise en direct) exigée ?
        $points = [
            ['CCP-ALLERG-03', 'Étiquetage allergènes du jour', Severity::CRITICAL,
                "Une erreur d'allergène peut être mortelle. 14 allergènes sont à déclaration obligatoire, et en collectivité le public est captif.",
                "Compare la fiche du jour aux composants réels du plat et affiche-la. Dans le doute, ne sers pas le plat concerné. Photo de l'étiquette du jour.", true],
            ['CCP-FROID-01', 'Température enceinte froide', Severity::ACUTE,
                "Au-dessus de 4 °C, Listeria et salmonelles se multiplient vite. Le public en collectivité est fragile (enfants, malades, personnes âgées).",
                "Relève la température à l'afficheur. Si > 4 °C : isole les denrées sensibles, préviens le responsable, ne sers pas. Photo de l'afficheur.", true],
            ['CCP-CUISSON-04', 'Température à cœur (cuisson)', Severity::ACUTE,
                "Une cuisson à cœur insuffisante laisse survivre les pathogènes, même si l'extérieur paraît cuit.",
                "Sonde à cœur. Atteins la cible (63–75 °C selon le plat). Re-cuis si le seuil n'est pas atteint. Photo de la sonde.", true],
            ['CCP-RECEP-02', 'Contrôle à réception', Severity::ACUTE,
                "Le contrôle à réception garantit que le produit est conforme et sans anomalie visible ; une rupture de la chaîne du froid à la livraison contamine en amont.",
                "Contrôle T°, DLC/DLUO, fraîcheur, état de l'emballage et du conditionnement, présence des étiquettes sanitaires, quantité. Refuse et note la livraison si non conforme. Photo de la livraison.", true],
            ['CCP-HUILE-05', 'Vérification des huiles de friture', Severity::SANITARY,
                "Un bain de friture usé produit des composés nocifs. Signes : brunissement, changement d'odeur et de goût, huile visqueuse, fumées précoces, mousse stable (un bain qui a moussé est inutilisable).",
                "Contrôle au testeur d'huiles ou bandelettes, plus contrôle visuel et olfactif. Tout bain non conforme doit être changé immédiatement. Ne pas chauffer à plus de 180 °C. Photo de la bandelette.", true],
            ['NET-CUISSON-06', 'Nettoyage four et appareils de cuisson', Severity::SANITARY,
                "Les résidus carbonisés et les graisses favorisent la prolifération microbienne et les mauvaises odeurs.",
                "1 fois/jour. Éliminer les résidus, laisser refroidir < 50 °C, pulvériser, laisser agir 10 min, brosser, rincer. Photo après nettoyage.", true],
            ['NET-PLANS-07', 'Nettoyage des plans de travail (surfaces au contact)', Severity::SANITARY,
                "Les surfaces au contact des aliments sont une voie directe de contamination croisée.",
                "Au minimum 2 fois/jour et après chaque service. Éliminer les déchets, pulvériser, brosser à la lavette, rincer, essuyer.", false],
            ['NET-SOLS-08', 'Nettoyage et désinfection des sols', Severity::COSMETIC,
                "Des sols souillés entretiennent l'humidité et les nuisibles, et signalent un relâchement général.",
                "Dilution 50 ml pour 8 L d'eau. Balai, serpillères ou autolaveuse. Laisser agir 5 min, laisser sécher.", false],
            ['PROP-SALLE-01', 'Propreté des sanitaires', Severity::COSMETIC,
                "Des sanitaires sales rebutent le convive et signalent un relâchement de l'hygiène.",
                "Nettoie et horodate ton passage.", false],
        ];

        foreach ($points as [$code, $label, $severity, $why, $how, $requiresPhoto]) {
            $lesson = new MicroLesson($label, $why, $how);
            $manager->persist($lesson);

            $cp = new ControlPoint($procedure, $code, $label, $severity);
            $cp->attachLesson($lesson);
            $cp->setRequiresPhoto($requiresPhoto);
            $manager->persist($cp);
        }

        $manager->flush();
    }
}
