<?php

declare(strict_types=1);

namespace Tyloo\Atc\Tests\Functional\Trait\InteractsWithProfiler;

use LogicException;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Tyloo\Atc\ApiTestCase;
use Tyloo\Atc\Tests\Fixtures\Kernel\TestKernel;
use Tyloo\Atc\Trait\InteractsWithProfiler;

/**
 * @covers \Tyloo\Atc\Trait\InteractsWithProfiler
 *
 * The default TestKernel does not enable `framework.profiler`, so calling
 * `withProfiling()` must fail fast with a clear, actionable error instead of
 * silently no-op'ing (which is Symfony's KernelBrowser default behaviour).
 */
final class ProfilerDisabledTest extends ApiTestCase
{
    use InteractsWithProfiler;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Test]
    public function with_profiling_throws_when_profiler_service_missing(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Symfony profiler is not enabled');

        $this->withProfiling();
    }
}
