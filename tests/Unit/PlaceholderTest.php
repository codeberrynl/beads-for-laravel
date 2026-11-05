<?php

declare(strict_types=1);

use Codeberry\Beads\BeadsServiceProvider;

it('registers the service provider', function () {
    expect(app()->getProviders(BeadsServiceProvider::class))->not->toBeEmpty();
});
