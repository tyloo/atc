<?php

declare(strict_types=1);

namespace Tyloo\Atc\Tests\Functional\Trait\InteractsWithApi;

use Override;
use Tyloo\Atc\ApiTestCase;
use Tyloo\Atc\Tests\Fixtures\Kernel\BareKernel;
use Tyloo\Atc\Trait\InteractsWithCache;
use Tyloo\Atc\Trait\InteractsWithHttpClient;
use Tyloo\Atc\Trait\InteractsWithMessenger;
use Tyloo\Atc\Trait\InteractsWithNotifier;
use PHPUnit\Framework\Attributes\Test;

/**
 * @covers \Tyloo\Atc\Trait\InteractsWithApi
 * @covers \Tyloo\Atc\Trait\InteractsWithCache
 * @covers \Tyloo\Atc\Trait\InteractsWithHttpClient
 * @covers \Tyloo\Atc\Trait\InteractsWithMessenger
 * @covers \Tyloo\Atc\Trait\InteractsWithNotifier
 *
 * Exercises the default override hooks against a minimal kernel:
 *
 * - InteractsWithApi::resolveDefaultHeaders returns []
 * - InteractsWithApi::resolveJsonSchemaBaseDir returns <kernel.project_dir>/tests/Schemas
 * - InteractsWithApi::resolveJsonSchemaValidator / resolveJsonPathExtractor build fresh instances
 * - InteractsWithHttpClient::resolveHttpClientStrict returns true
 * - InteractsWithMessenger::resolveMessengerTransports walks the auto-discovery branch
 */
final class BareKernelFallbacksTest extends ApiTestCase
{
    use InteractsWithCache;
    use InteractsWithHttpClient;
    use InteractsWithMessenger;
    use InteractsWithNotifier;

    #[Override]
    protected static function getKernelClass(): string
    {
        return BareKernel::class;
    }

    #[Test]
    public function default_headers_should_be_empty_array(): void
    {
        static::assertSame([], $this->apiClient()->defaultHeaders());
    }

    #[Test]
    public function request_round_trip_uses_fallback_validators(): void
    {
        $this->get('/ping')
            ->assertStatusOk()
            ->assertJsonContains(['ok' => true]);
    }

    #[Test]
    public function resolve_http_client_strict_defaults_to_true(): void
    {
        static::assertTrue($this->resolveHttpClientStrict());
    }

    #[Test]
    public function resolve_messenger_transports_returns_empty_when_none_configured(): void
    {
        static::assertSame([], $this->resolveMessengerTransports());
    }

    #[Test]
    public function resolve_json_schema_base_dir_uses_kernel_project_dir(): void
    {
        static::assertStringEndsWith('/tests/Schemas', $this->resolveJsonSchemaBaseDir());
    }
}
