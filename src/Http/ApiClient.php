<?php

declare(strict_types=1);

namespace Tyloo\Atc\Http;

use SensitiveParameter;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTP request builder backing {@see \Tyloo\Atc\Trait\InteractsWithApi}.
 *
 * Wraps Symfony's KernelBrowser to:
 *   - merge default headers with per-request headers
 *   - encode JSON bodies and add Content-Type/Accept automatically
 *   - measure wall-clock duration so {@see ApiResponse::assertResponseTimeLessThan()}
 *     can compare against it
 *
 * Treat this as an internal collaborator: prefer the trait's verb methods
 * (`get`, `post`, …) in tests rather than instantiating this directly.
 *
 * @internal
 */
final readonly class ApiClient
{
    /**
     * @param array<string, string> $defaultHeaders Headers applied to every request unless explicitly overridden.
     */
    public function __construct(
        private KernelBrowser $browser,
        private array $defaultHeaders = [],
    ) {}

    /**
     * Issue a request through the underlying KernelBrowser and return both the
     * Response and the wall-clock duration it took to produce it.
     *
     * Per-request `$headers` win over the constructor's default headers when keys
     * collide. If `$json` is non-null it is JSON-encoded (when not already a string)
     * and `Content-Type: application/json` is set unless the caller already provided
     * one. When a JSON body is present, `$formData` is ignored.
     *
     * @param string                $method   HTTP verb (`GET`, `POST`, `PATCH`, `PUT`, `DELETE`, …).
     * @param string                $uri      Request URI; query params from `$query` are appended with `?`/`&` as needed.
     * @param array<string, string> $headers  Request-scoped headers, merged on top of the default headers.
     * @param mixed                 $json     Optional JSON payload — encoded with `JSON_THROW_ON_ERROR`. A pre-encoded string is sent as-is.
     * @param array<string, string> $formData Form fields used when no JSON body is supplied.
     * @param array<string, mixed>  $files    Uploaded files keyed by field name, forwarded to the browser.
     * @param array<string, mixed>  $query    Query-string parameters serialised with `http_build_query()`.
     *
     * @return array{0: Response, 1: float} Tuple of the produced Response and the elapsed time in milliseconds.
     */
    public function request(string $method, string $uri, array $headers = [], mixed $json = null, array $formData = [], array $files = [], array $query = []): array
    {
        $effectiveHeaders = [...$this->defaultHeaders, ...$headers];
        $body = null;

        if ($json !== null) {
            $effectiveHeaders['Content-Type'] ??= 'application/json';
            $body = is_string($json) ? $json : json_encode($json, JSON_THROW_ON_ERROR);
        }

        $server = [];
        foreach ($effectiveHeaders as $name => $value) {
            $server['HTTP_' . str_replace('-', '_', strtoupper($name))] = $value;
        }
        if (array_key_exists('Content-Type', $effectiveHeaders)) {
            $server['CONTENT_TYPE'] = $effectiveHeaders['Content-Type'];
        }

        $parameters = $body !== null ? [] : $formData;

        $finalUri = $uri;
        if ($query !== []) {
            $finalUri .= (str_contains($uri, '?') ? '&' : '?') . http_build_query($query);
        }

        $start = microtime(true);
        $this->browser->request($method, $finalUri, $parameters, $files, $server, $body);
        $durationMs = (microtime(true) - $start) * 1000;

        $response = $this->browser->getResponse();

        return [$response, $durationMs];
    }

    /**
     * Return a new client with the given headers merged on top of the existing
     * defaults. The current instance is left untouched.
     *
     * @param array<string, string> $headers Headers to add or override.
     */
    public function withHeaders(array $headers): self
    {
        return new self($this->browser, [...$this->defaultHeaders, ...$headers]);
    }

    /**
     * Return a new client with an `Authorization: <scheme> <token>` header set.
     *
     * @param string $token  The bearer token (or equivalent credential).
     * @param string $scheme The auth scheme to advertise; defaults to `Bearer`.
     */
    public function withToken(#[SensitiveParameter] string $token, string $scheme = 'Bearer'): self
    {
        return $this->withHeaders(['Authorization' => sprintf('%s %s', $scheme, $token)]);
    }

    /**
     * @return array<string, string> Headers applied to every dispatched request.
     */
    public function defaultHeaders(): array
    {
        return $this->defaultHeaders;
    }

    /**
     * Expose the underlying KernelBrowser, e.g. for cookie/profiler inspection.
     */
    public function browser(): KernelBrowser
    {
        return $this->browser;
    }
}
