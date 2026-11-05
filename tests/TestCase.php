<?php

declare(strict_types=1);

namespace Codeberry\Beads\Tests;

use Codeberry\Beads\BeadsServiceProvider;
use Codeberry\Beads\Support\AssetManifest;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected static string $environment = 'local';

    /** @var array<string, mixed> */
    protected static array $configOverrides = [];

    /** @var (\Closure(Application): void)|null */
    protected static ?\Closure $bootingCallback = null;

    protected ?string $sandboxPath = null;

    protected function setUp(): void
    {
        // A running `npm run dev` writes ./hot; tests must not depend on it.
        $this->afterApplicationCreated(function (): void {
            $this->app->instance(AssetManifest::class, new AssetManifest(
                BeadsServiceProvider::packagePath('dist'),
                public_path('vendor/beads'),
                $this->sandbox('package').'/hot',
            ));
        });

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        static::$environment = 'local';
        static::$configOverrides = [];
        static::$bootingCallback = null;

        if ($this->sandboxPath !== null) {
            (new Filesystem)->deleteDirectory($this->sandboxPath);
            $this->sandboxPath = null;
        }
    }

    protected function getPackageProviders($app): array
    {
        return [
            BeadsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['env'] = static::$environment;
        $app->useStoragePath($this->sandbox('storage'));

        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('session.driver', 'array');
        $app['config']->set('beads.path', $this->sandbox('workspace'));
        $app['config']->set('beads.binary', $this->sandbox('bin').'/bd');
        $app['config']->set('beads.actor', 'Tester');

        foreach (static::$configOverrides as $key => $value) {
            $app['config']->set($key, $value);
        }

        if (static::$bootingCallback !== null) {
            $app->booting(static::$bootingCallback);
        }
    }

    /**
     * Boot a fresh application in the given environment with config overrides.
     *
     * @param  array<string, mixed>  $config
     * @param  (\Closure(Application): void)|null  $booting  runs before the providers boot
     */
    public function reloadWith(string $environment = 'local', array $config = [], ?\Closure $booting = null): void
    {
        static::$environment = $environment;
        static::$configOverrides = $config;
        static::$bootingCallback = $booting;

        $this->reloadApplication();
    }

    /**
     * Send the session's CSRF token, as the SPA does.
     */
    public function withCsrf(): static
    {
        return $this->withSession(['_token' => 'test-csrf-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-csrf-token');
    }

    /**
     * A per-test temporary directory (created on demand).
     */
    public function sandbox(string $path = ''): string
    {
        if ($this->sandboxPath === null) {
            $this->sandboxPath = sys_get_temp_dir().'/beads-tests-'.getmypid().'-'.bin2hex(random_bytes(4));
        }

        $full = rtrim($this->sandboxPath.'/'.$path, '/');

        if (! is_dir($full)) {
            mkdir($full, 0777, true);
        }

        return $full;
    }
}
