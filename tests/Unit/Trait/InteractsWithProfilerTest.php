<?php

declare(strict_types=1);

namespace Tyloo\Atc\Tests\Unit\Trait;

use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Tyloo\Atc\Trait\InteractsWithProfiler;
use PHPUnit\Framework\Attributes\Test;

/**
 * @covers \Tyloo\Atc\Trait\InteractsWithProfiler
 *
 * Unit-level coverage for InteractsWithProfiler error paths that are hard to
 * reach via the functional kernel (e.g. profile missing the `db` collector).
 */
final class InteractsWithProfilerTest extends TestCase
{
    #[Test]
    public function query_count_throws_when_db_collector_missing(): void
    {
        $stub = new class {
            use InteractsWithProfiler;

            public function profile(): Profile
            {
                return new Profile('test-token');
            }
        };

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Doctrine `db` data collector is not available');

        $stub->assertQueryCount(0);
    }
}
