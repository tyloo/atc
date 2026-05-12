<?php

declare(strict_types=1);

namespace Tyloo\Atc\Tests\Functional\Trait\InteractsWithContainer;

use Override;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Tyloo\Atc\ApiTestCase;
use Tyloo\Atc\Tests\Fixtures\Kernel\TestKernel;
use Tyloo\Atc\Tests\Fixtures\Service\Greeter;
use PHPUnit\Framework\Attributes\Test;

/**
 * @covers \Tyloo\Atc\Trait\InteractsWithContainer
 *
 * Drives the container-mocking helpers end-to-end against a booted kernel:
 * mockService(), setService(), and partialMockService() each replace a
 * container-managed service so HTTP requests observe the override.
 */
#[AllowMockObjectsWithoutExpectations]
final class InteractsWithContainerTest extends ApiTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Test]
    public function mock_service_replaces_container_service(): void
    {
        $greeter = $this->mockService(Greeter::class);
        $greeter->method('greet')->willReturn('Hi from mock');

        $this->get('/greet/World')
            ->assertStatusOk()
            ->assertJsonPath('message', 'Hi from mock');
    }

    #[Test]
    public function set_service_replaces_with_arbitrary_object(): void
    {
        $custom = new class extends Greeter {
            #[Override]
            public function greet(string $name): string
            {
                return "Yo, {$name}";
            }
        };

        $this->setService(Greeter::class, $custom);

        $this->get('/greet/World')
            ->assertStatusOk()
            ->assertJsonPath('message', 'Yo, World');
    }

    #[Test]
    public function partial_mock_only_overrides_listed_methods(): void
    {
        $mock = $this->partialMockService(Greeter::class, ['greet']);
        $mock->method('greet')->willReturn('partial');

        $this->get('/greet/X')
            ->assertStatusOk()
            ->assertJsonPath('message', 'partial');
    }
}
