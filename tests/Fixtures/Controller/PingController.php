<?php

declare(strict_types=1);

namespace Tyloo\Atc\Tests\Fixtures\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Minimal /ping for the BareKernel, used by the bundle-less trait tests.
 */
final class PingController
{
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['ok' => true]);
    }
}
