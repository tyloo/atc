<?php

declare(strict_types=1);

namespace Tyloo\Atc\Trait;

use LogicException;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use SensitiveParameter;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tyloo\Atc\Http\ApiClient;
use Tyloo\Atc\Http\ApiResponse;
use Tyloo\Atc\Json\JsonPathExtractor;
use Tyloo\Atc\Json\JsonSchemaValidator;

/**
 * HTTP verb shortcuts (`get`/`post`/`patch`/`put`/`delete`) plus a fluent
 * response wrapper for asserting JSON, headers, and status codes.
 *
 * @phpstan-require-extends WebTestCase
 */
trait InteractsWithApi
{
    private ?ApiClient $apiClient = null;
    private ?ApiResponse $lastResponse = null;
    private ?JsonSchemaValidator $jsonSchemaValidator = null;
    private ?JsonPathExtractor $jsonPathExtractor = null;
    private mixed $errorHandlerBeforeBoot = null;
    private mixed $exceptionHandlerBeforeBoot = null;

    /**
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     */
    #[Before]
    protected function bootApiClient(): void
    {
        // Snapshot top-of-stack handlers BEFORE Symfony boots so we can restore later.
        $this->errorHandlerBeforeBoot = set_error_handler(static fn() => null);
        restore_error_handler();
        $this->exceptionHandlerBeforeBoot = set_exception_handler(static fn() => null);
        restore_exception_handler();

        self::ensureKernelShutdown();
        $browser = static::createClient();
        assert($browser instanceof KernelBrowser, 'createClient() must return a KernelBrowser.');

        // Persist in-memory state (SQLite, transports, mocked services) across requests in the same test.
        $browser->disableReboot();

        $this->apiClient = new ApiClient($browser, $this->resolveDefaultHeaders());
        $this->jsonSchemaValidator = $this->resolveJsonSchemaValidator();
        $this->jsonPathExtractor = $this->resolveJsonPathExtractor();
        $this->lastResponse = null;
    }

    #[After]
    protected function shutdownApiClient(): void
    {
        self::ensureKernelShutdown();
        $this->apiClient = null;
        $this->lastResponse = null;
        $this->jsonSchemaValidator = null;
        $this->jsonPathExtractor = null;

        $this->popHandlersUntil(
            getter: 'set_error_handler',
            restorer: 'restore_error_handler',
            target: is_callable($this->errorHandlerBeforeBoot) ? $this->errorHandlerBeforeBoot : null,
        );
        $this->popHandlersUntil(
            getter: 'set_exception_handler',
            restorer: 'restore_exception_handler',
            target: is_callable($this->exceptionHandlerBeforeBoot) ? $this->exceptionHandlerBeforeBoot : null,
        );
    }

    /**
     * @param callable-string $getter   `set_error_handler` or `set_exception_handler`.
     * @param callable-string $restorer `restore_error_handler` or `restore_exception_handler`.
     */
    private function popHandlersUntil(string $getter, string $restorer, ?callable $target): void
    {
        // Bounded loop to avoid runaway iteration if the stack mismatches expectations.
        for ($i = 0; $i < 32; $i++) {
            /** @var callable|null $current */
            $current = $getter(static fn() => null);
            $restorer();
            if ($current === $target || $current === null) {
                return;
            }
            $restorer();
        }
    }

    /**
     * @param array<string, string> $headers Extra headers merged over the defaults.
     * @param array<string, mixed>  $query   Query-string parameters appended to $uri.
     *
     * @example $this->get('/api/users', query: ['page' => 2])->assertStatusOk();
     */
    public function get(string $uri, array $headers = [], array $query = []): ApiResponse
    {
        return $this->dispatch('GET', $uri, headers: $headers, query: $query);
    }

    /**
     * @param mixed                 $json     JSON body (encoded or as array). Null = form post.
     * @param array<string, string> $headers  Extra headers merged over the defaults.
     * @param array<string, string> $formData Form fields when $json is null.
     * @param array<string, mixed>  $files    Uploaded files keyed by form-field name.
     * @param array<string, mixed>  $query    Query-string parameters appended to $uri.
     *
     * @example $this->post('/api/users', ['email' => 'a@b.c'])->assertStatus(201);
     */
    public function post(string $uri, mixed $json = null, array $headers = [], array $formData = [], array $files = [], array $query = []): ApiResponse
    {
        return $this->dispatch('POST', $uri, $headers, $json, $formData, $files, $query);
    }

    /**
     * @param array<string, string> $headers Extra headers merged over the defaults.
     * @param array<string, mixed>  $query   Query-string parameters appended to $uri.
     *
     * @example $this->patch('/api/users/42', ['name' => 'Alice'])->assertStatusOk();
     */
    public function patch(string $uri, mixed $json = null, array $headers = [], array $query = []): ApiResponse
    {
        return $this->dispatch('PATCH', $uri, $headers, $json, [], [], $query);
    }

    /**
     * @param array<string, string> $headers Extra headers merged over the defaults.
     * @param array<string, mixed>  $query   Query-string parameters appended to $uri.
     *
     * @example $this->put('/api/users/42', ['email' => 'a@b.c'])->assertStatusOk();
     */
    public function put(string $uri, mixed $json = null, array $headers = [], array $query = []): ApiResponse
    {
        return $this->dispatch('PUT', $uri, $headers, $json, [], [], $query);
    }

    /**
     * @param array<string, string> $headers Extra headers merged over the defaults.
     * @param array<string, mixed>  $query   Query-string parameters appended to $uri.
     *
     * @example $this->delete('/api/users/42')->assertStatus(204);
     */
    public function delete(string $uri, array $headers = [], array $query = []): ApiResponse
    {
        return $this->dispatch('DELETE', $uri, headers: $headers, query: $query);
    }

    /**
     * Headers that persist for the rest of the test.
     *
     * @param array<string, string> $headers
     *
     * @example $this->withHeaders(['X-Tenant' => 'acme'])->get('/api/projects');
     */
    public function withHeaders(array $headers): static
    {
        $this->setApiClient($this->apiClient()->withHeaders($headers));

        return $this;
    }

    /**
     * Attach an `Authorization: <scheme> <token>` header to subsequent requests.
     *
     * @example $this->withToken('eyJhbGciOi...')->get('/api/me');
     */
    public function withToken(#[SensitiveParameter] string $token, string $scheme = 'Bearer'): static
    {
        $this->setApiClient($this->apiClient()->withToken($token, $scheme));

        return $this;
    }

    public function lastResponse(): ?ApiResponse
    {
        return $this->lastResponse;
    }

    /**
     * Override to send default headers with every request (e.g. tenant-aware tests).
     *
     * @return array<string, string>
     */
    protected function resolveDefaultHeaders(): array
    {
        return [];
    }

    protected function resolveJsonSchemaValidator(): JsonSchemaValidator
    {
        return new JsonSchemaValidator($this->resolveJsonSchemaBaseDir());
    }

    protected function resolveJsonPathExtractor(): JsonPathExtractor
    {
        return new JsonPathExtractor();
    }

    /**
     * Override to point at a different directory. Default: `<kernel.project_dir>/tests/Schemas`.
     */
    protected function resolveJsonSchemaBaseDir(): string
    {
        $container = static::getContainer();
        if ($container->hasParameter('kernel.project_dir')) {
            $param = $container->getParameter('kernel.project_dir');
            if (is_string($param)) {
                return $param . '/tests/Schemas';
            }
        }

        // @codeCoverageIgnoreStart
        // Defensive: every Symfony kernel sets `kernel.project_dir`.
        $cwd = getcwd();

        return ($cwd === false ? __DIR__ : $cwd) . '/tests/Schemas';

        // @codeCoverageIgnoreEnd
    }

    /**
     * @throws LogicException If called before {@see bootApiClient()} has run.
     */
    protected function apiClient(): ApiClient
    {
        if ($this->apiClient === null) {
            // @codeCoverageIgnoreStart
            throw new LogicException('ApiClient not initialised. Did bootApiClient() run?');

            // @codeCoverageIgnoreEnd
        }

        return $this->apiClient;
    }

    protected function setApiClient(ApiClient $client): void
    {
        $this->apiClient = $client;
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, string> $formData
     * @param array<string, mixed>  $files
     * @param array<string, mixed>  $query
     */
    private function dispatch(string $method, string $uri, array $headers = [], mixed $json = null, array $formData = [], array $files = [], array $query = []): ApiResponse
    {
        [$response, $durationMs] = $this->apiClient()->request($method, $uri, $headers, $json, $formData, $files, $query);

        return $this->lastResponse = new ApiResponse(
            $response,
            $this->jsonSchemaValidator ?? new JsonSchemaValidator($this->resolveJsonSchemaBaseDir()),
            $this->jsonPathExtractor ?? new JsonPathExtractor(),
            $durationMs,
        );
    }
}
