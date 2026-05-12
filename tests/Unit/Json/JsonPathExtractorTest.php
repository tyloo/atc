<?php

declare(strict_types=1);

namespace Tyloo\Atc\Tests\Unit\Json;

use Override;
use PHPUnit\Framework\TestCase;
use Tyloo\Atc\Exception\JsonPathException;
use Tyloo\Atc\Json\JsonPathExtractor;
use PHPUnit\Framework\Attributes\Test;

/**
 * @covers \Tyloo\Atc\Json\JsonPathExtractor
 *
 * Verifies the JSONPath extractor: scalar and nested lookups, missing
 * paths returning null, exists() semantics, and error handling for
 * malformed expressions.
 */
final class JsonPathExtractorTest extends TestCase
{
    private JsonPathExtractor $extractor;

    #[Override]
    protected function setUp(): void
    {
        $this->extractor = new JsonPathExtractor();
    }

    #[Test]
    public function extracts_scalar_at_root(): void
    {
        // extract() returns mixed by contract (any JSON value); assertSame narrows it.
        // @mago-ignore analysis:mixed-assignment
        $result = $this->extractor->extract(['name' => 'Alice'], 'name');
        static::assertSame('Alice', $result);
    }

    #[Test]
    public function extracts_nested_value(): void
    {
        $data = ['data' => [['email' => 'a@b.com'], ['email' => 'c@d.com']]];
        static::assertSame('a@b.com', $this->extractor->extract($data, 'data[0].email'));
        static::assertSame('c@d.com', $this->extractor->extract($data, 'data[1].email'));
    }

    #[Test]
    public function returns_null_when_path_not_found(): void
    {
        static::assertNull($this->extractor->extract(['a' => 1], 'missing'));
    }

    #[Test]
    public function throws_on_invalid_path(): void
    {
        $this->expectException(JsonPathException::class);
        $this->extractor->extract(['a' => 1], "\$['unclosed");
    }

    #[Test]
    public function exists_returns_true_when_path_present(): void
    {
        static::assertTrue($this->extractor->exists(['a' => 1], 'a'));
        static::assertFalse($this->extractor->exists(['a' => 1], 'b'));
    }

    #[Test]
    public function exists_throws_on_invalid_path(): void
    {
        $this->expectException(JsonPathException::class);
        $this->extractor->exists(['a' => 1], "\$['unclosed");
    }
}
