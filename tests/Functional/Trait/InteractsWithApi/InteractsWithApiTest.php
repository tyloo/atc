<?php

declare(strict_types=1);

namespace Tyloo\Atc\Tests\Functional\Trait\InteractsWithApi;

use Override;
use Tyloo\Atc\ApiTestCase;
use Tyloo\Atc\Tests\Fixtures\Kernel\TestKernel;
use PHPUnit\Framework\Attributes\Test;

/**
 * @covers \Tyloo\Atc\Trait\InteractsWithApi
 *
 * Drives the InteractsWithApi trait against a booted TestKernel. Covers
 * the GET/POST/PATCH/PUT/DELETE helpers, query strings, JSON bodies,
 * persistent headers/tokens, response timing, and the lastResponse() accessor.
 */
final class InteractsWithApiTest extends ApiTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Test]
    public function get_returns_fluent_response(): void
    {
        $this->get('/ping')
            ->assertStatusOk()
            ->assertJsonContains(['ok' => true])
            ->assertHeader('Content-Type', 'application/json');
    }

    #[Test]
    public function get_appends_query_string(): void
    {
        $this->get('/users', query: ['filter' => 'active'])
            ->assertStatusOk()
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function post_sends_json_body(): void
    {
        $this->post('/users', json: ['email' => 'new@example.com'])
            ->assertStatus(201)
            ->assertJsonPath('email', 'new@example.com')
            ->assertJsonPath('id', static fn($v) => is_int($v))
            ->assertHeader('Location', '/users/42');
    }

    #[Test]
    public function with_headers_persists_across_requests(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer abc'])
            ->get('/echo-headers')
            ->assertStatusOk()
            ->assertJsonPath('authorization', 'Bearer abc');
    }

    #[Test]
    public function with_token_sets_authorization_header(): void
    {
        $this->withToken('xyz')
            ->get('/echo-headers')
            ->assertStatusOk()
            ->assertJsonPath('authorization', 'Bearer xyz');
    }

    #[Test]
    public function response_time_is_measured(): void
    {
        $this->get('/ping')
            ->assertStatusOk()
            ->assertResponseTimeLessThan(5000);
    }

    #[Test]
    public function last_response_returns_most_recent(): void
    {
        $this->get('/ping')->assertStatusOk();

        $last = $this->lastResponse();
        static::assertNotNull($last);
        static::assertSame(200, $last->statusCode());
    }

    #[Test]
    public function last_response_is_null_before_any_request(): void
    {
        static::assertNull($this->lastResponse());
    }

    #[Test]
    public function patch_dispatches_with_body(): void
    {
        $this->patch('/users/7', json: ['email' => 'p@example.com'])
            ->assertStatusOk()
            ->assertJsonPath('method', 'PATCH')
            ->assertJsonPath('id', '7')
            ->assertJsonPath('patched.email', 'p@example.com');
    }

    #[Test]
    public function put_dispatches_with_body(): void
    {
        $this->put('/users/8', json: ['email' => 'q@example.com'])
            ->assertStatusOk()
            ->assertJsonPath('method', 'PUT')
            ->assertJsonPath('id', '8')
            ->assertJsonPath('replaced.email', 'q@example.com');
    }

    #[Test]
    public function delete_returns_fluent_response(): void
    {
        $this->delete('/users/9')
            ->assertStatus(204);
    }
}
