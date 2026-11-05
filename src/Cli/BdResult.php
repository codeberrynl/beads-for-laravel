<?php

declare(strict_types=1);

namespace Codeberry\Beads\Cli;

/**
 * The outcome of one bd call, with the JSON envelope unwrapped.
 */
final class BdResult
{
    /**
     * @param  list<string>  $command
     */
    public function __construct(
        public readonly array $command,
        public readonly int $exitCode,
        public readonly string $output,
        public readonly string $errorOutput,
    ) {}

    public function successful(): bool
    {
        return $this->exitCode === 0;
    }

    /**
     * The decoded stdout payload, unwrapped from the BD_JSON_ENVELOPE
     * envelope when present. Null when stdout is not JSON.
     */
    public function json(): mixed
    {
        return self::decode($this->output);
    }

    /**
     * Decoded JSON lines (bd export). Non-JSON lines are skipped.
     *
     * @return list<array<string, mixed>>
     */
    public function jsonLines(): array
    {
        $rows = [];

        foreach (preg_split('/\R/', $this->output) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] !== '{') {
                continue;
            }

            $row = json_decode($line, true);

            if (is_array($row)) {
                /** @var array<string, mixed> $row */
                $rows[] = $row;
            }
        }

        return $rows;
    }

    public static function decode(string $payload): mixed
    {
        $payload = trim($payload);

        if ($payload === '' || ! in_array($payload[0], ['{', '['], true)) {
            return null;
        }

        $decoded = json_decode($payload, true);

        if (is_array($decoded) && array_key_exists('data', $decoded) && array_key_exists('schema_version', $decoded)) {
            return $decoded['data'];
        }

        return $decoded;
    }
}
