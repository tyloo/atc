<?php

declare(strict_types=1);

namespace Tyloo\Atc\Tests\Functional\Trait\InteractsWithContainer;

use Override;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use stdClass;
use Tyloo\Atc\ApiTestCase;
use Tyloo\Atc\Tests\Fixtures\Kernel\TestKernel;
use Tyloo\Atc\Tests\Fixtures\Service\Greeter;
use PHPUnit\Framework\Attributes\Test;

/**
 * @covers \Tyloo\Atc\Trait\InteractsWithContainer
 *
 * Exercises the InteractsWithContainer::defaultMocks() override path. Lives in
 * its own class so the override only applies here — registering a default mock
 * for a service that another test has already initialized would throw.
 */
#[AllowMockObjectsWithoutExpectations]
final class DefaultMocksTest extends ApiTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    /**
     * @return array<string, callable(): object>
     */
    #[Override]
    protected function defaultMocks(): array
    {
        return [
            Greeter::class => static fn(): Greeter => new class extends Greeter {
                #[Override]
                public function greet(string $name): string
                {
                    return "Default-mock greets {$name}";
                }
            },
        ];
    }

    #[Test]
    public function default_mocks_override_is_applied_on_first_mocker_access(): void
    {
        // Default mocks are applied lazily on first serviceMocker access. Touch
        // a benign service to trigger that.
        $this->setService('default-mock-trigger', new stdClass());

        $this->get('/greet/Bob')
            ->assertStatusOk()
            ->assertJsonPath('message', 'Default-mock greets Bob');
    }
}
