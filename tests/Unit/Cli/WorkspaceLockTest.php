<?php

declare(strict_types=1);

use Codeberry\Beads\Cli\Exceptions\LockTimeoutException;
use Codeberry\Beads\Cli\WorkspaceLock;

it('uses one lock file per workspace', function () {
    $a = WorkspaceLock::forWorkspace('/projects/a', '/tmp/locks', 1);
    $b = WorkspaceLock::forWorkspace('/projects/b', '/tmp/locks', 1);

    expect($a->lockFile)->toBe('/tmp/locks/'.sha1('/projects/a').'.lock')
        ->and($a->lockFile)->not->toBe($b->lockFile);
});

it('runs the callback while holding the lock and returns its result', function () {
    $lock = WorkspaceLock::forWorkspace('/projects/a', $this->sandbox('locks'), 1);

    $result = $lock->run(function () use ($lock) {
        expect($lock->isHeld())->toBeTrue();

        // Another handle (as another process would) cannot take the lock now.
        $other = fopen($lock->lockFile, 'c');
        expect(flock($other, LOCK_EX | LOCK_NB))->toBeFalse();
        fclose($other);

        return 'done';
    });

    expect($result)->toBe('done')
        ->and($lock->isHeld())->toBeFalse();
});

it('waits for another holder and throws after the timeout', function () {
    $lock = WorkspaceLock::forWorkspace('/projects/a', $this->sandbox('locks'), 0.2);
    touch($lock->lockFile);
    $other = fopen($lock->lockFile, 'c');
    flock($other, LOCK_EX);

    $started = microtime(true);

    try {
        $lock->run(fn () => 'never');
        $this->fail('Expected a LockTimeoutException.');
    } catch (LockTimeoutException $exception) {
        expect(microtime(true) - $started)->toBeGreaterThanOrEqual(0.2)
            ->and($exception->getMessage())->toContain('busy');
    } finally {
        flock($other, LOCK_UN);
        fclose($other);
    }
});

it('acquires the lock once it is released', function () {
    $lock = WorkspaceLock::forWorkspace('/projects/a', $this->sandbox('locks'), 1);
    touch($lock->lockFile);
    $other = fopen($lock->lockFile, 'c');
    flock($other, LOCK_EX);
    flock($other, LOCK_UN);
    fclose($other);

    expect($lock->run(fn () => 'ok'))->toBe('ok');
});

it('is re-entrant within the same process', function () {
    $lock = WorkspaceLock::forWorkspace('/projects/a', $this->sandbox('locks'), 0.1);
    $again = WorkspaceLock::forWorkspace('/projects/a', $this->sandbox('locks'), 0.1);

    $result = $lock->run(fn () => $again->run(fn () => 'nested'));

    expect($result)->toBe('nested')
        ->and($lock->isHeld())->toBeFalse();
});

it('releases the lock when the callback throws', function () {
    $lock = WorkspaceLock::forWorkspace('/projects/a', $this->sandbox('locks'), 0.1);

    expect(fn () => $lock->run(fn () => throw new RuntimeException('boom')))->toThrow(RuntimeException::class);
    expect($lock->isHeld())->toBeFalse()
        ->and($lock->run(fn () => 'again'))->toBe('again');
});
