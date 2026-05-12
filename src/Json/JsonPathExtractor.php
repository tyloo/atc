<?php

declare(strict_types=1);

namespace Tyloo\Atc\Json;

use JmesPath\Env;
use JmesPath\SyntaxErrorException;
use Tyloo\Atc\Exception\JsonPathException;

/**
 * Thin wrapper around `mtdowling/jmespath.php` (JMESPath) that adapts vendor
 * errors to {@see JsonPathException}.
 *
 * @internal
 */
final class JsonPathExtractor
{
    /**
     * Extract the value matching `$path`, or `null` if nothing matches.
     *
     * @param array<mixed> $data
     *
     * @throws JsonPathException When `$path` is not a valid JMESPath expression.
     */
    public function extract(array $data, string $path): mixed
    {
        try {
            return Env::search($path, $data);
        } catch (SyntaxErrorException $e) {
            throw new JsonPathException(sprintf('Invalid JMESPath "%s": %s', $path, $e->getMessage()), 0, $e);
        }
    }

    /**
     * Whether `$path` resolves to a non-null value in `$data`.
     *
     * Note: JMESPath cannot distinguish between a missing path and an explicit
     * null value. Both return `null`, so this returns `false` in both cases.
     *
     * @param array<mixed> $data
     *
     * @throws JsonPathException When `$path` is not a valid JMESPath expression.
     */
    public function exists(array $data, string $path): bool
    {
        return $this->extract($data, $path) !== null;
    }
}
