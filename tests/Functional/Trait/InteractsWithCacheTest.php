<?php

declare(strict_types=1);

namespace Tyloo\Atc\Tests\Functional\Trait;

use Override;
use Tyloo\Atc\ApiTestCase;
use Tyloo\Atc\Tests\Fixtures\Kernel\TestKernel;
use Tyloo\Atc\Trait\InteractsWithCache;
use PHPUnit\Framework\Attributes\Test;

/**
 * @covers \Tyloo\Atc\Trait\InteractsWithCache
 *
 * Drives the cache helpers end-to-end via a booted kernel: assertions on
 * cache presence/absence and cache clearing across HTTP boundaries.
 */
final class InteractsWithCacheTest extends ApiTestCase
{
    use InteractsWithCache;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Test]
    public function cache_stores_value_via_request(): void
    {
        $this->post('/cache/greeting', json: ['value' => 'hello'])
            ->assertStatusOk()
            ->assertJsonPath('stored', 'hello');

        $this->assertCacheHas('greeting');
    }

    #[Test]
    public function cache_missing_passes_when_key_absent(): void
    {
        $this->assertCacheMissing('does-not-exist');
    }

    #[Test]
    public function clear_cache_resets_the_pool(): void
    {
        $this->post('/cache/x', json: ['value' => '1'])->assertStatusOk();
        $this->assertCacheHas('x');

        $this->clearCache();
        $this->assertCacheMissing('x');
    }
}
