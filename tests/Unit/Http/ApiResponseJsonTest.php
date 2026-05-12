<?php

declare(strict_types=1);

namespace Tyloo\Atc\Tests\Unit\Http;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Tyloo\Atc\Http\ApiResponse;
use Tyloo\Atc\Json\JsonPathExtractor;
use Tyloo\Atc\Json\JsonSchemaValidator;
use PHPUnit\Framework\Attributes\Test;

/**
 * @covers \Tyloo\Atc\Http\ApiResponse
 *
 * Verifies the JSON-aware accessors and assertions on ApiResponse:
 * exact match, subset containment, JSONPath assertions, count helpers,
 * schema validation, body decoding, caching, and error surfaces for
 * malformed/oversized JSON payloads.
 */
final class ApiResponseJsonTest extends TestCase
{
    /**
     * @param array<mixed> $body
     */
    private function make(array $body, int $status = 200): ApiResponse
    {
        $response = new Response(
            json_encode($body, JSON_THROW_ON_ERROR),
            $status,
            ['Content-Type' => 'application/json'],
        );

        return new ApiResponse(
            $response,
            new JsonSchemaValidator(__DIR__ . '/../../Fixtures/Schemas'),
            new JsonPathExtractor(),
            1.0,
        );
    }

    #[Test]
    public function json_exact_match(): void
    {
        $this->make(['a' => 1, 'b' => 2])->assertJson(['a' => 1, 'b' => 2]);
    }

    #[Test]
    public function json_fails_on_difference(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->make(['a' => 1])->assertJson(['a' => 2]);
    }

    #[Test]
    public function json_contains_subset(): void
    {
        $this->make(['name' => 'Alice', 'age' => 30, 'extra' => 'x'])
            ->assertJsonContains(['name' => 'Alice', 'age' => 30]);
    }

    #[Test]
    public function json_contains_supports_nested_subset(): void
    {
        $this->make(['user' => ['name' => 'Alice', 'email' => 'a@b.com']])
            ->assertJsonContains(['user' => ['name' => 'Alice']]);
    }

    #[Test]
    public function json_contains_fails_when_key_missing(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->make(['name' => 'Alice'])->assertJsonContains(['email' => 'a@b.com']);
    }

    #[Test]
    public function json_path_with_scalar(): void
    {
        $this->make(['user' => ['email' => 'a@b.com']])
            ->assertJsonPath('user.email', 'a@b.com');
    }

    #[Test]
    public function json_path_with_callable(): void
    {
        $this->make(['id' => 'abc-123'])
            ->assertJsonPath('id', static fn($v) => is_string($v) && str_starts_with($v, 'abc-'));
    }

    #[Test]
    public function json_path_with_callable_returning_false(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->make(['id' => 1])->assertJsonPath('id', static fn($v) => is_string($v));
    }

    #[Test]
    public function json_missing_path(): void
    {
        $this->make(['a' => 1])->assertJsonMissingPath('b');
    }

    #[Test]
    public function json_missing_path_fails_when_present(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->make(['a' => 1])->assertJsonMissingPath('a');
    }

    #[Test]
    public function json_count_at_root(): void
    {
        $this->make([1, 2, 3])->assertJsonCount(3);
    }

    #[Test]
    public function json_count_at_path(): void
    {
        $this->make(['items' => [1, 2, 3, 4]])->assertJsonCount(4, 'items');
    }

    #[Test]
    public function matches_json_schema_passes_for_valid_data(): void
    {
        $this->make(['id' => 1, 'email' => 'a@b.com'])
            ->assertMatchesJsonSchema('user.json');
    }

    #[Test]
    public function matches_json_schema_fails_for_invalid_data(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->make(['id' => 'not-int'])->assertMatchesJsonSchema('user.json');
    }

    #[Test]
    public function json_returns_decoded_body(): void
    {
        static::assertSame(['a' => 1], $this->make(['a' => 1])->json());
    }

    #[Test]
    public function json_with_path_returns_value(): void
    {
        static::assertSame(
            'a@b.com',
            $this->make(['user' => ['email' => 'a@b.com']])->json('user.email'),
        );
    }

    #[Test]
    public function json_caches_decoded_body_across_calls(): void
    {
        $response = $this->make(['a' => 1]);

        // First call decodes; second call hits the cache branch.
        static::assertSame(['a' => 1], $response->json());
        static::assertSame(['a' => 1], $response->json());
    }

    #[Test]
    public function json_treats_empty_body_as_empty_array(): void
    {
        $response = new ApiResponse(
            new Response('', 204),
            new JsonSchemaValidator(__DIR__),
            new JsonPathExtractor(),
            1.0,
        );

        static::assertSame([], $response->json());
    }

    #[Test]
    public function json_fails_on_invalid_json_body(): void
    {
        $response = new ApiResponse(
            new Response('{not json', 200, ['Content-Type' => 'application/json']),
            new JsonSchemaValidator(__DIR__),
            new JsonPathExtractor(),
            1.0,
        );

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageMatches('/not valid JSON/');
        $response->assertJson([]);
    }

    #[Test]
    public function json_fails_on_truncated_invalid_json_body(): void
    {
        $longInvalid = '{' . str_repeat('a', 600);
        $response = new ApiResponse(
            new Response($longInvalid, 200, ['Content-Type' => 'application/json']),
            new JsonSchemaValidator(__DIR__),
            new JsonPathExtractor(),
            1.0,
        );

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageMatches('/truncated/');
        $response->assertJson([]);
    }

    #[Test]
    public function json_fails_when_body_is_scalar_json(): void
    {
        $response = new ApiResponse(
            new Response('"a string"', 200, ['Content-Type' => 'application/json']),
            new JsonSchemaValidator(__DIR__),
            new JsonPathExtractor(),
            1.0,
        );

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageMatches('/not an array\\/object/');
        $response->assertJson([]);
    }

    #[Test]
    public function json_count_fails_when_path_does_not_resolve_to_array(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageMatches('/did not resolve to an array/');
        $this->make(['scalar' => 'value'])->assertJsonCount(1, 'scalar');
    }
}
