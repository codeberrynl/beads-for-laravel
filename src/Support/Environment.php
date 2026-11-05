<?php

declare(strict_types=1);

namespace Codeberry\Beads\Support;

/**
 * Facts about the PHP process environment.
 *
 * Herd's PHP-FPM clears the environment, so web requests have no HOME, PATH
 * or USER. Everything here works without them.
 */
final class Environment
{
    public static function home(): ?string
    {
        if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
            $info = posix_getpwuid(posix_geteuid());

            if (is_array($info) && $info['dir'] !== '') {
                return $info['dir'];
            }
        }

        $home = getenv('HOME');

        if (is_string($home) && $home !== '') {
            return $home;
        }

        $server = $_SERVER['HOME'] ?? null;

        return is_string($server) && $server !== '' ? $server : null;
    }

    public static function userName(): ?string
    {
        if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
            $info = posix_getpwuid(posix_geteuid());

            if (is_array($info) && $info['name'] !== '') {
                return $info['name'];
            }
        }

        $user = getenv('USER');

        return is_string($user) && $user !== '' ? $user : null;
    }

    public static function expandHome(string $path, ?string $home): string
    {
        if ($home !== null && ($path === '~' || str_starts_with($path, '~/'))) {
            return rtrim($home, '/').substr($path, 1);
        }

        return $path;
    }
}
