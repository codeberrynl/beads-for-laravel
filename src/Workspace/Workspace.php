<?php

declare(strict_types=1);

namespace Codeberry\Beads\Workspace;

/**
 * The single bd workspace of the host app: base_path() or BEADS_PATH.
 */
final class Workspace
{
    public readonly string $path;

    public function __construct(string $path)
    {
        $real = realpath($path);

        $this->path = rtrim($real !== false ? $real : $path, DIRECTORY_SEPARATOR) ?: DIRECTORY_SEPARATOR;
    }

    public function beadsDirectory(): string
    {
        return $this->path.DIRECTORY_SEPARATOR.'.beads';
    }

    public function exists(): bool
    {
        return is_dir($this->beadsDirectory());
    }

    /**
     * Stable key for per-workspace storage (snapshot, lock).
     */
    public function key(): string
    {
        return sha1($this->path);
    }
}
