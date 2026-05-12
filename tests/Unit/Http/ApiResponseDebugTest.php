<?php

declare(strict_types=1);

namespace Tyloo\Atc\Tests\Unit\Http;

use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\VarDumper\VarDumper;
use Tyloo\Atc\Http\ApiResponse;
use Tyloo\Atc\Json\JsonPathExtractor;
use Tyloo\Atc\Json\JsonSchemaValidator;

/**
 * @covers \Tyloo\Atc\Http\ApiResponse
 *
 * Covers ApiResponse::dump()/dd(). Uses VarDumper::setHandler() to intercept
 * the output instead of letting it hit stdout, which keeps dd() testable
 * without spawning a separate process (the exit(1) is the only line
 * excluded from coverage).
 */
final class ApiResponseDebugTest extends TestCase
{
    /** @var list<mixed> */
    private static array $captured = [];

    #[Override]
    protected function setUp(): void
    {
        self::$captured = [];
        VarDumper::setHandler(static function (mixed $value): void {
            self::$captured[] = $value;
        });
    }

    #[Override]
    protected function tearDown(): void
    {
        VarDumper::setHandler(null);
    }

    /**
     * @param array<string, string> $headers
     */
    private function make(int $status = 200, array $headers = [], string $body = ''): ApiResponse
    {
        return new ApiResponse(
            new Response($body, $status, $headers),
            new JsonSchemaValidator(__DIR__),
            new JsonPathExtractor(),
            12.34,
        );
    }

    #[Test]
    public function dump_writes_status_headers_duration_and_decoded_body(): void
    {
        $response = $this->make(201, ['Content-Type' => 'application/json'], '{"id":1,"name":"Apple"}');

        $result = $response->dump();

        self::assertSame($response, $result);
        self::assertCount(1, self::$captured);

        $snapshot = self::$captured[0];
        self::assertIsArray($snapshot);
        self::assertSame(201, $snapshot['status']);
        self::assertSame(12.34, $snapshot['duration_ms']);
        self::assertSame(['id' => 1, 'name' => 'Apple'], $snapshot['body']);
        self::assertIsArray($snapshot['headers']);
        self::assertSame(['application/json'], $snapshot['headers']['content-type'] ?? null);
    }

    #[Test]
    public function dump_falls_back_to_raw_string_for_non_json_body(): void
    {
        $this->make(500, body: 'boom not json')->dump();

        $snapshot = self::$captured[0];
        self::assertIsArray($snapshot);
        self::assertSame('boom not json', $snapshot['body']);
    }

    #[Test]
    public function dump_uses_empty_string_for_empty_body(): void
    {
        $this->make(204)->dump();

        $snapshot = self::$captured[0];
        self::assertIsArray($snapshot);
        self::assertSame('', $snapshot['body']);
    }

    #[Test]
    public function dump_with_path_writes_only_the_jmespath_slice(): void
    {
        $this->make(body: '{"data":[{"id":1},{"id":2}]}')->dump('data[1].id');

        self::assertSame([2], self::$captured);
    }

    #[Test]
    public function dd_dumps_then_exits(): void
    {
        // Replace the handler so dump() throws before exit(1) is reached.
        VarDumper::setHandler(static function (mixed $value): void {
            throw new RuntimeException('dumped: ' . var_export($value, true));
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('dumped:');

        $this->make(body: '{"ok":true}')->dd();
    }
}
