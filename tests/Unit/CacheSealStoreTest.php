<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Integrity\CacheSealStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Vérifie le coffre de graines persistant : round-trip binaire, absence levée,
 * oubli, et clés contenant des caractères réservés PSR-6 (« : »).
 */
final class CacheSealStoreTest extends TestCase
{
    public function testGraineBinaireRoundTrip(): void
    {
        $store = new CacheSealStore(new ArrayAdapter());
        $seed = random_bytes(32);

        $store->put('cantine:2026-06-23:midi', $seed);

        self::assertSame($seed, $store->get('cantine:2026-06-23:midi'));
    }

    public function testGetSansGraineLeve(): void
    {
        $store = new CacheSealStore(new ArrayAdapter());

        $this->expectException(\RuntimeException::class);
        $store->get('inexistant');
    }

    public function testForgetSupprimeLaGraine(): void
    {
        $store = new CacheSealStore(new ArrayAdapter());
        $store->put('w1', random_bytes(32));

        $store->forget('w1');

        $this->expectException(\RuntimeException::class);
        $store->get('w1');
    }
}
