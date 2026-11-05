<?php

declare(strict_types=1);

namespace Codeberry\Beads\Cli\Exceptions;

use Codeberry\Beads\Cli\BdResult;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A bd call failed. The code tells callers (and the UI) what kind of failure.
 */
final class BdException extends RuntimeException
{
    public const NOT_FOUND = 'not_found';

    public const GUARD_MISMATCH = 'guard_mismatch';

    public const SCHEMA_MIGRATION_REQUIRED = 'schema_migration_required';

    public const FAILED = 'failed';

    public const TIMEOUT = 'timeout';

    public const GUARD_MISMATCH_EXIT_CODE = 13;

    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly ?string $hint = null,
        public readonly ?int $exitCode = null,
        public readonly string $errorOutput = '',
    ) {
        parent::__construct($message);
    }

    public static function fromResult(BdResult $result): self
    {
        [$message, $hint] = self::extractMessage($result);
        $haystack = strtolower($message.' '.$hint.' '.$result->errorOutput);

        $code = match (true) {
            $result->exitCode === self::GUARD_MISMATCH_EXIT_CODE => self::GUARD_MISMATCH,
            str_contains($haystack, 'schema') && str_contains($haystack, 'migrat') => self::SCHEMA_MIGRATION_REQUIRED,
            str_contains($haystack, 'not found') || str_contains($haystack, 'no issues found') => self::NOT_FOUND,
            default => self::FAILED,
        };

        return new self($code, $message, $hint, $result->exitCode, $result->errorOutput);
    }

    public static function timeout(int $seconds): self
    {
        return new self(self::TIMEOUT, "bd did not finish within {$seconds} seconds.");
    }

    public function status(): int
    {
        return match ($this->errorCode) {
            self::NOT_FOUND => 404,
            self::GUARD_MISMATCH => 409,
            self::SCHEMA_MIGRATION_REQUIRED => 503,
            self::TIMEOUT => 504,
            default => 422,
        };
    }

    public function render(): JsonResponse
    {
        $error = array_filter([
            'code' => $this->errorCode,
            'message' => $this->getMessage(),
            'hint' => $this->hint,
        ], fn (?string $value): bool => $value !== null && $value !== '');

        $payload = ['error' => $error];

        if ($this->errorCode === self::SCHEMA_MIGRATION_REQUIRED) {
            $payload['health'] = ['state' => self::SCHEMA_MIGRATION_REQUIRED];
        }

        return new JsonResponse($payload, $this->status());
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private static function extractMessage(BdResult $result): array
    {
        foreach (self::jsonCandidates($result) as $decoded) {
            if (is_array($decoded) && isset($decoded['error']) && is_string($decoded['error'])) {
                $message = $decoded['error'];

                // `bd update` reports per-issue failures under "failed".
                if (isset($decoded['failed']) && is_array($decoded['failed'])) {
                    $details = array_values(array_filter(array_map(
                        fn (mixed $failure): ?string => is_array($failure) && is_string($failure['error'] ?? null) ? self::clean($failure['error']) : null,
                        $decoded['failed'],
                    )));

                    if ($details !== []) {
                        $message = implode('; ', $details);
                    }
                }

                return [self::clean($message), is_string($decoded['hint'] ?? null) ? $decoded['hint'] : null];
            }
        }

        $lines = array_values(array_filter(
            array_map('trim', preg_split('/\R/', $result->errorOutput) ?: []),
            fn (string $line): bool => $line !== '' && ! str_starts_with($line, 'Warning:') && ! str_starts_with($line, '{'),
        ));

        $hint = null;
        $messageLines = [];

        foreach ($lines as $line) {
            if (str_starts_with($line, 'Hint:')) {
                $hint ??= trim(substr($line, 5));
            } else {
                $messageLines[] = $line;
            }
        }

        $message = $messageLines !== [] ? self::clean($messageLines[0]) : "bd exited with code {$result->exitCode}.";

        return [$message, $hint];
    }

    /**
     * JSON payloads in stdout or stderr: the whole stream, or single lines
     * (bd may print a text line and a JSON line on the same stream).
     *
     * @return iterable<mixed>
     */
    private static function jsonCandidates(BdResult $result): iterable
    {
        foreach ([$result->output, $result->errorOutput] as $stream) {
            $decoded = BdResult::decode($stream);

            if ($decoded !== null) {
                yield $decoded;

                continue;
            }

            foreach (preg_split('/\R/', $stream) ?: [] as $line) {
                $line = trim($line);

                if ($line !== '' && $line[0] === '{') {
                    yield BdResult::decode($line);
                }
            }
        }
    }

    private static function clean(string $message): string
    {
        $message = trim($message);
        $message = (string) preg_replace('/^Error(?: updating [^:]+)?:\s*/i', '', $message);
        $message = (string) preg_replace('/^updating issue:\s*/i', '', $message);

        return $message;
    }
}
