<?php

declare(strict_types=1);

namespace Tyloo\Atc\Trait;

use LogicException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tyloo\Atc\Http\ApiClient;

/**
 * Adds `actingAs($user)` for stateless / token-based APIs.
 *
 * Override {@see authenticate()} to plug in your auth strategy (JWT, opaque
 * token, HMAC signature, ...). The default reads `$user->getApiToken()` and
 * attaches it as a Bearer token.
 *
 * @phpstan-require-extends WebTestCase
 */
trait InteractsWithAuth
{
    abstract protected function apiClient(): ApiClient;

    abstract protected function setApiClient(ApiClient $client): void;

    /**
     * Authenticate subsequent requests as `$user`.
     *
     * @example $this->actingAs($alice)->get('/api/me')->assertStatusOk();
     */
    public function actingAs(object $user): static
    {
        $this->setApiClient($this->authenticate($user, $this->apiClient()));

        return $this;
    }

    /**
     * Translate a user object into an authenticated {@see ApiClient}.
     *
     * Default: pull a Bearer token from `$user->getApiToken()`. Override
     * for any other strategy.
     *
     * @throws LogicException When the user has no `getApiToken()` or it returns a non-string.
     */
    protected function authenticate(object $user, ApiClient $client): ApiClient
    {
        if (!method_exists($user, 'getApiToken')) {
            throw new LogicException(sprintf(
                'Default actingAs() expects %s to expose a getApiToken(): string method. Override authenticate() in your test case to plug in a different strategy.',
                $user::class,
            ));
        }

        /** @var mixed $token */
        $token = $user->getApiToken();
        if (!is_string($token)) {
            throw new LogicException(sprintf('%s::getApiToken() must return a string, got %s.', $user::class, get_debug_type($token)));
        }

        return $client->withToken($token);
    }
}
