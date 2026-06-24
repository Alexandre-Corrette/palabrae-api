<?php

declare(strict_types=1);

namespace App\SpotCheck;

/**
 * Résout les plages horaires autorisées PAR SITE.
 *
 * Les horaires de service varient d'une cantine à l'autre : la configuration
 * porte une entrée par `siteRef`, plus un `_default` en repli. Demain, cette
 * source pourra devenir une entité en base sans toucher au reste.
 */
final class ControlHoursResolver
{
    /**
     * @param array<string, array{day: array{0:string,1:string}, forbidden?: list<string>}> $sites
     */
    public function __construct(private readonly array $sites)
    {
    }

    public function forSite(string $siteRef): ControlHours
    {
        $cfg = $this->sites[$siteRef] ?? $this->sites['_default'] ?? null;
        if ($cfg === null) {
            throw new \RuntimeException(sprintf('Aucune plage horaire pour "%s" (ni _default).', $siteRef));
        }

        return ControlHours::fromDay($cfg['day'][0], $cfg['day'][1], $cfg['forbidden'] ?? []);
    }
}
