<?php

declare(strict_types=1);

namespace Tyloo\Atc\Tests\Unit\Mock;

use LogicException;
use Override;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Tyloo\Atc\Mock\ServiceMocker;
use Tyloo\Atc\Tests\Fixtures\MockTarget\ConcreteExampleService;
use Tyloo\Atc\Tests\Fixtures\MockTarget\ExampleService;
use PHPUnit\Framework\Attributes\Test;

/**
 * @covers \Tyloo\Atc\Mock\ServiceMocker
 *
 * Verifies the service-mocking helper that swaps container services for
 * full or partial PHPUnit MockObjects, replaces them with arbitrary
 * objects, and applies a default-mocks list. Also covers the fallback
 * paths when the service id is not a class-string.
 */
#[AllowMockObjectsWithoutExpectations]
final class ServiceMockerTest extends TestCase
{
    private function buildMocker(Container $container): ServiceMocker
    {
        return new ServiceMocker(
            $container,
            /** @param class-string $class */
            function (string $class): MockObject {
                $mock = $this->getMockBuilder($class)
                    ->disableOriginalConstructor()
                    ->getMock();
                assert($mock instanceof MockObject);

                return $mock;
            },
            /**
             * @param class-string $class
             * @param list<non-empty-string> $methods
             */
            function (string $class, array $methods): MockObject {
                $mock = $this->getMockBuilder($class)
                    ->onlyMethods($methods)
                    ->getMock();
                assert($mock instanceof MockObject);

                return $mock;
            },
        );
    }

    #[Test]
    public function mock_replaces_service_with_full_mock(): void
    {
        $container = new Container();
        $container->set(ExampleService::class, new class implements ExampleService {
            #[Override]
            public function hello(): string
            {
                return 'real';
            }

            #[Override]
            public function world(): string
            {
                return 'real';
            }
        });

        $mocker = $this->buildMocker($container);
        $mock = $mocker->mock(ExampleService::class);
        $mock->method('hello')->willReturn('mocked');

        $service = $container->get(ExampleService::class);
        static::assertInstanceOf(ExampleService::class, $service);
        static::assertSame('mocked', $service->hello());
    }

    #[Test]
    public function set_replaces_service_with_arbitrary_object(): void
    {
        $container = new Container();
        $replacement = new class implements ExampleService {
            #[Override]
            public function hello(): string
            {
                return 'X';
            }

            #[Override]
            public function world(): string
            {
                return 'Y';
            }
        };

        $mocker = $this->buildMocker($container);
        $mocker->set(ExampleService::class, $replacement);

        static::assertSame($replacement, $container->get(ExampleService::class));
    }

    #[Test]
    public function partial_mock_only_mocks_listed_methods(): void
    {
        $container = new Container();
        $container->set(ConcreteExampleService::class, new ConcreteExampleService());

        $mocker = $this->buildMocker($container);
        $mock = $mocker->partialMock(ConcreteExampleService::class, ['hello']);
        $mock->method('hello')->willReturn('mocked-hello');

        // hello is mocked, world keeps its real behavior
        $service = $container->get(ConcreteExampleService::class);
        static::assertInstanceOf(ConcreteExampleService::class, $service);
        static::assertSame('mocked-hello', $service->hello());
        static::assertSame('real-world', $service->world());
    }

    #[Test]
    public function apply_default_mocks_invokes_factories(): void
    {
        $container = new Container();
        $mocker = $this->buildMocker($container);

        $mocker->applyDefaultMocks([
            ExampleService::class => static fn() => new class implements ExampleService {
                #[Override]
                public function hello(): string
                {
                    return 'default-mock';
                }

                #[Override]
                public function world(): string
                {
                    return 'default-mock';
                }
            },
        ]);

        $service = $container->get(ExampleService::class);
        static::assertInstanceOf(ExampleService::class, $service);
        static::assertSame('default-mock', $service->hello());
    }

    #[Test]
    public function mock_resolves_class_from_existing_service_when_id_is_not_a_class(): void
    {
        $container = new Container();
        $container->set('app.example', new ConcreteExampleService());

        $mocker = $this->buildMocker($container);
        $mock = $mocker->mock('app.example');
        $mock->method('hello')->willReturn('mocked-from-id');

        $service = $container->get('app.example');
        static::assertInstanceOf(ConcreteExampleService::class, $service);
        static::assertSame('mocked-from-id', $service->hello());
    }

    #[Test]
    public function mock_throws_when_service_id_is_not_resolvable(): void
    {
        $mocker = $this->buildMocker(new Container());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/Cannot mock service "unknown\\.id"/');
        $mocker->mock('unknown.id');
    }
}
