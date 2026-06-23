<?php

declare(strict_types=1);

namespace App\Security;

use App\Enum\DataPurpose;
use Symfony\Component\HttpFoundation\Request;

/**
 * SOURCE DE VÉRITÉ UNIQUE de la finalité d'accès (DataPurpose).
 *
 * La finalité est dérivée du CONTEXTE SERVEUR — ici, le chemin de la route —
 * et JAMAIS d'une donnée fournie par le client (claim JWT, en-tête, paramètre).
 * C'est ce qui empêche un attaquant de poser `data_purpose = coaching` pour
 * lire la donnée nominative.
 *
 * Conventions (le mur RGPD encodé dans le routage) :
 *   /api/coaching/*    → COACHING   (espace d'accompagnement, lecture nominative)
 *   /api/compliance/*  → COMPLIANCE (pilotage agrégé, jamais de nominatif)
 *   tout le reste      → null       (aucune finalité → lecture nominative refusée)
 *
 * DISCIPLINARY n'est JAMAIS dérivable ici : le disciplinaire vit dans un
 * contexte isolé (autre firewall / autre application), de sorte que le coaching
 * ne peut, par construction, nourrir un dossier à charge.
 */
final class DataPurposeResolver
{
    public function resolve(Request $request): ?DataPurpose
    {
        $path = rawurldecode($request->getPathInfo());

        return match (true) {
            str_starts_with($path, '/api/coaching/'), $path === '/api/coaching'
                => DataPurpose::COACHING,
            str_starts_with($path, '/api/compliance/'), $path === '/api/compliance'
                => DataPurpose::COMPLIANCE,
            default => null,
        };
    }
}
