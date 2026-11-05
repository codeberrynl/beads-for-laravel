<?php

declare(strict_types=1);

namespace Codeberry\Beads\Commands;

use Illuminate\Console\Command;
use JsonException;
use stdClass;

final class InstallCommand extends Command
{
    public const HOOK = '@php artisan vendor:publish --tag=beads-assets --force';

    protected $signature = 'beads:install
        {--hook : Add the asset publish hook to composer.json without asking}
        {--no-hook : Do not add the asset publish hook}';

    protected $description = 'Publish the Beads config and assets, and optionally keep the assets fresh after composer update';

    public function handle(): int
    {
        $this->call('vendor:publish', ['--tag' => 'beads-config']);
        $this->call('vendor:publish', ['--tag' => 'beads-assets', '--force' => true]);

        $composer = base_path('composer.json');

        if ($this->option('no-hook') || ! is_file($composer)) {
            return self::SUCCESS;
        }

        if (self::hasHook($composer)) {
            $this->components->info('The composer post-update-cmd hook is already present.');

            return self::SUCCESS;
        }

        $add = $this->option('hook') || $this->confirm(
            'Add "'.self::HOOK.'" to post-update-cmd in composer.json, so the assets stay fresh after composer update?',
            true,
        );

        if ($add) {
            self::addHook($composer);
            $this->components->info('Added the asset publish hook to composer.json.');
        }

        return self::SUCCESS;
    }

    public static function hasHook(string $composerPath): bool
    {
        $scripts = self::read($composerPath)->scripts ?? null;
        $hooks = $scripts instanceof stdClass ? ($scripts->{'post-update-cmd'} ?? []) : [];

        return in_array(self::HOOK, (array) $hooks, true);
    }

    /**
     * Adds the hook without touching anything else (objects stay objects).
     */
    public static function addHook(string $composerPath): void
    {
        $json = self::read($composerPath);

        if (! isset($json->scripts) || ! $json->scripts instanceof stdClass) {
            $json->scripts = new stdClass;
        }

        $hooks = $json->scripts->{'post-update-cmd'} ?? [];
        $hooks = is_array($hooks) ? $hooks : [$hooks];

        if (! in_array(self::HOOK, $hooks, true)) {
            $hooks[] = self::HOOK;
        }

        $json->scripts->{'post-update-cmd'} = array_values($hooks);

        file_put_contents($composerPath, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
    }

    private static function read(string $composerPath): stdClass
    {
        try {
            $json = json_decode((string) file_get_contents($composerPath), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $json = null;
        }

        return $json instanceof stdClass ? $json : new stdClass;
    }
}
