<?php

declare(strict_types=1);

namespace Tyloo\Atc\Trait;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Before;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * Asserts on messages dispatched onto Symfony Messenger in-memory transports.
 *
 * Transports come from `tyloo_atc.in_memory.messenger.transports` or are
 * discovered by scanning the test container for `messenger.transport.*`.
 *
 * @phpstan-require-extends WebTestCase
 */
trait InteractsWithMessenger
{
    /** @var list<string>|null */
    private ?array $messengerTransportNames = null;

    #[Before]
    protected function resetMessengerState(): void
    {
        $this->messengerTransportNames = null;
    }

    /**
     * @param class-string                  $messageClass
     * @param (callable(object): bool)|null $matcher      Optional predicate applied after class filtering.
     *
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     *
     * @example $this->assertMessageDispatched(WelcomeUser::class, fn(WelcomeUser $m) => $m->email === 'a@b.c');
     */
    public function assertMessageDispatched(string $messageClass, ?callable $matcher = null): void
    {
        $matching = $this->dispatchedMessages($messageClass);

        if ($matcher !== null) {
            $matching = array_values(array_filter($matching, $matcher));
        }

        Assert::assertGreaterThan(
            0,
            count($matching),
            sprintf('Expected message %s to be dispatched%s, none found.', $messageClass, $matcher !== null ? ' matching predicate' : ''),
        );
    }

    /**
     * @param class-string|null $messageClass FQCN to filter on, or null for any.
     *
     * @throws \PHPUnit\Framework\Exception|\PHPUnit\Framework\ExpectationFailedException|\PHPUnit\Framework\GeneratorNotSupportedException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     */
    public function assertNoMessagesDispatched(?string $messageClass = null): void
    {
        $messages = $this->dispatchedMessages($messageClass);
        Assert::assertCount(0, $messages, sprintf('Expected no %s messages, found %d.', $messageClass ?? 'any', count($messages)));
    }

    /**
     * @param class-string|null $messageClass FQCN to filter on, or null for any.
     *
     * @throws \PHPUnit\Framework\Exception|\PHPUnit\Framework\ExpectationFailedException|\PHPUnit\Framework\GeneratorNotSupportedException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     */
    public function assertMessagesDispatchedCount(int $count, ?string $messageClass = null): void
    {
        $messages = $this->dispatchedMessages($messageClass);
        Assert::assertCount($count, $messages, sprintf('Expected %d %s messages, got %d.', $count, $messageClass ?? 'any', count($messages)));
    }

    /**
     * @param class-string|null $messageClass
     *
     * @return list<object> Messages in dispatch order.
     *
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     */
    public function dispatchedMessages(?string $messageClass = null): array
    {
        $container = static::getContainer();
        $messages = [];

        $this->messengerTransportNames ??= $this->resolveMessengerTransports();

        foreach ($this->messengerTransportNames as $name) {
            // @codeCoverageIgnoreStart
            // Defensive: guards against state drift between resolution and access.
            if (!$container->has('messenger.transport.' . $name)) {
                continue;
            }
            $transport = $container->get('messenger.transport.' . $name);
            if (!$transport instanceof InMemoryTransport) {
                continue;
            }
            // @codeCoverageIgnoreEnd
            foreach ($transport->getSent() as $envelope) {
                assert($envelope instanceof Envelope, 'InMemoryTransport must yield Envelope instances.');
                $messages[] = $envelope->getMessage();
            }
        }

        if ($messageClass !== null) {
            $messages = array_values(array_filter($messages, static fn(object $m) => $m instanceof $messageClass));
        }

        return $messages;
    }

    /**
     * Override to pin the list of in-memory transports. Default: auto-discover.
     *
     * @return list<string>
     */
    protected function resolveMessengerTransports(): array
    {
        $container = static::getContainer();

        // Messenger transports are private, so the framework hides them from getServiceIds().
        // getRemovedIds() exposes the full private-service list — scan both to be safe.
        $candidates = [];
        foreach ($container->getServiceIds() as $id) {
            $candidates[] = $id;
        }
        if (method_exists($container, 'getRemovedIds')) {
            foreach (array_keys($container->getRemovedIds()) as $id) {
                $candidates[] = $id;
            }
        }

        // FrameworkBundle's serializer services share the `messenger.transport.*` prefix — exclude them.
        $reserved = ['native_php_serializer', 'symfony_serializer'];

        $names = [];
        foreach ($candidates as $id) {
            if (!is_string($id) || !str_starts_with($id, 'messenger.transport.')) {
                continue;
            }
            $name = substr($id, strlen('messenger.transport.'));
            if ($name === '' || str_contains($name, '.')) {
                continue;
            }
            if (in_array($name, $reserved, true)) {
                continue;
            }
            $names[] = $name;
        }

        return array_values(array_unique($names));
    }
}
