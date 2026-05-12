<?php

declare(strict_types=1);

namespace Tyloo\Atc\Tests\Functional\Trait;

use Override;
use Symfony\Component\Mime\Email;
use Tyloo\Atc\ApiTestCase;
use Tyloo\Atc\Tests\Fixtures\Kernel\TestKernel;
use Tyloo\Atc\Trait\InteractsWithMailer;
use PHPUnit\Framework\Attributes\Test;

/**
 * @covers \Tyloo\Atc\Trait\InteractsWithMailer
 *
 * Drives the in-memory mailer assertions end-to-end: detecting that an
 * email was sent, filtering recipients and matchers, and listing the
 * captured Email instances.
 */
final class InteractsWithMailerTest extends ApiTestCase
{
    use InteractsWithMailer;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Test]
    public function email_is_sent_through_mailer(): void
    {
        $this->post('/send-email', json: ['to' => 'alice@example.com', 'subject' => 'Hi'])
            ->assertStatusOk();

        $this->assertEmailSent();
        $this->assertEmailSentTo('alice@example.com');
    }

    #[Test]
    public function email_matcher_filters_recorded_emails(): void
    {
        $this->post('/send-email', json: ['to' => 'bob@example.com', 'subject' => 'Welcome'])
            ->assertStatusOk();

        $this->assertEmailSent(static fn(Email $email): bool => $email->getSubject() === 'Welcome');
    }

    #[Test]
    public function no_emails_sent_succeeds_when_idle(): void
    {
        $this->assertNoEmailsSent();
    }

    #[Test]
    public function email_matcher_filters_assertion(): void
    {
        $this->post('/send-email', json: ['to' => 'matcher@example.com', 'subject' => 'Match'])
            ->assertStatusOk();

        $this->assertEmailSent(static fn(Email $email): bool => $email->getSubject() === 'Match');
    }

    #[Test]
    public function email_sent_to_matches_recipient_predicate(): void
    {
        $this->post('/send-email', json: ['to' => 'target@example.com', 'subject' => 'X'])
            ->assertStatusOk();

        $this->assertEmailSentTo(
            'target@example.com',
            static fn(Email $email): bool => $email->getSubject() === 'X',
        );
    }

    #[Test]
    public function sent_emails_returns_recorded_emails(): void
    {
        $this->post('/send-email', json: ['to' => 'one@example.com', 'subject' => 'One'])
            ->assertStatusOk();

        $emails = $this->sentEmails();

        static::assertNotEmpty($emails);
        $subjects = array_map(static fn(Email $email): ?string => $email->getSubject(), $emails);
        static::assertContains('One', $subjects);
    }
}
