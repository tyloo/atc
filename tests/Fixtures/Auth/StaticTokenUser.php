<?php

declare(strict_types=1);

namespace Tyloo\Atc\Tests\Fixtures\Auth;

use SensitiveParameter;

/**
 * Minimal "user" fixture exposing a getApiToken() method, used by the
 * InteractsWithAuth trait tests to drive BearerTokenAuthenticator.
 */
final readonly class StaticTokenUser
{
    public function __construct(
        #[SensitiveParameter]
        public string $token,
    ) {}

    public function getApiToken(): string
    {
        return $this->token;
    }
}
