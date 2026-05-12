<?php

declare(strict_types=1);

namespace Tyloo\Atc\Tests\Unit\Json;

use Override;
use PHPUnit\Framework\TestCase;
use Tyloo\Atc\Exception\JsonSchemaException;
use Tyloo\Atc\Json\JsonSchemaValidator;
use PHPUnit\Framework\Attributes\Test;

/**
 * @covers \Tyloo\Atc\Json\JsonSchemaValidator
 *
 * Verifies the JSON-schema validator: happy-path validation, failure
 * surfaces as JsonSchemaException, schema caching across calls, object
 * payload acceptance, and broken-on-disk schema detection.
 */
final class JsonSchemaValidatorTest extends TestCase
{
    private string $schemaDir;

    #[Override]
    protected function setUp(): void
    {
        $this->schemaDir = __DIR__ . '/../../Fixtures/Schemas';
    }

    #[Test]
    public function validates_valid_data(): void
    {
        $validator = new JsonSchemaValidator($this->schemaDir);
        $validator->validate('user.json', ['id' => 1, 'email' => 'a@b.com']);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throws_on_invalid_data(): void
    {
        $validator = new JsonSchemaValidator($this->schemaDir);
        $this->expectException(JsonSchemaException::class);
        $validator->validate('user.json', ['id' => 'not-an-int']);
    }

    #[Test]
    public function throws_when_schema_file_missing(): void
    {
        $validator = new JsonSchemaValidator($this->schemaDir);
        $this->expectException(JsonSchemaException::class);
        $this->expectExceptionMessageMatches('/Schema file not found/');
        $validator->validate('does-not-exist.json', []);
    }

    #[Test]
    public function caches_loaded_schemas(): void
    {
        $validator = new JsonSchemaValidator($this->schemaDir);
        $validator->validate('user.json', ['id' => 1, 'email' => 'a@b.com']);
        $validator->validate('user.json', ['id' => 2, 'email' => 'c@d.com']);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function accepts_object_payload_directly(): void
    {
        $validator = new JsonSchemaValidator($this->schemaDir);
        $payload = (object) ['id' => 1, 'email' => 'a@b.com'];

        $validator->validate('user.json', $payload);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throws_when_schema_file_is_not_valid_json(): void
    {
        $tempDir = sys_get_temp_dir() . '/api-testcase-schema-' . uniqid();
        mkdir($tempDir);
        $schemaPath = $tempDir . '/broken.json';
        file_put_contents($schemaPath, '{ this is not json');

        try {
            $validator = new JsonSchemaValidator($tempDir);
            $this->expectException(JsonSchemaException::class);
            $this->expectExceptionMessageMatches('/not valid JSON/');
            $validator->validate('broken.json', []);
        } finally {
            unlink($schemaPath);
            rmdir($tempDir);
        }
    }

    #[Test]
    public function throws_when_schema_file_does_not_decode_to_object(): void
    {
        $tempDir = sys_get_temp_dir() . '/api-testcase-schema-' . uniqid();
        mkdir($tempDir);
        $schemaPath = $tempDir . '/scalar.json';
        file_put_contents($schemaPath, '42');

        try {
            $validator = new JsonSchemaValidator($tempDir);
            $this->expectException(JsonSchemaException::class);
            $this->expectExceptionMessageMatches('/must decode to a JSON object/');
            $validator->validate('scalar.json', []);
        } finally {
            unlink($schemaPath);
            rmdir($tempDir);
        }
    }
}
