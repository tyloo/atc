<?php

declare(strict_types=1);

namespace Tyloo\Atc;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tyloo\Atc\Trait\InteractsWithApi;
use Tyloo\Atc\Trait\InteractsWithAuth;
use Tyloo\Atc\Trait\InteractsWithContainer;

/**
 * Fluent base test case for HTTP+JSON APIs in Symfony.
 *
 * Wraps Symfony's WebTestCase with three composed traits:
 *   - {@see InteractsWithApi} for HTTP verb shortcuts and fluent response assertions
 *   - {@see InteractsWithContainer} for service mocking
 *   - {@see InteractsWithAuth} for `actingAs()` and Authenticator-driven flows
 *
 * The kernel is booted once per test (via `#[Before]`) and rebooting is disabled so in-memory state
 * (SQLite, MockHttpClient, mocked services) survives across the multiple requests a single test may perform.
 */
abstract class ApiTestCase extends WebTestCase
{
    use InteractsWithApi;
    use InteractsWithContainer;
    use InteractsWithAuth;
}
