<?php

declare(strict_types=1);

namespace Tyloo\Atc\Tests\Functional\Trait\InteractsWithProfiler;

use LogicException;
use Override;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Tyloo\Atc\ApiTestCase;
use Tyloo\Atc\Tests\Fixtures\Kernel\ProfilerKernel;
use Tyloo\Atc\Trait\InteractsWithProfiler;
use PHPUnit\Framework\Attributes\Test;

/**
 * @covers \Tyloo\Atc\Trait\InteractsWithProfiler
 */
final class InteractsWithProfilerTest extends ApiTestCase
{
    use InteractsWithProfiler;

    #[Override]
    protected static function getKernelClass(): string
    {
        return ProfilerKernel::class;
    }

    #[Test]
    public function profile_returns_profile_after_request(): void
    {
        $this->withProfiling();
        $this->get('/ping')->assertStatusOk();

        $profile = $this->profile();

        self::assertInstanceOf(Profile::class, $profile);
    }

    #[Test]
    public function query_count_zero_on_non_db_route(): void
    {
        $this->withProfiling();

        $this->get('/ping')->assertStatusOk();

        $this->assertQueryCount(0);
    }

    #[Test]
    public function query_count_less_than_passes_under_threshold(): void
    {
        $this->ensureSchema();
        $this->withProfiling();

        $this->post('/items', json: ['name' => 'thing'])->assertStatus(201);

        // Insert + transaction + Doctrine metadata lookups; a generous bound
        // keeps the test stable across Symfony/Doctrine versions.
        $this->assertQueryCountLessThan(50);
    }

    #[Test]
    public function profile_throws_when_no_profile_captured(): void
    {
        $this->get('/ping')->assertStatusOk();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No profile was captured');

        $this->profile();
    }

    private function ensureSchema(): void
    {
        $kernel = self::$kernel;
        assert($kernel instanceof ProfilerKernel, 'ProfilerKernel must be booted before schema creation.');
        $kernel->createSchema();
    }
}
