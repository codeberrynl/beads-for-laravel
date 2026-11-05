<?php

declare(strict_types=1);

use Codeberry\Beads\BeadsServiceProvider;
use Codeberry\Beads\Commands\InstallCommand;
use Codeberry\Beads\Support\AssetManifest;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;

beforeEach(function () {
    $this->publicTarget = public_path('vendor/beads');
    $this->configTarget = array_values(ServiceProvider::pathsToPublish(null, 'beads-config'))[0] ?? config_path('beads.php');
});

afterEach(function () {
    (new Filesystem)->deleteDirectory($this->publicTarget);
    @unlink($this->configTarget);
});

function hostComposer(object $test, array $data): string
{
    $path = $test->sandbox('host').'/composer.json';
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    app()->setBasePath($test->sandbox('host'));

    return $path;
}

function manifestFor(object $test, ?string $published, ?string $package, ?string $hot = null): AssetManifest
{
    $dist = $test->sandbox('dist');
    $public = $test->sandbox('public');
    @mkdir($dist.'/.vite', 0777, true);
    @mkdir($public.'/.vite', 0777, true);
    @unlink($dist.'/.vite/manifest.json');
    @unlink($public.'/.vite/manifest.json');

    if ($package !== null) {
        file_put_contents($dist.'/.vite/manifest.json', $package);
    }

    if ($published !== null) {
        file_put_contents($public.'/.vite/manifest.json', $published);
    }

    $hotFile = $test->sandbox('pkg').'/hot';
    @unlink($hotFile);

    if ($hot !== null) {
        file_put_contents($hotFile, $hot);
    }

    return new AssetManifest($dist, $public, $hotFile);
}

it('publishes the assets with beads:publish', function () {
    $this->artisan('beads:publish')->assertSuccessful();

    expect(public_path('vendor/beads/.vite/manifest.json'))->toBeFile()
        ->and(sha1_file(public_path('vendor/beads/.vite/manifest.json')))->toBe(sha1_file(BeadsServiceProvider::packagePath('dist/.vite/manifest.json')))
        ->and(app(AssetManifest::class)->isStale())->toBeFalse();
});

it('installs config and assets and adds the composer hook when confirmed', function () {
    $assetsTarget = public_path('vendor/beads/.vite/manifest.json');
    $composer = hostComposer($this, ['name' => 'acme/app', 'require-dev' => new stdClass, 'scripts' => ['post-update-cmd' => ['@php artisan boost:update']]]);

    $this->artisan('beads:install')
        ->expectsConfirmation('Add "'.InstallCommand::HOOK.'" to post-update-cmd in composer.json, so the assets stay fresh after composer update?', 'yes')
        ->assertSuccessful();

    $json = file_get_contents($composer);

    expect($this->configTarget)->toBeFile()
        ->and($assetsTarget)->toBeFile()
        ->and(json_decode($json, true)['scripts']['post-update-cmd'])->toBe(['@php artisan boost:update', InstallCommand::HOOK])
        ->and($json)->toContain('"require-dev": {}');
});

it('does not add the hook when declined', function () {
    $composer = hostComposer($this, ['name' => 'acme/app']);

    $this->artisan('beads:install')
        ->expectsConfirmation('Add "'.InstallCommand::HOOK.'" to post-update-cmd in composer.json, so the assets stay fresh after composer update?', 'no')
        ->assertSuccessful();

    expect(InstallCommand::hasHook($composer))->toBeFalse();
});

it('adds the hook only once', function () {
    $composer = hostComposer($this, ['name' => 'acme/app', 'scripts' => ['post-update-cmd' => '@php artisan other']]);

    $this->artisan('beads:install --hook')->assertSuccessful();
    $this->artisan('beads:install')->expectsOutputToContain('already present')->assertSuccessful();
    $this->artisan('beads:install --hook')->assertSuccessful();

    expect(json_decode(file_get_contents($composer), true)['scripts']['post-update-cmd'])->toBe(['@php artisan other', InstallCommand::HOOK]);
});

it('skips the hook with --no-hook', function () {
    $composer = hostComposer($this, ['name' => 'acme/app']);

    $this->artisan('beads:install --no-hook')->assertSuccessful();

    expect(InstallCommand::hasHook($composer))->toBeFalse();
});

it('registers the commands only locally', function () {
    $this->reloadWith('production');

    expect(array_keys(Artisan::all()))->not->toContain('beads:install', 'beads:publish');
});

it('detects stale, missing and fresh assets', function () {
    expect(manifestFor($this, published: '{"a":1}', package: '{"a":1}')->isStale())->toBeFalse()
        ->and(manifestFor($this, published: '{"a":1}', package: '{"a":2}')->isStale())->toBeTrue()
        ->and(manifestFor($this, published: null, package: '{"a":2}')->isStale())->toBeTrue()
        ->and(manifestFor($this, published: null, package: '{"a":2}')->isPublished())->toBeFalse()
        ->and(manifestFor($this, published: null, package: '{"a":2}', hot: 'http://localhost:5183')->isStale())->toBeFalse();
});

it('shows a banner with the command when the assets are stale', function () {
    app()->instance(AssetManifest::class, manifestFor($this, published: '{"a":1}', package: '{"a":2}'));

    $this->get('/beads')
        ->assertSee('id="beads-stale-assets"', false)
        ->assertSee('The Beads assets are out of date.')
        ->assertSee('php artisan beads:publish');
});

it('shows a banner when the assets were never published', function () {
    app()->instance(AssetManifest::class, manifestFor($this, published: null, package: '{"a":2}'));

    $this->get('/beads')->assertSee('The Beads assets are not published.');
});

it('shows no banner for fresh assets', function () {
    app()->instance(AssetManifest::class, manifestFor($this, published: '{"a":1}', package: '{"a":1}'));

    $this->get('/beads')->assertDontSee('id="beads-stale-assets"', false);
});
