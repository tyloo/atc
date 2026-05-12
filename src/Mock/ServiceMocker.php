<?php

declare(strict_types=1);

namespace Tyloo\Atc\Mock;

use Closure;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Container-side helper used by {@see \Tyloo\Atc\Trait\InteractsWithContainer}
 * to swap real services for PHPUnit mocks at runtime.
 *
 * Mock creation itself lives in the TestCase (via the trait) because PHPUnit's
 * `MockBuilder` methods are `protected`; this class only handles container
 * manipulation and class resolution.
 *
 * @internal
 */
final class ServiceMocker
{
    /**
     * @param ContainerInterface                                          $container          Test container whose services will be replaced.
     * @param Closure(class-string): MockObject                           $mockFactory        Builds a full mock for a class-string.
     * @param Closure(class-string, list<non-empty-string>): MockObject   $partialMockFactory Builds a partial mock stubbing only the listed methods.
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly Closure $mockFactory,
        private readonly Closure $partialMockFactory,
    ) {}

    /**
     * Replace `$serviceId` with a full mock; every method on the resolved
     * class/interface is stubbed and returns a default value.
     *
     * @param string $serviceId Container service id, FQCN, or interface name.
     *
     * @throws LogicException                                                                            When `$serviceId` cannot be resolved to a class or interface.
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException When resolving the existing service triggers a circular dependency.
     */
    public function mock(string $serviceId): MockObject
    {
        $class = $this->resolveClass($serviceId);
        $mock = ($this->mockFactory)($class);
        $this->set($serviceId, $mock);

        return $mock;
    }

    /**
     * Replace `$serviceId` with a partial mock: only the listed methods are
     * stubbed, every other method still runs the real implementation.
     *
     * @param string                 $serviceId     Container service id, FQCN, or interface name.
     * @param list<non-empty-string> $methodsToMock Names of the methods to stub.
     *
     * @throws LogicException                                                                            When `$serviceId` cannot be resolved to a class or interface.
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException When resolving the existing service triggers a circular dependency.
     */
    public function partialMock(string $serviceId, array $methodsToMock): MockObject
    {
        $class = $this->resolveClass($serviceId);
        $mock = ($this->partialMockFactory)($class, $methodsToMock);
        $this->set($serviceId, $mock);

        return $mock;
    }

    /**
     * Inject any object as the implementation of `$serviceId` in the container.
     *
     * Useful for swapping in stubs, fakes, or hand-written test doubles that
     * don't go through PHPUnit's MockBuilder.
     */
    public function set(string $serviceId, object $service): void
    {
        $this->container->set($serviceId, $service);
    }

    /**
     * Apply a map of `serviceId => factory` defaults to the container.
     *
     * Each factory is invoked exactly once and the result installed under the
     * matching service id. Use this for project-wide default doubles configured
     * via the test case's `defaultMocks()` method.
     *
     * @param array<string, callable(): object> $providers
     */
    public function applyDefaultMocks(array $providers): void
    {
        foreach ($providers as $serviceId => $factory) {
            $this->set($serviceId, $factory());
        }
    }

    /**
     * Resolve `$serviceId` to a concrete class-string suitable for mocking.
     *
     * The id is interpreted in this order: FQCN/interface name → existing
     * service in the container (whose class is read off the live instance).
     *
     * @return class-string
     *
     * @throws LogicException                                                                            When neither resolution path succeeds.
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException When resolving the existing service triggers a circular dependency.
     */
    private function resolveClass(string $serviceId): string
    {
        if (interface_exists($serviceId) || class_exists($serviceId)) {
            return $serviceId;
        }

        if ($this->container->has($serviceId)) {
            $original = $this->container->get($serviceId);
            if ($original !== null) {
                return $original::class;
            }
        }

        throw new LogicException(sprintf('Cannot mock service "%s": not a class/interface and no existing service to derive from.', $serviceId));
    }
}
