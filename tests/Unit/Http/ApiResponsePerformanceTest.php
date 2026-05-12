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
 * Verifies the response-time assertions on ApiResponse:
 * assertResponseTimeLessThan, assertResponseTimeGreaterThan, and the
 * inclusive assertResponseTimeBetween helper.
 */
final class ApiResponsePerformanceTest extends TestCase
{
    private function make(float $durationMs): ApiResponse
    {
        return new ApiResponse(
            new Response('', 200),
            new JsonSchemaValidator(__DIR__),
            new JsonPathExtractor(),
            $durationMs,
        );
    }

    #[Test]
    public function response_time_less_than_passes(): void
    {
        $this->make(50)->assertResponseTimeLessThan(100);
    }

    #[Test]
    public function response_time_less_than_fails(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->make(150)->assertResponseTimeLessThan(100);
    }

    #[Test]
    public function response_time_greater_than_passes(): void
    {
        $this->make(150)->assertResponseTimeGreaterThan(100);
    }

    #[Test]
    public function response_time_between_passes(): void
    {
        $this->make(150)->assertResponseTimeBetween(100, 200);
    }

    #[Test]
    public function response_time_between_fails_below(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->make(50)->assertResponseTimeBetween(100, 200);
    }

    #[Test]
    public function response_time_between_fails_above(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->make(250)->assertResponseTimeBetween(100, 200);
    }
}
