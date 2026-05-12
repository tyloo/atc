<?php

declare(strict_types=1);

namespace Tyloo\Atc\Tests\Fixtures\MockTarget;

/**
 * Trivial fixture interface used as a mock target by ServiceMocker tests.
 */
interface ExampleService
{
    public function hello(): string;

    public function world(): string;
}
