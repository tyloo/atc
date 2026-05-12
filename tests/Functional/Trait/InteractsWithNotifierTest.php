<?php

declare(strict_types=1);

namespace Tyloo\Atc\Tests\Functional\Trait;

use Override;
use Symfony\Component\Notifier\Notification\Notification;
use Tyloo\Atc\ApiTestCase;
use Tyloo\Atc\Tests\Fixtures\Kernel\TestKernel;
use Tyloo\Atc\Trait\InteractsWithNotifier;
use PHPUnit\Framework\Attributes\Test;

/**
 * @covers \Tyloo\Atc\Trait\InteractsWithNotifier
 *
 * Drives the notifier trait end-to-end: detecting that a notification
 * was sent, applying matcher closures, and listing the captured
 * notifications via sentNotifications().
 */
final class InteractsWithNotifierTest extends ApiTestCase
{
    use InteractsWithNotifier;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Test]
    public function notification_is_recorded(): void
    {
        $this->post('/notify', json: ['subject' => 'Welcome', 'email' => 'a@b.com'])
            ->assertStatusOk();

        $this->assertNotificationSent(
            static fn(Notification $n) => $n->getSubject() === 'Welcome',
        );
    }

    #[Test]
    public function no_notifications_sent_succeeds_when_idle(): void
    {
        $this->assertNoNotificationsSent();
    }

    #[Test]
    public function notification_sent_passes_without_matcher(): void
    {
        $this->post('/notify', json: ['subject' => 'Plain', 'email' => 'x@y.com'])
            ->assertStatusOk();

        $this->assertNotificationSent();
    }

    #[Test]
    public function sent_notifications_returns_recorded_notifications(): void
    {
        $this->post('/notify', json: ['subject' => 'A', 'email' => 'a@b.com'])
            ->assertStatusOk();
        $this->post('/notify', json: ['subject' => 'B', 'email' => 'c@d.com'])
            ->assertStatusOk();

        $sent = $this->sentNotifications();

        static::assertCount(2, $sent);
        $subjects = array_map(
            /** @param array{notification: Notification, recipients: list<\Symfony\Component\Notifier\Recipient\RecipientInterface>} $entry */
            static fn(array $entry): ?string => $entry['notification']->getSubject(),
            $sent,
        );
        static::assertSame(['A', 'B'], $subjects);
    }
}
