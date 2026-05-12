<?php

declare(strict_types=1);

namespace Tyloo\Atc\Exception;

use RuntimeException;

/**
 * Thrown when a JMESPath expression is syntactically invalid or cannot be evaluated
 * against the response payload by {@see \Tyloo\Atc\Json\JsonPathExtractor}.
 */
final class JsonPathException extends RuntimeException {}
