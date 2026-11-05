<?php

declare(strict_types=1);

namespace Codeberry\Beads\Cli;

use Codeberry\Beads\Support\JsonFile;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Detects the bd version. The result is cached on the binary path and its
 * mtime, so an upgrade is picked up without clearing anything.
 */
final class BdVersion
{
    public const MINIMUM = '1.3.0';

    public function __construct(
        private readonly BdBinary $binary,
        private readonly string $cacheFile,
        private readonly ?string $home,
    ) {}

    public function current(): ?string
    {
        $path = $this->binary->path();

        if ($path === null) {
            return null;
        }

        clearstatcache(true, $path);
        $mtime = (int) @filemtime($path);
        $cached = JsonFile::read($this->cacheFile);

        if (is_array($cached) && ($cached['path'] ?? null) === $path && ($cached['mtime'] ?? null) === $mtime) {
            return is_string($cached['version'] ?? null) ? $cached['version'] : null;
        }

        $version = $this->detect($path);

        JsonFile::write($this->cacheFile, ['path' => $path, 'mtime' => $mtime, 'version' => $version]);

        return $version;
    }

    public function isSupported(): bool
    {
        $version = $this->current();

        return $version !== null && self::satisfies($version);
    }

    public static function parse(string $output): ?string
    {
        if (preg_match('/(\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?)/', $output, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    public static function satisfies(string $version, string $minimum = self::MINIMUM): bool
    {
        return version_compare($version, $minimum, '>=');
    }

    private function detect(string $path): ?string
    {
        try {
            $result = Process::path(sys_get_temp_dir())
                ->env(array_filter([
                    'HOME' => $this->home,
                    'PATH' => dirname($path).':/usr/bin:/bin',
                    'NO_COLOR' => '1',
                ], fn (?string $value): bool => $value !== null))
                ->timeout(10)
                ->run([$path, '--version']);
        } catch (Throwable) {
            return null;
        }

        if (! $result->successful()) {
            return null;
        }

        return self::parse($result->output());
    }
}
