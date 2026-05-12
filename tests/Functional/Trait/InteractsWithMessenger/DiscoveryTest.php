<?php

declare(strict_types=1);

namespace Tyloo\Atc\Tests\Functional\Trait\InteractsWithMessenger;

use Override;
use Tyloo\Atc\ApiTestCase;
use Tyloo\Atc\Tests\Fixtures\Kernel\MessengerDiscoveryKernel;
use Tyloo\Atc\Trait\InteractsWithMessenger;
use PHPUnit\Framework\Attributes\Test;

/**
 * @covers \Tyloo\Atc\Trait\InteractsWithMessenger
 *
 * Drives the InteractsWithMessenger discovery branch — bundle config provides
 * an empty transports list, so the trait walks getServiceIds()/getRemovedIds()
 * to find `messenger.transport.*` IDs.
 */
final class DiscoveryTest extends ApiTestCase
{
    use InteractsWithMessenger;

    #[Override]
    protected static function getKernelClass(): string
    {
        return MessengerDiscoveryKernel::class;
    }

    #[Test]
    public function resolve_messenger_transports_discovers_in_memory_transports(): void
    {
        $names = $this->resolveMessengerTransports();

        // Framework config registers an `async` transport — the discovery
        // branch should pick it up via getRemovedIds() since messenger
        // transports are private services.
        static::assertContains('async', $names);
    }
}
