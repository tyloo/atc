<?php

declare(strict_types=1);

namespace Tyloo\Atc\Tests\Functional;

use Override;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tyloo\Atc\Tests\Fixtures\Kernel\TestKernel;
use PHPUnit\Framework\Attributes\Test;

/**
 * @covers \Tyloo\Atc\Tests\Fixtures\Kernel\TestKernel
 *
 * Smoke test that confirms the test fixtures' kernel boots cleanly. The
 * setUp/tearDown bookends snapshot global error/exception handlers so the
 * kernel's debug stack is fully unwound between tests.
 */
final class KernelBootTest extends KernelTestCase
{
    private mixed $errorHandlerBeforeBoot = null;
    private mixed $exceptionHandlerBeforeBoot = null;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        // Snapshot top-of-stack handlers so we can restore after Symfony's debug stack registers its own.
        $this->errorHandlerBeforeBoot = set_error_handler(static fn() => null);
        restore_error_handler();
        $this->exceptionHandlerBeforeBoot = set_exception_handler(static fn() => null);
        restore_exception_handler();
    }

    #[Override]
    protected function tearDown(): void
    {
        self::ensureKernelShutdown();
        parent::tearDown();

        $this->popHandlersUntil(
            getter: 'set_error_handler',
            restorer: 'restore_error_handler',
            target: is_callable($this->errorHandlerBeforeBoot) ? $this->errorHandlerBeforeBoot : null,
        );
        $this->popHandlersUntil(
            getter: 'set_exception_handler',
            restorer: 'restore_exception_handler',
            target: is_callable($this->exceptionHandlerBeforeBoot) ? $this->exceptionHandlerBeforeBoot : null,
        );
    }

    #[Test]
    public function kernel_boots(): void
    {
        $kernel = self::bootKernel();
        static::assertSame('test', $kernel->getEnvironment());
    }

    /**
     * @param callable-string $getter
     * @param callable-string $restorer
     */
    private function popHandlersUntil(string $getter, string $restorer, ?callable $target): void
    {
        for ($i = 0; $i < 32; $i++) {
            /** @var callable|null $current */
            $current = $getter(static fn() => null);
            $restorer();
            if ($current === $target || $current === null) {
                return;
            }
            $restorer();
        }
    }
}
