<?php

declare(strict_types=1);

namespace Tyloo\Atc\Tests\Fixtures\Message;

/**
 * Fixture messenger message dispatched by the JsonController test endpoint
 * to drive the InteractsWithMessenger assertions.
 */
final readonly class SendWelcomeEmail
{
    public function __construct(
        public string $email,
    ) {}
}
