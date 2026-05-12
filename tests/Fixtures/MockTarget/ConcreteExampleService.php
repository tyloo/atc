<?php

declare(strict_types=1);

namespace Tyloo\Atc\Tests\Fixtures\MockTarget;

use Override;

/**
 * Concrete fixture implementation of ExampleService used by ServiceMocker
 * tests to verify partial-mock behavior and id-to-class fallback resolution.
 */
class ConcreteExampleService implements ExampleService
{
    #[Override]
    public function hello(): string
    {
        return 'real-hello';
    }

    #[Override]
    public function world(): string
    {
        return 'real-world';
    }
}
