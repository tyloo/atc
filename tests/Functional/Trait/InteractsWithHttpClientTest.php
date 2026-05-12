<?php

declare(strict_types=1);

namespace Tyloo\Atc\Tests\Functional\Trait;

use Override;
use PHPUnit\Framework\AssertionFailedError;
use RuntimeException;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Tyloo\Atc\ApiTestCase;
use Tyloo\Atc\Tests\Fixtures\Kernel\TestKernel;
use Tyloo\Atc\Trait\InteractsWithHttpClient;
use PHPUnit\Framework\Attributes\Test;

/**
 * @covers \Tyloo\Atc\Trait\InteractsWithHttpClient
 *
 * Drives the in-memory HTTP client trait end-to-end: mocking responses,
 * recording outbound requests, exhausting the response queue, and reading
 * back the configured strict-mode parameter.
 */
final class InteractsWithHttpClientTest extends ApiTestCase
{
    use InteractsWithHttpClient;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Test]
    public function mocked_response_is_returned_to_app(): void
    {
        $this->mockHttpClient([
            new MockResponse(json_encode(['ok' => true], JSON_THROW_ON_ERROR), ['http_code' => 200]),
        ]);

        $this->get('/external')
            ->assertStatusOk()
            ->assertJsonPath('status', 200);

        $this->assertHttpRequestSent('GET', 'https://example.invalid/data');
    }

    #[Test]
    public function no_request_sent_succeeds_when_client_idle(): void
    {
        $this->mockHttpClient([]);
        $this->assertNoHttpRequestSent();
    }

    #[Test]
    public function mock_http_client_accepts_single_response(): void
    {
        $this->mockHttpClient(new MockResponse('{"single": true}', ['http_code' => 200]));

        $this->get('/external')
            ->assertStatusOk()
            ->assertJsonPath('body.single', true);
    }

    #[Test]
    public function http_request_sent_fails_when_no_match(): void
    {
        $this->mockHttpClient(new MockResponse('{}', ['http_code' => 200]));
        $this->get('/external')->assertStatusOk();

        $this->expectException(AssertionFailedError::class);
        $this->assertHttpRequestSent('POST', 'https://other.invalid/x');
    }

    #[Test]
    public function http_client_requests_returns_recorded_requests(): void
    {
        $this->mockHttpClient(new MockResponse('{}', ['http_code' => 200]));
        $this->get('/external')->assertStatusOk();

        $requests = $this->httpClientRequests();
        static::assertCount(1, $requests);
        $first = $requests[0] ?? null;
        static::assertNotNull($first);
        static::assertSame('GET', $first['method']);
        static::assertSame('https://example.invalid/data', $first['url']);
    }

    #[Test]
    public function mock_http_client_throws_when_no_more_responses(): void
    {
        $this->mockHttpClient([]);

        $client = static::getContainer()->get(HttpClientInterface::class);
        static::assertInstanceOf(HttpClientInterface::class, $client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/No more mocked HTTP responses/');
        $client->request('GET', 'https://example.invalid/exhausted');
    }

    #[Test]
    public function resolve_http_client_strict_defaults_to_true(): void
    {
        static::assertTrue($this->resolveHttpClientStrict());
    }
}
