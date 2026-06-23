<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Gradient de gravité — le cœur du produit. Une checklist classique traite
 * chaque écart à plat ; ici l'escalade est PROPORTIONNELLE au risque.
 *
 * Exemple restauration : toilettes sales (COSMETIC) → crevette hors chaîne du
 * froid (ACUTE) → allergène non tracé (CRITICAL, potentiellement létal).
 */
enum Severity: int
{
    case COSMETIC = 1;   // gêne client, image (toilettes, propreté de salle)
    case SANITARY = 2;   // non-conformité d'hygiène sans danger immédiat
    case ACUTE = 3;      // danger sanitaire (chaîne du froid, cuisson, DLC)
    case CRITICAL = 4;   // risque grave/vital (allergènes, contamination avérée)

    /** Le manager est-il notifié (en agrégé) ? */
    public function notifiesManager(): bool
    {
        return $this->value >= self::SANITARY->value;
    }

    /** Faut-il un arrêt dur (bloquer le lot / le service) ? */
    public function requiresHardStop(): bool
    {
        return $this->value >= self::ACUTE->value;
    }

    /** L'enregistrement est-il immuable (défense en cas de litige/contrôle) ? */
    public function isImmutable(): bool
    {
        return $this === self::CRITICAL;
    }

    public function label(): string
    {
        return match ($this) {
            self::COSMETIC => 'Cosmétique',
            self::SANITARY => 'Sanitaire',
            self::ACUTE => 'Aiguë',
            self::CRITICAL => 'Critique',
        };
    }
}
