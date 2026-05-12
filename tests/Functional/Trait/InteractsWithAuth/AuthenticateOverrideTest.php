<?php

declare(strict_types=1);

namespace Tyloo\Atc\Tests\Functional\Trait\InteractsWithAuth;

use Override;
use PHPUnit\Framework\Attributes\Test;
use Tyloo\Atc\ApiTestCase;
use Tyloo\Atc\Http\ApiClient;
use Tyloo\Atc\Tests\Fixtures\Auth\StaticTokenUser;
use Tyloo\Atc\Tests\Fixtures\Kernel\TestKernel;

/**
 * @covers \Tyloo\Atc\Trait\InteractsWithAuth
 *
 * Verifies that overriding `authenticate()` in a subclass replaces the
 * default Bearer-token strategy.
 */
final class AuthenticateOverrideTest extends ApiTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function authenticate(object $user, ApiClient $client): ApiClient
    {
        assert($user instanceof StaticTokenUser);

        return $client->withHeaders(['X-Custom' => 'hmac-' . $user->token]);
    }

    #[Test]
    public function authenticate_override_replaces_default_strategy(): void
    {
        $this->actingAs(new StaticTokenUser('secret'))
            ->get('/echo-headers')
            ->assertStatusOk()
            ->assertJsonPath('x_custom', 'hmac-secret');
    }
}
