<?php

declare(strict_types=1);

namespace Tyloo\Atc\Trait;

use InvalidArgumentException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Before;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Replaces Symfony's outbound {@see HttpClientInterface} with a {@see MockHttpClient}
 * driven by a configured queue of {@see MockResponse} instances, and exposes
 * assertions over the captured outbound requests.
 *
 * Useful for testing controllers and services that talk to third-party APIs
 * without making real network calls.
 *
 * @phpstan-require-extends WebTestCase
 */
trait InteractsWithHttpClient
{
    /** @var list<array{method: string, url: string, options: array<string, mixed>}> */
    private array $httpRequests = [];
    private bool $httpClientPrepared = false;

    /**
     * Reset the recorded request log and prepared-state flag between tests.
     */
    #[Before]
    protected function resetHttpClientState(): void
    {
        $this->httpRequests = [];
        $this->httpClientPrepared = false;
    }

    /**
     * Install a {@see MockHttpClient} that replays the supplied {@see MockResponse} queue.
     *
     * Each outbound HTTP call consumes the next response in the iterator;
     * exhausting the queue before all calls complete throws a RuntimeException
     * to surface unexpected outbound traffic.
     *
     * @param iterable<MockResponse>|MockResponse $responses Single response or iterable of responses to replay in order.
     *
     * @example
     * ```
     * $this->mockHttpClient([
     *     new MockResponse(json_encode(['ok' => true]), ['http_code' => 200]),
     * ]);
     *
     * $this->post('/api/webhooks/forward')->assertStatusOk();
     * $this->assertHttpRequestSent('POST', 'https://example.com/webhook');
     * ```
     */
    public function mockHttpClient(iterable|MockResponse $responses): void
    {
        $iterable = is_iterable($responses) ? $responses : [$responses];
        $iterator = (static function () use ($iterable) {
            foreach ($iterable as $response) {
                yield $response;
            }
        })();

        $this->httpRequests = [];

        $callback = function (string $method, string $url, array $options) use ($iterator): MockResponse {
            // MockHttpClient always invokes the callback with associative-string keyed options.
            // @mago-expect analysis:property-type-coercion
            $this->httpRequests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            if (!$iterator->valid()) {
                throw new RuntimeException(sprintf('No more mocked HTTP responses available for %s %s.', $method, $url));
            }

            $response = $iterator->current();
            $iterator->next();

            return $response;
        };

        $this->installHttpClient(new MockHttpClient($callback));
    }

    /**
     * Assert that an outbound HTTP request was made matching the method and URL.
     *
     * Method matching is case-insensitive; URL matching is exact (string equality).
     *
     * @param string $method HTTP method (case-insensitive).
     * @param string $url    Full URL the application is expected to have called.
     *
     * @throws \PHPUnit\Framework\AssertionFailedError
     * @throws \PHPUnit\Framework\ExpectationFailedException
     *
     * @example
     * ```
     * $this->mockHttpClient([new MockResponse('', ['http_code' => 204])]);
     * $this->post('/api/notify')->assertStatusOk();
     * $this->assertHttpRequestSent('POST', 'https://hooks.example/notify');
     * ```
     */
    public function assertHttpRequestSent(string $method, string $url): void
    {
        foreach ($this->httpRequests as $request) {
            if (!(strcasecmp($request['method'], $method) === 0 && $request['url'] === $url)) {
                continue;
            }

            Assert::assertTrue(true);

            return;
        }

        Assert::fail(sprintf('No HTTP %s request sent to %s.', $method, $url));
    }

    /**
     * Assert that no outbound HTTP request was made during the test.
     *
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \PHPUnit\Framework\GeneratorNotSupportedException
     *
     * @example
     * ```
     * $this->mockHttpClient([]);
     * $this->get('/api/status')->assertStatusOk();
     * $this->assertNoHttpRequestSent();
     * ```
     */
    public function assertNoHttpRequestSent(): void
    {
        Assert::assertCount(0, $this->httpRequests, 'Expected no HTTP requests sent.');
    }

    /**
     * Return the full list of outbound HTTP requests captured by {@see mockHttpClient()}.
     *
     * @return list<array{method: string, url: string, options: array<string, mixed>}>
     */
    public function httpClientRequests(): array
    {
        return $this->httpRequests;
    }

    /**
     * Override to allow unexpected outbound HTTP requests instead of failing the test.
     */
    protected function resolveHttpClientStrict(): bool
    {
        return true;
    }

    /**
     * Bind the supplied {@see MockHttpClient} into the test container.
     *
     * Replaces the {@see HttpClientInterface} alias first; if that has already
     * been resolved, falls back to overriding the underlying `http_client`
     * service id.
     */
    private function installHttpClient(MockHttpClient $client): void
    {
        $this->httpClientPrepared = true;

        $container = static::getContainer();
        // Replace through the HttpClientInterface alias only — replacing the underlying
        // 'http_client' service can throw "service is already initialized" if Symfony
        // booted the alias chain before us. Setting the alias is enough for autowiring.
        if ($container->has(HttpClientInterface::class)) {
            try {
                $container->set(HttpClientInterface::class, $client);

                // @codeCoverageIgnoreStart
                // Defensive: only fires if HttpClientInterface was already
                // resolved; depends on Symfony container compile state.
            } catch (InvalidArgumentException) {
                if ($container->has('http_client')) {
                    $container->set('http_client', $client);
                }
            }

            // @codeCoverageIgnoreEnd
        }
    }
}
