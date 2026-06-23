<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * SEUL écrivain de l'attribut `data_purpose` sur le jeton.
 *
 * À chaque requête (après l'authentification du firewall), il (ré)écrit la
 * finalité d'accès à partir du CONTEXTE SERVEUR (DataPurposeResolver), et EFFACE
 * toute valeur préexistante. Conséquence : même si un claim JWT forgé ou un
 * autre composant tentait d'injecter `data_purpose = coaching`, cette valeur est
 * systématiquement écrasée par la finalité serveur. La forge est donc sans effet.
 *
 * Priorité 7 : juste après le firewall (priorité 8) — le jeton existe déjà —
 * et avant l'exécution du contrôleur et des voters.
 */
final class DataPurposeContextListener implements EventSubscriberInterface
{
    private const ATTRIBUTE = 'data_purpose';

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly DataPurposeResolver $resolver,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if ($token === null) {
            return;
        }

        // On repart toujours des attributs SANS data_purpose (anti-forge),
        // puis on pose la seule valeur autorisée : celle décidée côté serveur.
        $attributes = $token->getAttributes();
        unset($attributes[self::ATTRIBUTE]);

        $purpose = $this->resolver->resolve($event->getRequest());
        if ($purpose !== null) {
            $attributes[self::ATTRIBUTE] = $purpose;
        }

        $token->setAttributes($attributes);
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 7]];
    }
}
