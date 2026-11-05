<?php

declare(strict_types=1);

namespace Codeberry\Beads\Support;

use InvalidArgumentException;

final class IssueId
{
    public const PATTERN = '[A-Za-z0-9][A-Za-z0-9._-]*';

    public static function isValid(mixed $id): bool
    {
        return is_string($id) && strlen($id) <= 200 && preg_match('/^'.self::PATTERN.'$/', $id) === 1;
    }

    public static function assertValid(mixed $id): string
    {
        if (! self::isValid($id)) {
            throw new InvalidArgumentException('Invalid issue ID ['.(is_scalar($id) ? (string) $id : get_debug_type($id)).'].');
        }

        /** @var string $id */
        return $id;
    }
}
