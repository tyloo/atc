<?php

declare(strict_types=1);

namespace Tyloo\Atc\Tests\Functional\Trait\InteractsWithAuth;

use LogicException;
use Override;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Tyloo\Atc\ApiTestCase;
use Tyloo\Atc\Tests\Fixtures\Auth\StaticTokenUser;
use Tyloo\Atc\Tests\Fixtures\Kernel\TestKernel;

/**
 * @covers \Tyloo\Atc\Trait\InteractsWithAuth
 */
final class InteractsWithAuthTest extends ApiTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Test]
    public function acting_as_attaches_bearer_token_from_user(): void
    {
        $this->actingAs(new StaticTokenUser('alice-token'))
            ->get('/echo-headers')
            ->assertStatusOk()
            ->assertJsonPath('authorization', 'Bearer alice-token');
    }

    #[Test]
    public function acting_as_throws_when_user_has_no_get_api_token_method(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('getApiToken(): string');

        $this->actingAs(new stdClass());
    }

    #[Test]
    public function acting_as_throws_when_get_api_token_returns_non_string(): void
    {
        $user = new class {
            public function getApiToken(): mixed
            {
                return 42;
            }
        };

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must return a string');

        $this->actingAs($user);
    }
}
