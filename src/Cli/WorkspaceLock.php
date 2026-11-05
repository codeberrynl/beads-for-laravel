<?php

declare(strict_types=1);

namespace Codeberry\Beads\Cli;

use Closure;
use Codeberry\Beads\Cli\Exceptions\LockTimeoutException;
use RuntimeException;

/**
 * Serialises every bd call per workspace with flock().
 *
 * Concurrent bd calls corrupt the embedded Dolt store, and even reads write
 * to it, so every call goes through this lock. The lock is re-entrant within
 * the current process, which lets a snapshot refresh wrap several calls.
 */
final class WorkspaceLock
{
    /** @var array<string, int> */
    private static array $depth = [];

    public function __construct(
        public readonly string $lockFile,
        private readonly float $timeout,
    ) {}

    public static function forWorkspace(string $workspacePath, string $directory, float $timeout): self
    {
        return new self(rtrim($directory, '/').'/'.sha1($workspacePath).'.lock', $timeout);
    }

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function run(Closure $callback): mixed
    {
        if ($this->isHeld()) {
            self::$depth[$this->lockFile]++;

            try {
                return $callback();
            } finally {
                self::$depth[$this->lockFile]--;
            }
        }

        $handle = $this->acquire();
        self::$depth[$this->lockFile] = 1;

        try {
            return $callback();
        } finally {
            unset(self::$depth[$this->lockFile]);
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function isHeld(): bool
    {
        return (self::$depth[$this->lockFile] ?? 0) > 0;
    }

    /**
     * @return resource
     */
    private function acquire()
    {
        $directory = dirname($this->lockFile);

        if (! is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $handle = @fopen($this->lockFile, 'c');

        if ($handle === false) {
            throw new RuntimeException("Unable to open the beads lock file [{$this->lockFile}].");
        }

        $deadline = microtime(true) + max(0.0, $this->timeout);

        while (! flock($handle, LOCK_EX | LOCK_NB)) {
            if (microtime(true) >= $deadline) {
                fclose($handle);

                throw new LockTimeoutException($this->lockFile, $this->timeout);
            }

            usleep(25_000);
        }

        return $handle;
    }
}
