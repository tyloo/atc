<?php

declare(strict_types=1);

namespace Tyloo\Atc\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Tyloo\Atc\Http\ApiClient;
use PHPUnit\Framework\Attributes\Test;

/**
 * @covers \Tyloo\Atc\Http\ApiClient
 *
 * Verifies the immutable ApiClient: withHeaders/withToken return new
 * instances, default headers merge correctly, and the underlying
 * KernelBrowser stays accessible via browser().
 */
final class ApiClientTest extends TestCase
{
    #[Test]
    public function with_headers_returns_new_instance(): void
    {
        $browser = $this->createStub(KernelBrowser::class);
        $client = new ApiClient($browser);

        $derived = $client->withHeaders(['X-Custom' => 'value']);

        static::assertNotSame($client, $derived);
    }

    #[Test]
    public function with_token_returns_new_instance_with_authorization_header(): void
    {
        $browser = $this->createStub(KernelBrowser::class);
        $client = new ApiClient($browser);

        $derived = $client->withToken('abc123');

        static::assertNotSame($client, $derived);
        static::assertSame(['Authorization' => 'Bearer abc123'], $derived->defaultHeaders());
    }

    #[Test]
    public function with_token_supports_custom_scheme(): void
    {
        $browser = $this->createStub(KernelBrowser::class);
        $client = new ApiClient($browser);

        $derived = $client->withToken('abc123', 'Token');

        static::assertSame(['Authorization' => 'Token abc123'], $derived->defaultHeaders());
    }

    #[Test]
    public function with_headers_merges_with_default_headers(): void
    {
        $browser = $this->createStub(KernelBrowser::class);
        $client = new ApiClient($browser, ['Accept' => 'application/json']);

        $derived = $client->withHeaders(['X-Custom' => 'value']);

        static::assertSame(
            ['Accept' => 'application/json', 'X-Custom' => 'value'],
            $derived->defaultHeaders(),
        );
    }

    #[Test]
    public function browser_accessor_returns_underlying_browser(): void
    {
        $browser = $this->createStub(KernelBrowser::class);
        $client = new ApiClient($browser);

        static::assertSame($browser, $client->browser());
    }
}
