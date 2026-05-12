<?php

declare(strict_types=1);

namespace Tyloo\Atc\Tests\Fixtures\Kernel;

use Override;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Test kernel with `framework.profiler` enabled (collect: false). Used by
 * the InteractsWithProfiler functional tests to exercise per-request
 * profile enabling via `withProfiling()`. Inherits everything else from
 * {@see TestKernel}; only the framework config is overridden so the cache,
 * mailer and other collectors do not interfere with the global fixture
 * services that are not TraceableAdapter-compatible.
 */
final class ProfilerKernel extends TestKernel
{
    #[Override]
    protected function configureContainer(ContainerConfigurator $c): void
    {
        parent::configureContainer($c);

        $c->extension('framework', [
            'profiler' => [
                'collect' => false,
                'only_exceptions' => false,
            ],
        ]);
    }
}
