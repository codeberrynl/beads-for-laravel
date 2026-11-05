<?php

declare(strict_types=1);

namespace Codeberry\Beads;

use Codeberry\Beads\Cli\BdBinary;
use Codeberry\Beads\Cli\BdRunner;
use Codeberry\Beads\Cli\BdVersion;
use Codeberry\Beads\Cli\WorkspaceLock;
use Codeberry\Beads\Snapshot\SnapshotRepository;
use Codeberry\Beads\Snapshot\SnapshotStore;
use Codeberry\Beads\Support\Environment;
use Codeberry\Beads\Workspace\Actor;
use Codeberry\Beads\Workspace\HealthCheck;
use Codeberry\Beads\Workspace\Workspace;
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

    public function packageRegistered(): void
    {
        $this->app->singleton(BdBinary::class, fn (Application $app): BdBinary => new BdBinary(
            $app['config']->get('beads.binary'),
            Environment::home(),
        ));

        $this->app->singleton(BdVersion::class, fn (Application $app): BdVersion => new BdVersion(
            $app->make(BdBinary::class),
            static::storagePath('bd-version.json'),
            Environment::home(),
        ));

        $this->app->singleton(Workspace::class, fn (Application $app): Workspace => new Workspace(
            (string) $app['config']->get('beads.path', base_path()),
        ));

        $this->app->singleton(WorkspaceLock::class, fn (Application $app): WorkspaceLock => WorkspaceLock::forWorkspace(
            $app->make(Workspace::class)->path,
            static::storagePath(),
            (float) $app['config']->get('beads.lock_timeout', 10),
        ));

        $this->app->singleton(Actor::class, fn (Application $app): Actor => new Actor(
            $app['config']->get('beads.actor'),
            array_values((array) $app['config']->get('beads.humans', [])),
            $app->make(Workspace::class),
            static::storagePath($app->make(Workspace::class)->key().'/actor.json'),
            Environment::home(),
        ));

        $this->app->singleton(HealthCheck::class);

        $this->app->singleton(SnapshotStore::class, fn (Application $app): SnapshotStore => new SnapshotStore(
            static::storagePath($app->make(Workspace::class)->key()),
        ));

        $this->app->singleton(SnapshotRepository::class);

        $this->app->singleton(BdRunner::class, fn (Application $app): BdRunner => new BdRunner(
            $app->make(BdBinary::class),
            $app->make(Workspace::class),
            $app->make(WorkspaceLock::class),
            fn (): string => $app->make(Actor::class)->name(),
            (int) $app['config']->get('beads.process_timeout', 30),
            Environment::home(),
        ));
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

    /**
     * The package's own storage, independent of the host's cache driver.
     */
    public static function storagePath(string $path = ''): string
    {
        return storage_path('framework/beads'.($path === '' ? '' : '/'.$path));
    }

    public static function packagePath(string $path = ''): string
    {
        return dirname(__DIR__).($path === '' ? '' : DIRECTORY_SEPARATOR.$path);
    }
}
