<?php

declare(strict_types=1);

namespace Codeberry\Beads\Support;

/**
 * Small JSON file store with atomic writes. Independent of the host's cache
 * driver on purpose.
 */
final class JsonFile
{
    /**
     * @return array<mixed>|null
     */
    public static function read(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        $contents = @file_get_contents($path);

        if ($contents === false || $contents === '') {
            return null;
        }

        $data = json_decode($contents, true);

        return is_array($data) ? $data : null;
    }

    /**
     * @param  array<mixed>  $data
     */
    public static function write(string $path, array $data): void
    {
        self::writeRaw($path, (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public static function writeRaw(string $path, string $contents): void
    {
        $directory = dirname($path);

        if (! is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $temporary = $path.'.'.bin2hex(random_bytes(6)).'.tmp';

        file_put_contents($temporary, $contents);
        rename($temporary, $path);
    }
}
