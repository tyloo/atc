<?php

declare(strict_types=1);

namespace Tyloo\Atc\Trait;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Before;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

/**
 * Replaces the notifier service with a recording stub that captures sent notifications.
 *
 * Both the `notifier` service id and the {@see NotifierInterface} alias are
 * swapped to a tiny in-memory recorder. Tests can assert that notifications
 * were sent and inspect the captured recipients without going through any
 * real channel transport.
 *
 * @phpstan-require-extends WebTestCase
 */
trait InteractsWithNotifier
{
    /** @var list<array{notification: Notification, recipients: list<RecipientInterface>}> */
    private array $sentNotifications = [];

    /**
     * Replace the notifier service(s) with a recording stub before each test.
     */
    #[Before]
    protected function setUpNotifier(): void
    {
        $this->sentNotifications = [];

        $container = static::getContainer();
        if (!$container->has('notifier')) {
            return;
        }

        $sentNotifications = &$this->sentNotifications;

        $recording = new class($sentNotifications) implements NotifierInterface {
            /**
             * @param list<array{notification: Notification, recipients: list<RecipientInterface>}> $store
             */
            public function __construct(
                private array &$store,
            ) {}

            #[Override]
            public function send(Notification $notification, RecipientInterface ...$recipients): void
            {
                $this->store[] = [
                    'notification' => $notification,
                    'recipients' => array_values($recipients),
                ];
            }
        };

        try {
            $container->set('notifier', $recording);

            // @codeCoverageIgnoreStart
            // Defensive: only fires if `notifier` was already resolved.
        } catch (InvalidArgumentException) {
            // @mago-expect lint:no-empty-catch-clause
            // Service was already resolved before the test boot — leave as-is.
        }
        // @codeCoverageIgnoreEnd
        if ($container->has(NotifierInterface::class)) {
            try {
                $container->set(NotifierInterface::class, $recording);
            } catch (InvalidArgumentException) {
                // @mago-expect lint:no-empty-catch-clause
                // Service was already resolved before the test boot — leave as-is.
            }
        }
    }

    /**
     * Assert that at least one notification was sent, optionally matching a predicate.
     *
     * The predicate receives both the {@see Notification} and the list of
     * recipients so tests can assert on either or both.
     *
     * @param (callable(Notification, list<RecipientInterface>): bool)|null $matcher Optional predicate.
     *
     * @throws \PHPUnit\Framework\ExpectationFailedException
     *
     * @example
     * ```
     * $this->post('/api/orders', ['amount' => 100]);
     * $this->assertNotificationSent(
     *     static fn(Notification $n) => $n->getSubject() === 'Order placed',
     * );
     * ```
     */
    public function assertNotificationSent(?callable $matcher = null): void
    {
        $matching = $matcher === null
            ? $this->sentNotifications
            : array_filter(
                $this->sentNotifications,
                /** @param array{notification: Notification, recipients: list<RecipientInterface>} $entry */
                static fn(array $entry): bool => $matcher($entry['notification'], $entry['recipients']),
            );

        Assert::assertGreaterThan(0, count($matching), 'Expected at least one notification to be sent.');
    }

    /**
     * Assert that no notification was sent during the test.
     *
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \PHPUnit\Framework\GeneratorNotSupportedException
     *
     * @example
     * ```
     * $this->get('/api/users')->assertStatusOk();
     * $this->assertNoNotificationsSent();
     * ```
     */
    public function assertNoNotificationsSent(): void
    {
        Assert::assertCount(0, $this->sentNotifications, 'Expected no notifications to be sent.');
    }

    /**
     * Return all captured notifications and their recipients in dispatch order.
     *
     * @return list<array{notification: Notification, recipients: list<RecipientInterface>}>
     */
    public function sentNotifications(): array
    {
        return $this->sentNotifications;
    }
}
