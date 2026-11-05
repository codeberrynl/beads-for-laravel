<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

it('registers the routes in the local environment', function () {
    expect(Route::has('beads.spa'))->toBeTrue();

    $this->get('/beads')->assertOk();
});

it('registers no routes outside the local environment', function (string $environment) {
    $this->reloadWith($environment);

    expect(Route::has('beads.spa'))->toBeFalse();
    $this->get('/beads')->assertNotFound();
})->with(['production', 'staging', 'testing']);

it('registers no routes when BEADS_ENABLED is false', function () {
    $this->reloadWith('local', ['beads.enabled' => false]);

    expect(Route::has('beads.spa'))->toBeFalse();
    $this->get('/beads')->assertNotFound();
});

it('treats the string "false" as disabled', function () {
    $this->reloadWith('local', ['beads.enabled' => 'false']);

    expect(Route::has('beads.spa'))->toBeFalse();
});

it('allows guests by default in the local environment', function () {
    expect(Gate::has('viewBeads'))->toBeTrue();

    $this->get('/beads')->assertOk();
});

it('respects a viewBeads gate defined by the host', function () {
    Gate::define('viewBeads', fn (?Authenticatable $user = null): bool => false);

    $this->get('/beads')->assertForbidden();
});

it('does not overwrite a gate the host defined before booting', function () {
    $this->reloadWith('local', [], function (): void {
        // A non-nullable user parameter denies guests.
        Gate::define('viewBeads', fn (Authenticatable $user): bool => true);
    });

    $this->get('/beads')->assertForbidden();
});

it('honours the configured route prefix', function () {
    $this->reloadWith('local', ['beads.route.prefix' => 'tickets']);

    $this->get('/tickets')->assertOk();
    $this->get('/beads')->assertNotFound();
});

it('registers the assets as publishable', function () {
    $paths = ServiceProvider::pathsToPublish(null, 'beads-assets');

    expect($paths)->toHaveCount(1)
        ->and(array_values($paths)[0])->toBe(public_path('vendor/beads'));
});

it('registers the publishables outside the local environment as well', function () {
    $this->reloadWith('production');

    expect(ServiceProvider::pathsToPublish(null, 'beads-assets'))->toHaveCount(1);
});
