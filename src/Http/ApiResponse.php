<?php

declare(strict_types=1);

namespace Tyloo\Atc\Http;

use JsonException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\ExpectationFailedException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\VarDumper\VarDumper;
use Tyloo\Atc\Exception\JsonPathException;
use Tyloo\Atc\Exception\JsonSchemaException;
use Tyloo\Atc\Json\JsonPathExtractor;
use Tyloo\Atc\Json\JsonSchemaValidator;

/**
 * Fluent assertion wrapper around a Symfony {@see Response}. Returned by every
 * verb helper on {@see \Tyloo\Atc\Trait\InteractsWithApi} so tests can chain
 * assertions on status, headers, JSON shape, schema, and timing.
 */
final class ApiResponse
{
    /** @var array<mixed>|null */
    private ?array $decodedJson = null;
    private bool $jsonDecoded = false;

    public function __construct(
        private readonly Response $response,
        private readonly JsonSchemaValidator $schemaValidator,
        private readonly JsonPathExtractor $jsonPath,
        private readonly float $durationMs,
    ) {}

    // ───── Status assertions ─────

    /**
     * @throws ExpectationFailedException
     *
     * @example $this->get('/api/items')->assertStatus(418);
     */
    public function assertStatus(int $expected): self
    {
        $actual = $this->response->getStatusCode();
        Assert::assertSame($expected, $actual, sprintf('Expected status %d, got %d. Body: %s', $expected, $actual, $this->shortBody()));

        return $this;
    }

    /**
     * @throws ExpectationFailedException
     *
     * @example $this->get('/api/items/1')->assertStatusOk();
     */
    public function assertStatusOk(): self
    {
        return $this->assertStatus(200);
    }

    // ───── Header assertions ─────

    /**
     * @throws ExpectationFailedException
     *
     * @example $this->get('/api/items')->assertHeader('Content-Type', 'application/json');
     */
    public function assertHeader(string $name, string $value): self
    {
        $actual = $this->response->headers->get($name);
        Assert::assertSame($value, $actual, sprintf('Expected header %s to be %s, got %s.', $name, $value, var_export($actual, true)));

        return $this;
    }

    /**
     * @throws ExpectationFailedException
     *
     * @example $this->get('/api/items/1')->assertHeaderHas('ETag');
     */
    public function assertHeaderHas(string $name): self
    {
        Assert::assertTrue($this->response->headers->has($name), sprintf('Expected header %s to be present, but it is absent.', $name));

        return $this;
    }

    /**
     * @throws ExpectationFailedException
     *
     * @example $this->get('/api/items')->assertHeaderMissing('X-Debug-Token');
     */
    public function assertHeaderMissing(string $name): self
    {
        Assert::assertFalse($this->response->headers->has($name), sprintf('Expected header %s to be absent, but it is present.', $name));

        return $this;
    }

    // ───── JSON assertions ─────

    /**
     * Strict exact-match against the decoded JSON body.
     *
     * @param array<mixed> $expected
     *
     * @throws AssertionFailedError|ExpectationFailedException
     *
     * @example $this->get('/api/items/1')->assertJson(['id' => 1, 'name' => 'Apple']);
     */
    public function assertJson(array $expected): self
    {
        Assert::assertSame($expected, $this->decodeJson(), 'Response JSON did not exactly match expected.');

        return $this;
    }

    /**
     * Recursive subset match against the decoded JSON body.
     *
     * @param array<mixed> $subset
     *
     * @throws AssertionFailedError|ExpectationFailedException|\PHPUnit\Framework\Exception
     *
     * @example $this->get('/api/items/1')->assertJsonContains(['name' => 'Apple']);
     */
    public function assertJsonContains(array $subset): self
    {
        $this->assertSubset($subset, $this->decodeJson());

        return $this;
    }

    /**
     * @param mixed $expected Expected value, or a `callable(mixed): bool` predicate.
     *
     * @throws AssertionFailedError|ExpectationFailedException|JsonPathException
     *
     * @example
     *     $this->get('/api/items')->assertJsonPath('data[0].name', 'Apple');
     *     $this->get('/api/items')->assertJsonPath('data', fn($v) => count($v) > 0);
     */
    public function assertJsonPath(string $path, mixed $expected): self
    {
        /** @var mixed $actual */
        $actual = $this->jsonPath->extract($this->decodeJson(), $path);

        if (is_callable($expected) && !is_string($expected)) {
            Assert::assertTrue(self::isTruthy($expected($actual)), sprintf('JMESPath "%s" callable assertion failed. Actual: %s', $path, var_export($actual, true)));

            return $this;
        }

        Assert::assertSame($expected, $actual, sprintf('JMESPath "%s" did not match expected.', $path));

        return $this;
    }

    /**
     * @throws AssertionFailedError|ExpectationFailedException|JsonPathException
     *
     * @example $this->get('/api/items/1')->assertJsonMissingPath('password');
     */
    public function assertJsonMissingPath(string $path): self
    {
        Assert::assertFalse($this->jsonPath->exists($this->decodeJson(), $path), sprintf('Expected JMESPath "%s" to be missing, but it exists.', $path));

        return $this;
    }

    /**
     * Count items at `$path` (or root when null). `$path` must resolve to an array.
     *
     * @throws AssertionFailedError|ExpectationFailedException|JsonPathException|\PHPUnit\Framework\Exception|\PHPUnit\Framework\GeneratorNotSupportedException
     *
     * @example $this->get('/api/items')->assertJsonCount(3, 'data');
     */
    public function assertJsonCount(int $count, ?string $path = null): self
    {
        /** @var mixed $target */
        $target = $path === null ? $this->decodeJson() : $this->jsonPath->extract($this->decodeJson(), $path);

        Assert::assertIsArray($target, sprintf('JMESPath "%s" did not resolve to an array.', $path ?? '(root)'));
        Assert::assertCount($count, $target);

        return $this;
    }

    /**
     * Validate the decoded body against a JSON Schema file resolved relative
     * to the configured schemas directory (defaults to `tests/Schemas/`).
     *
     * @throws AssertionFailedError|ExpectationFailedException|\PHPUnit\Framework\Exception|JsonException
     *
     * @example $this->get('/api/items/1')->assertMatchesJsonSchema('item.json');
     */
    public function assertMatchesJsonSchema(string $schemaPath): self
    {
        try {
            $this->schemaValidator->validate($schemaPath, $this->decodeJson());
        } catch (JsonSchemaException $e) {
            Assert::fail($e->getMessage());
        }

        Assert::assertTrue(true, sprintf('JSON matches schema "%s".', $schemaPath));

        return $this;
    }

    // ───── Performance assertions ─────

    /**
     * @throws ExpectationFailedException
     *
     * @example $this->get('/api/items')->assertResponseTimeLessThan(200);
     */
    public function assertResponseTimeLessThan(int $milliseconds): self
    {
        Assert::assertLessThan($milliseconds, $this->durationMs, sprintf('Expected response time < %dms, got %.2fms.', $milliseconds, $this->durationMs));

        return $this;
    }

    /**
     * @throws ExpectationFailedException
     *
     * @example $this->get('/api/heavy')->assertResponseTimeGreaterThan(50);
     */
    public function assertResponseTimeGreaterThan(int $milliseconds): self
    {
        Assert::assertGreaterThan($milliseconds, $this->durationMs, sprintf('Expected response time > %dms, got %.2fms.', $milliseconds, $this->durationMs));

        return $this;
    }

    /**
     * @throws ExpectationFailedException
     *
     * @example $this->get('/api/items')->assertResponseTimeBetween(10, 500);
     */
    public function assertResponseTimeBetween(int $min, int $max): self
    {
        $duration = $this->durationMs;
        Assert::assertTrue($duration >= $min && $duration <= $max, sprintf('Expected response time between %dms and %dms, got %.2fms.', $min, $max, $duration));

        return $this;
    }

    // ───── Accessors ─────

    public function statusCode(): int
    {
        return $this->response->getStatusCode();
    }

    public function content(): string
    {
        return (string) $this->response->getContent();
    }

    /**
     * @return array<string, list<string|null>>
     */
    public function headers(): array
    {
        return $this->response->headers->all();
    }

    public function responseTimeMs(): float
    {
        return $this->durationMs;
    }

    public function raw(): Response
    {
        return $this->response;
    }

    /**
     * Return the decoded JSON body, or the value at `$path` when supplied.
     *
     * @throws AssertionFailedError|JsonPathException
     *
     * @example $name = $this->get('/api/items/1')->json('name');
     */
    public function json(?string $path = null): mixed
    {
        $decoded = $this->decodeJson();
        if ($path === null) {
            return $decoded;
        }

        return $this->jsonPath->extract($decoded, $path);
    }

    // ───── Debugging ─────

    /**
     * Dump the response (status, headers, duration, body) for inspection.
     * When `$path` is set, dumps just that JMESPath slice of the JSON body.
     *
     * @throws AssertionFailedError|JsonPathException
     *
     * @example $this->get('/api/items/1')->dump()->assertStatusOk();
     * @example $this->get('/api/items')->dump('data[0].id');
     */
    public function dump(?string $path = null): self
    {
        self::write($path === null ? $this->snapshot() : $this->json($path));

        return $this;
    }

    /**
     * Dump the response, then exit. Mirrors {@see dump()}, useful inside a chain
     * when you want to halt the test on the spot.
     *
     * @throws AssertionFailedError|JsonPathException
     *
     * @example $this->get('/api/items/1')->dd();
     */
    public function dd(?string $path = null): never
    {
        $this->dump($path);

        // @codeCoverageIgnoreStart
        exit(1);

        // @codeCoverageIgnoreEnd
    }

    // ───── Internals ─────

    /**
     * @return array{status: int, headers: array<string, list<string|null>>, duration_ms: float, body: mixed}
     */
    private function snapshot(): array
    {
        return [
            'status' => $this->response->getStatusCode(),
            'headers' => $this->response->headers->all(),
            'duration_ms' => $this->durationMs,
            'body' => $this->bodyForDump(),
        ];
    }

    private function bodyForDump(): mixed
    {
        $content = $this->content();
        if ($content === '') {
            return '';
        }

        try {
            return json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $content;
        }
    }

    private static function write(mixed $value): void
    {
        if (class_exists(VarDumper::class)) {
            VarDumper::dump($value);

            return;
        }

        // @codeCoverageIgnoreStart
        // @mago-ignore lint:no-debug-symbols — last-resort fallback when symfony/var-dumper is not installed.
        var_dump($value);

        // @codeCoverageIgnoreEnd
    }

    private function shortBody(int $max = 500): string
    {
        $content = $this->content();
        if (strlen($content) <= $max) {
            return $content;
        }

        return substr($content, 0, $max) . '… (truncated)';
    }

    /**
     * @return array<mixed>
     *
     * @throws AssertionFailedError
     */
    private function decodeJson(): array
    {
        if ($this->jsonDecoded) {
            return $this->decodedJson ?? [];
        }

        $content = $this->content();
        if ($content === '') {
            $this->jsonDecoded = true;

            return $this->decodedJson = [];
        }

        try {
            $decoded = self::asJsonArray(json_decode($content, true, flags: JSON_THROW_ON_ERROR), $this->shortBody());
        } catch (JsonException $e) {
            Assert::fail(sprintf('Response body is not valid JSON: %s. Body: %s', $e->getMessage(), $this->shortBody()));
        }

        $this->jsonDecoded = true;

        return $this->decodedJson = $decoded;
    }

    /**
     * `mixed` parameter avoids tripping mago's mixed-operand on `(bool) $mixed`.
     */
    private static function isTruthy(mixed $value): bool
    {
        return match (true) {
            $value === null, $value === false, $value === 0, $value === 0.0, $value === '', $value === '0', $value === [] => false,
            default => true,
        };
    }

    /**
     * @return array<mixed>
     *
     * @throws AssertionFailedError
     */
    private static function asJsonArray(mixed $decoded, string $bodyForError): array
    {
        if (!is_array($decoded)) {
            Assert::fail(sprintf('Response JSON is not an array/object. Body: %s', $bodyForError));
        }

        return $decoded;
    }

    /**
     * @param array<mixed> $subset
     * @param array<mixed> $actual
     *
     * @throws ExpectationFailedException|\PHPUnit\Framework\Exception
     */
    private function assertSubset(array $subset, array $actual): void
    {
        array_walk($subset, function (mixed $expectedValue, mixed $key) use ($actual): void {
            Assert::assertArrayHasKey($key, $actual, sprintf('Expected JSON to contain key "%s".', (string) $key));

            /** @var mixed $actualValue */
            $actualValue = $actual[$key] ?? null;

            if (is_array($expectedValue) && is_array($actualValue)) {
                $this->assertSubset($expectedValue, $actualValue);

                return;
            }

            Assert::assertSame($expectedValue, $actualValue, sprintf('JSON key "%s" did not match.', (string) $key));
        });
    }
}
