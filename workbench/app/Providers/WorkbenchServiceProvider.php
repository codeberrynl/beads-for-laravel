<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Support\ServiceProvider;
use Workbench\App\Console\Commands\DemoCommand;
use Workbench\App\Console\Commands\RecordFixturesCommand;

class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // testbench.yaml sets BEADS_PATH relative to the package root.
        $path = (string) config('beads.path');

        if ($path !== '' && ! str_starts_with($path, '/')) {
            config(['beads.path' => static::packageRoot().'/'.$path]);
        }
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                DemoCommand::class,
                RecordFixturesCommand::class,
            ]);
        }
    }

    public static function packageRoot(): string
    {
        return dirname(__DIR__, 3);
    }
}
