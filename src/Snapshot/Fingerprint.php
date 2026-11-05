<?php

declare(strict_types=1);

namespace Codeberry\Beads\Snapshot;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use UnexpectedValueException;

/**
 * A cheap hash over (path, mtime, size) of every file under .beads/,
 * except lock files and last-touched. No bd call needed.
 */
final class Fingerprint
{
    public static function of(string $beadsDirectory): string
    {
        clearstatcache();

        if (! is_dir($beadsDirectory)) {
            return sha1('missing:'.$beadsDirectory);
        }

        $entries = [];
        $prefixLength = strlen(rtrim($beadsDirectory, '/')) + 1;

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($beadsDirectory, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY,
                RecursiveIteratorIterator::CATCH_GET_CHILD,
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (! $file->isFile() || self::ignored($file->getFilename())) {
                    continue;
                }

                $entries[] = substr($file->getPathname(), $prefixLength).'|'.@$file->getMTime().'|'.@$file->getSize();
            }
        } catch (UnexpectedValueException) {
            // A file vanished while iterating; the next poll sees the new state.
        }

        sort($entries);

        return sha1(implode("\n", $entries));
    }

    public static function ignored(string $filename): bool
    {
        return $filename === 'last-touched'
            || str_ends_with($filename, '.lock')
            || str_contains($filename, '.lock.');
    }
}
