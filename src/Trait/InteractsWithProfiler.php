<?php

declare(strict_types=1);

namespace Tyloo\Atc\Trait;

use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use LogicException;
use PHPUnit\Framework\Assert;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\Profiler\Profile;

/**
 * Exposes the Symfony Profiler for the most recent request. Requires
 * `framework.profiler` enabled in the kernel under test.
 *
 * @phpstan-require-extends WebTestCase
 * @phpstan-require-use InteractsWithApi
 */
trait InteractsWithProfiler
{
    /**
     * Enable profile collection for the next request.
     *
     * @throws LogicException When the `profiler` service is not registered in the kernel under test.
     *
     * @example $this->withProfiling(); $this->get('/items')->assertStatusOk();
     */
    public function withProfiling(): static
    {
        $browser = $this->kernelBrowserForProfiler();

        if (!$browser->getContainer()->has('profiler')) {
            throw new LogicException(
                'The Symfony profiler is not enabled in the kernel under test. '
                . 'Add `framework.profiler:` to your test framework config '
                . '(e.g. `framework.profiler.collect: false`) so withProfiling() can opt this request in.',
            );
        }

        $browser->enableProfiler();

        return $this;
    }

    /**
     * @throws LogicException When no profile was captured (profiler disabled, or `withProfiling()` not called).
     */
    public function profile(): Profile
    {
        $profile = $this->kernelBrowserForProfiler()->getProfile();

        if (!$profile instanceof Profile) {
            throw new LogicException(
                'No profile was captured for the last request. '
                . 'Call ->withProfiling() before the request, '
                . 'or set `framework.profiler.collect: true` in your kernel config.',
            );
        }

        return $profile;
    }

    /**
     * @throws LogicException|\PHPUnit\Framework\ExpectationFailedException
     *
     * @example $this->assertQueryCount(1);
     */
    public function assertQueryCount(int $expected): static
    {
        $actual = $this->doctrineCollector()->getQueryCount();
        Assert::assertSame($expected, $actual, sprintf('Expected exactly %d DB queries, %d were executed.', $expected, $actual));

        return $this;
    }

    /**
     * @throws LogicException|\PHPUnit\Framework\ExpectationFailedException
     *
     * @example $this->assertQueryCountLessThan(5);
     */
    public function assertQueryCountLessThan(int $upper): static
    {
        $actual = $this->doctrineCollector()->getQueryCount();
        Assert::assertLessThan($upper, $actual, sprintf('Expected fewer than %d DB queries, %d were executed.', $upper, $actual));

        return $this;
    }

    /**
     * @throws LogicException When the profile has no `db` collector.
     */
    private function doctrineCollector(): DoctrineDataCollector
    {
        $profile = $this->profile();

        if (!$profile->hasCollector('db')) {
            throw new LogicException('The Doctrine `db` data collector is not available. Install doctrine/doctrine-bundle and ensure the profiler is collecting DB queries.');
        }

        $collector = $profile->getCollector('db');
        assert($collector instanceof DoctrineDataCollector, 'Expected the `db` collector to be a DoctrineDataCollector.');

        return $collector;
    }

    private function kernelBrowserForProfiler(): KernelBrowser
    {
        $browser = self::getClient();
        assert($browser instanceof KernelBrowser, 'KernelBrowser must be available. Did your test case extend ApiTestCase / WebTestCase and call createClient()?');

        return $browser;
    }
}
