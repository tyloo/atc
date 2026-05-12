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
 * Verifies the status and header surface on ApiResponse.
 */
final class ApiResponseStatusTest extends TestCase
{
    /**
     * @param array<string, string> $headers
     */
    private function make(int $status = 200, array $headers = [], string $body = ''): ApiResponse
    {
        $response = new Response($body, $status, $headers);

        return new ApiResponse(
            $response,
            new JsonSchemaValidator(__DIR__),
            new JsonPathExtractor(),
            12.34,
        );
    }

    #[Test]
    public function status_passes_for_matching_code(): void
    {
        $this->make(418)->assertStatus(418);
    }

    #[Test]
    public function status_fails_for_mismatch(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->make(500)->assertStatus(200);
    }

    #[Test]
    public function status_ok_passes_for_200(): void
    {
        $this->make(200)->assertStatusOk();
    }

    #[Test]
    public function status_ok_fails_for_404(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->make(404)->assertStatusOk();
    }

    #[Test]
    public function header_passes_when_value_matches(): void
    {
        $this->make(200, ['Content-Type' => 'application/json'])
            ->assertHeader('Content-Type', 'application/json');
    }

    #[Test]
    public function header_fails_when_value_differs(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->make(200, ['Content-Type' => 'text/html'])
            ->assertHeader('Content-Type', 'application/json');
    }

    #[Test]
    public function header_missing_passes_when_absent(): void
    {
        $this->make(200)->assertHeaderMissing('X-Custom');
    }

    #[Test]
    public function header_missing_fails_when_present(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->make(200, ['X-Custom' => 'present'])->assertHeaderMissing('X-Custom');
    }

    #[Test]
    public function header_has_passes_when_present(): void
    {
        $this->make(200, ['X-Custom' => 'present'])->assertHeaderHas('X-Custom');
    }

    #[Test]
    public function header_has_fails_when_absent(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->make()->assertHeaderHas('X-Custom');
    }

    #[Test]
    public function truncates_long_response_body_in_status_failure_message(): void
    {
        $longBody = str_repeat('a', 600);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageMatches('/truncated/');
        $this->make(500, [], $longBody)->assertStatusOk();
    }

    #[Test]
    public function returns_self_for_chaining(): void
    {
        $response = $this->make();
        static::assertSame($response, $response->assertStatusOk());
    }

    #[Test]
    public function status_code_returns_response_status(): void
    {
        static::assertSame(201, $this->make(201)->statusCode());
    }

    #[Test]
    public function response_time_ms_returns_measured_duration(): void
    {
        static::assertSame(12.34, $this->make()->responseTimeMs());
    }

    #[Test]
    public function raw_returns_underlying_response(): void
    {
        $apiResponse = $this->make();
        static::assertInstanceOf(Response::class, $apiResponse->raw());
    }

    #[Test]
    public function content_returns_response_body(): void
    {
        static::assertSame('hello', $this->make(200, [], 'hello')->content());
    }

    #[Test]
    public function headers_returns_all_response_headers(): void
    {
        $headers = $this->make(200, ['X-Test' => 'value'])->headers();

        static::assertArrayHasKey('x-test', $headers);
        static::assertSame(['value'], $headers['x-test'] ?? null);
    }
}
