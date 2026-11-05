<?php

declare(strict_types=1);

use Codeberry\Beads\Cli\WorkspaceLock;
use Illuminate\Support\Facades\Route;

it('renders a lock timeout as a 503 with Retry-After', function () {
    $lock = WorkspaceLock::forWorkspace('/projects/a', $this->sandbox('locks'), 0.05);
    touch($lock->lockFile);
    $other = fopen($lock->lockFile, 'c');
    flock($other, LOCK_EX);

    Route::get('/_test/lock', fn () => $lock->run(fn () => 'never'));

    try {
        $this->getJson('/_test/lock')
            ->assertStatus(503)
            ->assertHeader('Retry-After', '1')
            ->assertJsonPath('error.code', 'workspace_busy');
    } finally {
        flock($other, LOCK_UN);
        fclose($other);
    }
});
