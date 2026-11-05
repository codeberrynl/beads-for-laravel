<?php

declare(strict_types=1);

use Codeberry\Beads\BeadsServiceProvider;
use Codeberry\Beads\Support\AssetManifest;
use Illuminate\Filesystem\Filesystem;

function publishManifest(object $test, array $manifest): void
{
    $directory = public_path('vendor/beads/.vite');
    @mkdir($directory, 0777, true);
    file_put_contents($directory.'/manifest.json', json_encode($manifest));
}

afterEach(function () {
    (new Filesystem)->deleteDirectory(public_path('vendor/beads'));
});

it('serves the shell for the SPA routes', function (string $path) {
    $this->get($path)
        ->assertOk()
        ->assertSee('<div id="beads-app"', false)
        ->assertSee('window.Beads', false);
})->with(['/beads', '/beads/issue/demo-1', '/beads/board', '/beads/issues/active?priority=0']);

it('does not serve the shell for API paths', function () {
    $this->get('/beads/api/unknown')->assertNotFound()->assertJsonPath('error.code', 'not_found');
    $this->get('/beads/api')->assertNotFound();
});

it('passes the base path, API path, CSRF token and poll interval', function () {
    $response = $this->get('/beads')->assertOk();

    preg_match('/window\.Beads = (.*?);<\/script>/s', $response->getContent(), $matches);
    $config = json_decode($matches[1], true);

    expect($config)
        ->basePath->toBe('/beads')
        ->apiPath->toBe('/beads/api')
        ->pollInterval->toBe(3)
        ->csrfToken->toBeString()->not->toBeEmpty()
        ->and($config['assets'])->toHaveKeys(['hot', 'published', 'stale', 'command']);
});

it('follows the configured prefix', function () {
    $this->reloadWith('local', ['beads.route.prefix' => 'tickets']);

    $response = $this->get('/tickets/board')->assertOk();

    expect($response->getContent())->toContain('"basePath":"\/tickets"');
});

it('loads the entry and its CSS from the published manifest', function () {
    publishManifest($this, [
        'resources/js/app.ts' => ['file' => 'assets/app-abc.js', 'isEntry' => true, 'css' => ['assets/app-abc.css'], 'imports' => ['_shared.js']],
        '_shared.js' => ['file' => 'assets/shared-1.js', 'css' => ['assets/shared-1.css']],
    ]);

    $base = asset('vendor/beads/assets');

    $this->get('/beads')
        ->assertSee('<script type="module" src="'.$base.'/app-abc.js"></script>', false)
        ->assertSeeInOrder([$base.'/shared-1.css', $base.'/app-abc.css'], false);
});

it('loads the Vite dev server when the hot file exists', function () {
    $hot = $this->sandbox('pkg').'/hot';
    file_put_contents($hot, "http://localhost:5183\n");
    app()->instance(AssetManifest::class, new AssetManifest(BeadsServiceProvider::packagePath('dist'), public_path('vendor/beads'), $hot));

    $this->get('/beads')
        ->assertSee('<script type="module" src="http://localhost:5183/@vite/client"></script>', false)
        ->assertSee('<script type="module" src="http://localhost:5183/resources/js/app.ts"></script>', false);
});
