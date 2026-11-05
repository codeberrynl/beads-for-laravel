<?php

declare(strict_types=1);

namespace Codeberry\Beads;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class BeadsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('beads')
            ->hasConfigFile()
            ->hasViews('beads');
    }

    public function packageBooted(): void
    {
        $this->registerPublishables();

        if (! static::shouldRun($this->app)) {
            return;
        }

        $this->registerGate();
        $this->registerRoutes();
    }

    /**
     * The UI, routes, gate and commands only exist locally and when enabled.
     */
    public static function shouldRun(Application $app): bool
    {
        return $app->isLocal() && filter_var($app['config']->get('beads.enabled', true), FILTER_VALIDATE_BOOL);
    }

    protected function registerPublishables(): void
    {
        $this->publishes([
            static::packagePath('dist') => public_path('vendor/beads'),
        ], ['beads-assets']);
    }

    protected function registerGate(): void
    {
        // Defined after every provider has booted, so a host definition wins.
        $this->app->booted(function (): void {
            if (! Gate::has('viewBeads')) {
                Gate::define('viewBeads', fn (?Authenticatable $user = null): bool => true);
            }
        });
    }

    protected function registerRoutes(): void
    {
        Route::group([
            'prefix' => trim((string) config('beads.route.prefix', 'beads'), '/'),
            'domain' => config('beads.route.domain'),
            'middleware' => [...(array) config('beads.route.middleware', ['web']), Http\Middleware\Authorize::class],
            'as' => 'beads.',
        ], fn () => $this->loadRoutesFrom(static::packagePath('routes/beads.php')));
    }

    public static function packagePath(string $path = ''): string
    {
        return dirname(__DIR__).($path === '' ? '' : DIRECTORY_SEPARATOR.$path);
    }
}
