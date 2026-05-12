<?php

declare(strict_types=1);

namespace Tyloo\Atc\Tests\Functional\Trait\InteractsWithCache;

use Override;
use PHPUnit\Framework\Attributes\Test;
use Tyloo\Atc\ApiTestCase;
use Tyloo\Atc\Tests\Fixtures\Kernel\TestKernel;
use Tyloo\Atc\Trait\InteractsWithCache;

/**
 * @covers \Tyloo\Atc\Trait\InteractsWithCache
 *
 * Exercises the `continue` branch in setUpCache(): a configured pool id that
 * is not registered in the container is silently skipped.
 */
final class CachePoolIdsOverrideTest extends ApiTestCase
{
    use InteractsWithCache;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    /**
     * @return list<string>
     */
    protected function cachePoolIds(): array
    {
        return ['cache.app', 'cache.does-not-exist'];
    }

    #[Test]
    public function unknown_pool_ids_are_skipped(): void
    {
        $this->get('/ping')->assertStatusOk();

        // Only `cache.app` (which exists) should have been swapped; the bogus id was skipped.
        $this->clearCache('cache.app');
    }
}
