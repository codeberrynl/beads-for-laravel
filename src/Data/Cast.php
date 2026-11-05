<?php

declare(strict_types=1);

namespace Codeberry\Beads\Data;

/**
 * Tolerant casting for bd output: missing or odd values become defaults.
 *
 * @internal
 */
final class Cast
{
    public static function string(mixed $value, string $default = ''): string
    {
        return is_scalar($value) ? (string) $value : $default;
    }

    public static function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = (string) $value;

        return $value === '' ? null : $value;
    }

    public static function int(mixed $value, int $default = 0): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^[Pp]?(-?\d+)$/', trim($value), $matches) === 1) {
            return (int) $matches[1];
        }

        if (is_float($value)) {
            return (int) $value;
        }

        return $default;
    }

    public static function bool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    /**
     * @return list<string>
     */
    public static function strings(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn (mixed $item): ?string => self::nullableString($item), $value),
            fn (?string $item): bool => $item !== null,
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function rows(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $rows = [];

        foreach ($value as $row) {
            if (is_array($row)) {
                /** @var array<string, mixed> $row */
                $rows[] = $row;
            }
        }

        return $rows;
    }
}
