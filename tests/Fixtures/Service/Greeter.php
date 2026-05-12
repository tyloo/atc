<?php

declare(strict_types=1);

namespace Tyloo\Atc\Tests\Fixtures\Service;

/**
 * Trivial fixture service used by InteractsWithContainer tests as a
 * mock/replacement target reachable through the /greet/{name} route.
 */
class Greeter
{
    public function greet(string $name): string
    {
        return "Hello, {$name}!";
    }
}
