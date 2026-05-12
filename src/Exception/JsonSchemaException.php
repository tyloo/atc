<?php

declare(strict_types=1);

namespace Tyloo\Atc\Exception;

use RuntimeException;

/**
 * Thrown when a JSON schema file cannot be loaded, is malformed, or when a payload
 * fails validation against it inside {@see \Tyloo\Atc\Json\JsonSchemaValidator}.
 */
final class JsonSchemaException extends RuntimeException {}
