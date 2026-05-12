<?php

declare(strict_types=1);

namespace Tyloo\Atc\Tests\Functional\Trait;

use Override;
use Tyloo\Atc\ApiTestCase;
use Tyloo\Atc\Tests\Fixtures\Entity\Item;
use Tyloo\Atc\Tests\Fixtures\Kernel\TestKernel;
use Tyloo\Atc\Trait\InteractsWithDatabase;
use PHPUnit\Framework\Attributes\Test;

/**
 * @covers \Tyloo\Atc\Trait\InteractsWithDatabase
 *
 * Drives the database assertions end-to-end via Doctrine:
 * assertDatabaseHas, assertDatabaseMissing, and assertDatabaseCount each
 * exercise the schema bound to the Item fixture entity.
 */
final class InteractsWithDatabaseTest extends ApiTestCase
{
    use InteractsWithDatabase;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    private function ensureSchema(): void
    {
        $kernel = self::$kernel;
        assert($kernel instanceof TestKernel, 'TestKernel must be booted before schema creation.');
        $kernel->createSchema();
    }

    #[Test]
    public function database_has_finds_persisted_row(): void
    {
        $this->ensureSchema();

        $this->post('/items', json: ['name' => 'widget'])
            ->assertStatus(201);

        $this->assertDatabaseHas(Item::class, ['name' => 'widget']);
    }

    #[Test]
    public function database_missing_passes_when_row_absent(): void
    {
        $this->ensureSchema();

        $this->assertDatabaseMissing(Item::class, ['name' => 'never-created']);
    }

    #[Test]
    public function database_count_returns_row_count(): void
    {
        $this->ensureSchema();

        $this->post('/items', json: ['name' => 'a'])->assertStatus(201);
        $this->post('/items', json: ['name' => 'b'])->assertStatus(201);

        $this->assertDatabaseCount(Item::class, 2);
    }

    #[Test]
    public function database_count_handles_empty_table(): void
    {
        $this->ensureSchema();

        $this->assertDatabaseCount(Item::class, 0);
    }
}
