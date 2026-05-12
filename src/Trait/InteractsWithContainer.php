<?php

declare(strict_types=1);

namespace Tyloo\Atc\Trait;

use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tyloo\Atc\Mock\ServiceMocker;

/**
 * Provides a fluent service-mocking API on top of Symfony's test container.
 *
 * Lazy container interaction. ServiceMocker is built on first call so that
 * traits using this don't require the kernel to be booted at #[Before] time —
 * InteractsWithApi handles the kernel lifecycle.
 *
 * @phpstan-require-extends WebTestCase
 */
trait InteractsWithContainer
{
    private ?ServiceMocker $serviceMocker = null;
    private bool $defaultMocksApplied = false;

    /**
     * Reset the {@see ServiceMocker} and default-mock guard between tests.
     */
    #[Before]
    protected function resetServiceMockerState(): void
    {
        $this->serviceMocker = null;
        $this->defaultMocksApplied = false;
    }

    /**
     * Replace the container service with a fully mocked PHPUnit MockObject.
     *
     * The original constructor is bypassed so even services with mandatory
     * dependencies can be mocked. Use the returned mock to set expectations.
     *
     * @param string $id Service id (typically a class-string of the target).
     *
     * @return MockObject The mock instance now bound to the container.
     *
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     *
     * @example
     * ```
     * $payments = $this->mockService(PaymentGateway::class);
     * $payments->expects($this->once())
     *     ->method('charge')
     *     ->willReturn(new Receipt('txn_123'));
     *
     * $this->post('/api/orders', ['amount' => 100])->assertStatus(201);
     * ```
     */
    public function mockService(string $id): MockObject
    {
        return $this->serviceMocker()->mock($id);
    }

    /**
     * Partially mock a service: only the listed methods are stubbed; the rest call through.
     *
     * @param string                 $id            Service id (typically a class-string).
     * @param list<non-empty-string> $methodsToMock Names of methods to mock; all others remain real.
     *
     * @return MockObject The partial mock now bound to the container.
     *
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     *
     * @example
     * ```
     * $clock = $this->partialMockService(SystemClock::class, ['now']);
     * $clock->method('now')->willReturn(new DateTimeImmutable('2025-01-01'));
     * ```
     */
    public function partialMockService(string $id, array $methodsToMock): MockObject
    {
        return $this->serviceMocker()->partialMock($id, $methodsToMock);
    }

    /**
     * Bind a concrete object as the implementation for the given service id.
     *
     * Useful for swapping in fakes or test doubles you have constructed manually.
     *
     * @param string $id      Service id to override.
     * @param object $service Replacement service instance.
     *
     * @example
     * ```
     * $this->setService(Mailer::class, new InMemoryMailer());
     * ```
     */
    public function setService(string $id, object $service): void
    {
        $this->serviceMocker()->set($id, $service);
    }

    /**
     * Override in subclasses to register default mocks applied in setUp.
     *
     * Each callable is invoked once per test and its return value is bound to
     * the container under the corresponding service id. Useful for mocks that
     * every test in a TestCase needs (e.g. a payment gateway).
     *
     * @return array<string, callable(): object> Map of service id => factory.
     */
    protected function defaultMocks(): array
    {
        return [];
    }

    /**
     * Lazily build the {@see ServiceMocker} on first use, applying configured default mocks.
     */
    private function serviceMocker(): ServiceMocker
    {
        $mocker = $this->serviceMocker ?? new ServiceMocker(
            static::getContainer(),
            /** @param class-string $class */
            function (string $class): MockObject {
                $mock = $this->getMockBuilder($class)->disableOriginalConstructor()->getMock();
                assert($mock instanceof MockObject, 'PHPUnit MockBuilder::getMock() must return a MockObject.');

                return $mock;
            },
            /**
             * @param class-string $class
             * @param list<non-empty-string> $methods
             */
            function (string $class, array $methods): MockObject {
                $mock = $this->getMockBuilder($class)->onlyMethods($methods)->getMock();
                assert($mock instanceof MockObject, 'PHPUnit MockBuilder::getMock() must return a MockObject.');

                return $mock;
            },
        );
        $this->serviceMocker = $mocker;

        if (!$this->defaultMocksApplied) {
            $this->defaultMocksApplied = true;
            $this->applyDefaultMocksInternal($mocker);
        }

        return $mocker;
    }

    /**
     * Apply local {@see defaultMocks()} plus any service ids listed in
     * `tyloo_atc.default_mocks` to the {@see ServiceMocker}.
     *
     * Override {@see defaultMocks()} in your test case to register factories.
     */
    private function applyDefaultMocksInternal(ServiceMocker $mocker): void
    {
        $mocker->applyDefaultMocks($this->defaultMocks());
    }
}
