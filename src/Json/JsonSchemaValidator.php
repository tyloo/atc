<?php

declare(strict_types=1);

namespace Tyloo\Atc\Json;

use JsonException;
use JsonSchema\Validator;
use Tyloo\Atc\Exception\JsonSchemaException;

/**
 * Loads JSON Schema files from a base directory and validates payloads against
 * them with `justinrainbow/json-schema`.
 *
 * @internal
 */
final class JsonSchemaValidator
{
    /** @var array<string, object> */
    private array $cache = [];

    public function __construct(
        private readonly string $baseDir,
    ) {}

    /**
     * @param array<mixed>|object $data
     *
     * @throws JsonException      When `$data` cannot be normalised to a JSON object.
     * @throws JsonSchemaException When the schema cannot be loaded or the payload does not validate.
     */
    public function validate(string $schemaPath, array|object $data): void
    {
        $schema = $this->loadSchema($schemaPath);
        $payload = is_array($data) ? self::arrayToObject($data) : $data;

        $validator = new Validator();
        // @mago-ignore analysis:mixed-assignment
        // justinrainbow's validate(&$value) signature is by-reference, which mago treats as a reassignment.
        $validator->validate($payload, $schema);

        if (!$validator->isValid()) {
            throw new JsonSchemaException(sprintf(
                "JSON does not match schema '%s':\n%s",
                $schemaPath,
                json_encode($validator->getErrors(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
            ));
        }
    }

    /**
     * justinrainbow expects an object graph, not an associative array. Round-trip via JSON.
     *
     * @param array<mixed> $data
     *
     * @throws JsonException|JsonSchemaException
     */
    private static function arrayToObject(array $data): object
    {
        return self::asJsonObject(json_decode(json_encode($data, JSON_THROW_ON_ERROR), false, flags: JSON_THROW_ON_ERROR), '<inline payload>');
    }

    /**
     * @throws JsonSchemaException When the file is missing, unreadable, or not a valid JSON object.
     */
    private function loadSchema(string $schemaPath): object
    {
        $cached = $this->cache[$schemaPath] ?? null;
        if ($cached !== null) {
            return $cached;
        }

        $absolute = rtrim($this->baseDir, '/') . '/' . ltrim($schemaPath, '/');

        if (!is_file($absolute)) {
            throw new JsonSchemaException(sprintf('Schema file not found: %s', $absolute));
        }

        $contents = file_get_contents($absolute);
        // @codeCoverageIgnoreStart
        // Defensive: is_file() above already catches the common missing-file case.
        if ($contents === false) {
            throw new JsonSchemaException(sprintf('Could not read schema file: %s', $absolute));
        }
        // @codeCoverageIgnoreEnd

        try {
            return $this->cache[$schemaPath] = self::asJsonObject(json_decode($contents, false, flags: JSON_THROW_ON_ERROR), $absolute);
        } catch (JsonException $e) {
            throw new JsonSchemaException(sprintf('Schema file %s is not valid JSON: %s', $absolute, $e->getMessage()), 0, $e);
        }
    }

    /**
     * @throws JsonSchemaException
     */
    private static function asJsonObject(mixed $decoded, string $absolute): object
    {
        if (!is_object($decoded)) {
            throw new JsonSchemaException(sprintf('Schema file %s must decode to a JSON object, got %s.', $absolute, get_debug_type($decoded)));
        }

        return $decoded;
    }
}
