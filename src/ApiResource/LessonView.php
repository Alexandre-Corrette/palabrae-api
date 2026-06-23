<?php

declare(strict_types=1);

namespace App\ApiResource;

/**
 * Projection lecture seule d'une micro-leçon (couche enseignante).
 * Sert le « pourquoi » et le « geste » dans le flux opérateur.
 */
final class LessonView
{
    public function __construct(
        public readonly string $title,
        public readonly string $why,
        public readonly string $how,
        public readonly int $estimatedSeconds,
    ) {
    }
}
