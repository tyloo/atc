<?php

declare(strict_types=1);

namespace Tyloo\Atc\Tests\Functional\Trait\InteractsWithMessenger;

use Override;
use Tyloo\Atc\ApiTestCase;
use Tyloo\Atc\Tests\Fixtures\Kernel\TestKernel;
use Tyloo\Atc\Tests\Fixtures\Message\SendWelcomeEmail;
use Tyloo\Atc\Trait\InteractsWithMessenger;
use PHPUnit\Framework\Attributes\Test;

/**
 * @covers \Tyloo\Atc\Trait\InteractsWithMessenger
 *
 * Drives the messenger trait end-to-end against a booted kernel:
 * dispatch detection, count assertions, matcher predicates, and access
 * to the captured envelope list.
 */
final class InteractsWithMessengerTest extends ApiTestCase
{
    use InteractsWithMessenger;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Test]
    public function message_is_dispatched_to_bus(): void
    {
        $this->post('/dispatch-welcome', json: ['email' => 'alice@example.com'])
            ->assertStatusOk();

        $this->assertMessageDispatched(SendWelcomeEmail::class);
        $this->assertMessagesDispatchedCount(1, SendWelcomeEmail::class);
    }

    #[Test]
    public function message_matcher_callback_filters_dispatched(): void
    {
        $this->post('/dispatch-welcome', json: ['email' => 'bob@example.com'])
            ->assertStatusOk();

        $this->assertMessageDispatched(
            SendWelcomeEmail::class,
            static fn(object $m): bool => $m instanceof SendWelcomeEmail && $m->email === 'bob@example.com',
        );
    }

    #[Test]
    public function no_messages_dispatched_succeeds_when_bus_idle(): void
    {
        $this->assertNoMessagesDispatched();
    }

    #[Test]
    public function dispatched_messages_returns_all_when_unfiltered(): void
    {
        $this->post('/dispatch-welcome', json: ['email' => 'd@example.com'])
            ->assertStatusOk();

        $messages = $this->dispatchedMessages();
        static::assertCount(1, $messages);
        $first = $messages[0] ?? null;
        static::assertInstanceOf(SendWelcomeEmail::class, $first);
    }
}
