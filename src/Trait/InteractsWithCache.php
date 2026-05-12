<?php

declare(strict_types=1);

namespace Tyloo\Atc\Trait;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Before;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Replaces Symfony cache pools with in-memory {@see ArrayAdapter} instances and
 * exposes assertions over the resulting cache state.
 *
 * The list of pools to replace is `cache.app` by default and can be overridden
 * via the `tyloo_atc.in_memory.cache.pools` bundle parameter. Pools that have
 * already been resolved before the swap fall back silently to the real adapter.
 *
 * @phpstan-require-extends WebTestCase
 */
trait InteractsWithCache
{
    /** @var list<string> */
    private array $cachePoolIds = [];

    /**
     * Swap configured cache pools with in-memory ArrayAdapter instances before each test.
     */
    #[Before]
    protected function setUpCache(): void
    {
        $container = static::getContainer();

        $this->cachePoolIds = [];
        foreach ($this->cachePoolIds() as $id) {
            if (!$container->has($id)) {
                continue;
            }
            try {
                $container->set($id, new ArrayAdapter());
                $this->cachePoolIds[] = $id;

                // @codeCoverageIgnoreStart
                // Defensive: only fires if the pool was already resolved before
                // the test boot — depends on Symfony's compiled container state.
            } catch (InvalidArgumentException) {
                // @mago-expect lint:no-empty-catch-clause
                // Symfony test container throws when the cache pool was already
                // resolved. We cannot replace it without rebooting the kernel; the
                // test will exercise the real pool instead, which is acceptable.
            }

            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Assert that the given key exists in the cache pool.
     *
     * @param string      $key  Cache key to look up.
     * @param string|null $pool Pool service id; null uses the first configured/swapped pool.
     *
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     *
     * @example
     * ```
     * $this->get('/api/projects');
     * $this->assertCacheHas('projects.list');
     * ```
     */
    public function assertCacheHas(string $key, ?string $pool = null): void
    {
        Assert::assertTrue($this->resolveCachePool($pool)->hasItem($key), sprintf('Expected cache key "%s" to exist in pool %s.', $key, $pool ?? 'default'));
    }

    /**
     * Assert that the given key does not exist in the cache pool.
     *
     * @param string      $key  Cache key to look up.
     * @param string|null $pool Pool service id; null uses the first configured/swapped pool.
     *
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     *
     * @example
     * ```
     * $this->delete('/api/projects/42');
     * $this->assertCacheMissing('projects.42');
     * ```
     */
    public function assertCacheMissing(string $key, ?string $pool = null): void
    {
        Assert::assertFalse($this->resolveCachePool($pool)->hasItem($key), sprintf('Expected cache key "%s" to NOT exist in pool %s.', $key, $pool ?? 'default'));
    }

    /**
     * Override to swap additional cache pools. Default: just `cache.app`.
     *
     * @return list<string>
     */
    protected function cachePoolIds(): array
    {
        return ['cache.app'];
    }

    /**
     * Clear all entries from the given (or default) cache pool.
     *
     * @param string|null $pool Pool service id; null uses the first configured/swapped pool.
     *
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     *
     * @example
     * ```
     * $this->clearCache();
     * $this->assertCacheMissing('projects.list');
     * ```
     */
    public function clearCache(?string $pool = null): void
    {
        $this->resolveCachePool($pool)->clear();
    }

    /**
     * Look up the cache pool service for the given id, or the first swapped pool if null.
     *
     * @throws LogicException If the resolved service is not an {@see AdapterInterface}.
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     */
    private function resolveCachePool(?string $pool): AdapterInterface
    {
        $container = static::getContainer();
        $id = $pool ?? $this->cachePoolIds[0] ?? 'cache.app';

        $adapter = $container->get($id);
        // @codeCoverageIgnoreStart
        // Defensive: Symfony's cache pools always implement AdapterInterface.
        if (!$adapter instanceof AdapterInterface) {
            throw new LogicException(sprintf('Cache pool "%s" is not an AdapterInterface.', $id));
        }
        // @codeCoverageIgnoreEnd

        return $adapter;
    }
}
