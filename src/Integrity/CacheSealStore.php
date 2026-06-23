<?php

declare(strict_types=1);

namespace App\Integrity;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Coffre de graines PERSISTANT, adossé à un pool de cache DÉDIÉ — distinct de
 * la base de données applicative.
 *
 * Pourquoi pas InMemorySealStore : en PHP-FPM / multi-process, le worker qui
 * `seal()` n'est pas celui qui `reveal()`. Un store en mémoire perd la graine
 * entre deux requêtes/commandes → `reveal()` échoue. Un pool persistant
 * (filesystem en dev, Redis/secret manager en prod) survit entre process.
 *
 * Invariant préservé : la graine ne touche JAMAIS la base applicative. Le pool
 * `seal_store.pool` est isolé du reste.
 *
 * ⚠️ En PROD, pointer ce pool (ou remplacer cette implémentation) vers un
 * secret manager (Vault / KMS / Secrets Manager) : chiffrement au repos, accès
 * audité, rotation. Le store filesystem de dev n'offre pas ces garanties, et un
 * `cache:clear` agressif pourrait l'effacer — acceptable en dev, pas en prod.
 */
final class CacheSealStore implements SealStore
{
    public function __construct(private readonly CacheItemPoolInterface $sealStorePool)
    {
    }

    public function put(string $planRef, string $seed): void
    {
        $item = $this->sealStorePool->getItem($this->key($planRef));
        $item->set($seed);
        $this->sealStorePool->save($item);
    }

    public function get(string $planRef): string
    {
        $item = $this->sealStorePool->getItem($this->key($planRef));
        if (!$item->isHit()) {
            throw new \RuntimeException(sprintf('Graine absente pour le plan %s.', $planRef));
        }

        $seed = $item->get();
        if (!is_string($seed)) {
            throw new \RuntimeException(sprintf('Graine corrompue pour le plan %s.', $planRef));
        }

        return $seed;
    }

    public function forget(string $planRef): void
    {
        $this->sealStorePool->deleteItem($this->key($planRef));
    }

    /**
     * Les clés PSR-6 interdisent {}()/\@: — or un windowRef en contient (« : »).
     * On hashe donc la référence pour produire une clé sûre et stable.
     */
    private function key(string $planRef): string
    {
        return 'seal_'.hash('sha256', $planRef);
    }
}
