<?php

declare(strict_types=1);

namespace Tyloo\Atc\Trait;

use PHPUnit\Framework\Assert;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;

/**
 * Assertions over outgoing mail captured by Symfony's mailer test transport.
 *
 * Composes Symfony's {@see MailerAssertionsTrait} and exposes higher-level
 * helpers tailored for the JSON-API context: predicate-based matching and
 * recipient-address shortcuts.
 *
 * @phpstan-require-extends WebTestCase
 */
trait InteractsWithMailer
{
    use MailerAssertionsTrait;

    /**
     * Assert that at least one email was sent during the test, optionally matching a predicate.
     *
     * @param (callable(Email): bool)|null $matcher Optional predicate; when provided, only matching emails count.
     *
     * @throws \PHPUnit\Framework\ExpectationFailedException
     *
     * @example
     * ```
     * $this->post('/api/users', ['email' => 'alice@example.com']);
     * $this->assertEmailSent(static fn(Email $e) => str_contains((string) $e->getSubject(), 'Welcome'));
     * ```
     */
    public function assertEmailSent(?callable $matcher = null): void
    {
        $emails = $this->sentEmails();

        if ($matcher !== null) {
            $emails = array_values(array_filter($emails, $matcher));
        }

        Assert::assertGreaterThan(0, count($emails), 'Expected at least one email to be sent.');
    }

    /**
     * Assert that an email was sent to the given recipient address, optionally matching a predicate.
     *
     * @param string                       $address Recipient email address that must appear in the To: header.
     * @param (callable(Email): bool)|null $matcher Additional predicate for further constraints.
     *
     * @throws \PHPUnit\Framework\ExpectationFailedException
     *
     * @example
     * ```
     * $this->post('/api/users', ['email' => 'alice@example.com']);
     * $this->assertEmailSentTo('alice@example.com');
     * ```
     */
    public function assertEmailSentTo(string $address, ?callable $matcher = null): void
    {
        $emails = array_filter($this->sentEmails(), static fn(Email $email): bool => in_array($address, array_map(static fn($a) => $a->getAddress(), $email->getTo()), true));

        if ($matcher !== null) {
            $emails = array_filter($emails, $matcher);
        }

        Assert::assertGreaterThan(0, count($emails), sprintf('Expected an email sent to %s.', $address));
    }

    /**
     * Assert that no emails were sent during the test.
     *
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \PHPUnit\Framework\GeneratorNotSupportedException
     *
     * @example
     * ```
     * $this->get('/api/users')->assertStatusOk();
     * $this->assertNoEmailsSent();
     * ```
     */
    public function assertNoEmailsSent(): void
    {
        Assert::assertCount(0, $this->sentEmails(), 'Expected no emails sent.');
    }

    /**
     * Return all emails captured by Symfony's mailer test transport during this test.
     *
     * Filters out non-Email mailer messages defensively (the framework only
     * records {@see Email} instances in practice).
     *
     * @return list<Email>
     */
    public function sentEmails(): array
    {
        $messages = [];
        foreach ($this->getMailerMessages() as $message) {
            // @codeCoverageIgnoreStart
            // Defensive: Symfony's mailer logger only records Email messages.
            if (!$message instanceof Email) {
                continue;
            }
            // @codeCoverageIgnoreEnd

            $messages[] = $message;
        }

        return $messages;
    }
}
