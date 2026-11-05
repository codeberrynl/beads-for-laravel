<?php

declare(strict_types=1);

use Codeberry\Beads\Cli\BdBinary;
use Codeberry\Beads\Cli\BdRunner;
use Codeberry\Beads\Cli\Exceptions\BdException;
use Codeberry\Beads\Cli\Exceptions\LockTimeoutException;
use Codeberry\Beads\Cli\WorkspaceLock;
use Codeberry\Beads\Workspace\Workspace;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

it('builds read commands with --json, --sandbox and --readonly', function () {
    $runner = makeRunner($this);
    $binary = $this->sandbox('bin').'/bd';

    expect($runner->buildCommand(['show', 'bfl-1'], [], [], true))
        ->toBe([$binary, 'show', 'bfl-1', '--json', '--sandbox', '--readonly']);
});

it('builds write commands without --readonly', function () {
    $runner = makeRunner($this);
    $binary = $this->sandbox('bin').'/bd';

    expect($runner->buildCommand(['update', 'bfl-1'], ['status' => 'closed']))
        ->toBe([$binary, 'update', 'bfl-1', '--status=closed', '--json', '--sandbox']);
});

it('passes string values as --flag=value so a leading dash never becomes a flag', function () {
    $runner = makeRunner($this);

    $command = $runner->buildCommand(['create'], ['title' => '--force -rf', 'priority' => 0]);

    expect($command)->toContain('--title=--force -rf')
        ->and($command)->toContain('--priority=0')
        ->and($command)->not->toContain('--force');
});

it('repeats list values, renders true as a bare flag and skips null and false', function () {
    $runner = makeRunner($this);

    $command = $runner->buildCommand(['update', 'bfl-1'], [
        'add-label' => ['a', 'b'],
        'force' => true,
        'reason' => null,
        'claim' => false,
        'assignee' => '',
    ]);

    expect(array_slice($command, 3, 5))->toBe(['--add-label=a', '--add-label=b', '--force', '--assignee=', '--json']);
});

it('places free text after a double dash', function () {
    $runner = makeRunner($this);

    $command = $runner->buildCommand(['comments', 'add', 'bfl-1'], [], ['-starts with a dash --json']);

    expect(array_slice($command, -4))->toBe(['--json', '--sandbox', '--', '-starts with a dash --json']);
});

it('rejects invalid issue IDs and command tokens', function (string $token) {
    $runner = makeRunner($this);

    expect(fn () => $runner->buildCommand(['show', $token]))->toThrow(InvalidArgumentException::class);
})->with(['-rf', '--force', 'a b', '', '../etc', 'id;rm', "id\nx"]);

it('rejects invalid flag names', function () {
    $runner = makeRunner($this);

    expect(fn () => $runner->buildCommand(['update', 'bfl-1'], ['title; rm' => 'x']))->toThrow(InvalidArgumentException::class);
});

it('runs in the workspace with an explicit environment', function () {
    Process::fake(['*' => Process::result(envelope([['id' => 'bfl-1']]))]);
    $runner = makeRunner($this, 'Jurrid');

    $runner->read(['show', 'bfl-1']);

    Process::assertRan(function (PendingProcess $process) {
        $env = $process->environment;

        return $process->path === realpath($this->sandbox('workspace'))
            && $env['HOME'] === '/home/tester'
            && $env['PATH'] === $this->sandbox('bin').':/usr/bin:/bin'
            && $env['BD_JSON_ENVELOPE'] === '1'
            && $env['BEADS_ACTOR'] === 'Jurrid'
            && $env['NO_COLOR'] === '1'
            && $env['BEADS_DIR'] === false
            && $process->timeout === 30;
    });
});

it('leaves BEADS_ACTOR out when there is no actor', function () {
    expect(makeRunner($this, null)->environment())->not->toHaveKey('BEADS_ACTOR');
});

it('unwraps the JSON envelope', function () {
    Process::fake(['*' => Process::result(envelope([['id' => 'bfl-1', 'title' => 'One']]))]);

    expect(makeRunner($this)->read(['show', 'bfl-1'])->json())->toBe([['id' => 'bfl-1', 'title' => 'One']]);
});

it('accepts output without an envelope', function () {
    Process::fake(['*' => Process::result(json_encode(['id1' => 'a', 'id2' => 'b', 'related' => true]))]);

    expect(makeRunner($this)->write(['dep', 'relate', 'a', 'b'])->json())->toBe(['id1' => 'a', 'id2' => 'b', 'related' => true]);
});

it('parses JSON lines', function () {
    Process::fake(['*' => Process::result("{\"id\":\"a\"}\n\n{\"id\":\"b\"}\nnot json\n")]);

    expect(makeRunner($this)->read(['export'])->jsonLines())->toBe([['id' => 'a'], ['id' => 'b']]);
});

it('maps errors to codes', function (string $stdout, string $stderr, int $exit, string $code, string $message) {
    Process::fake(['*' => Process::result($stdout, $stderr, $exit)]);

    try {
        makeRunner($this)->write(['update', 'bfl-1'], ['status' => 'closed']);
        $this->fail('Expected a BdException.');
    } catch (BdException $exception) {
        expect($exception->errorCode)->toBe($code)
            ->and($exception->getMessage())->toBe($message)
            ->and($exception->exitCode)->toBe($exit);
    }
})->with([
    'not found (JSON on stdout)' => [
        envelope(['error' => 'no issues found matching the provided IDs', 'hint' => 'try bd history']),
        "Issue bfl-1 not found\nHint: try bd history",
        1, BdException::NOT_FOUND, 'no issues found matching the provided IDs',
    ],
    'guard mismatch (exit 13)' => [
        '', 'Error updating bfl-1: status is in_progress, expected open', 13, BdException::GUARD_MISMATCH, 'status is in_progress, expected open',
    ],
    'schema migration (JSON on stderr)' => [
        '', json_encode(['error' => 'database schema is out of date; run bd migrate', 'hint' => 'bd migrate']), 1,
        BdException::SCHEMA_MIGRATION_REQUIRED, 'database schema is out of date; run bd migrate',
    ],
    'plain refusal on stderr' => [
        '', 'cannot close blocked issue: bfl-1 is blocked by [bfl-2] (use --force to override)', 1,
        BdException::FAILED, 'cannot close blocked issue: bfl-1 is blocked by [bfl-2] (use --force to override)',
    ],
    'update failures list' => [
        envelope(['error' => '1 of 1 issues failed to update', 'failed' => [['id' => 'bfl-1', 'error' => 'updating issue: cannot close blocked issue: bfl-1 is blocked by [bfl-2]']]]),
        'Error updating bfl-1: cannot close blocked issue', 1,
        BdException::FAILED, 'cannot close blocked issue: bfl-1 is blocked by [bfl-2]',
    ],
    'no output at all' => ['', '', 2, BdException::FAILED, 'bd exited with code 2.'],
]);

it('renders bd errors as JSON with a matching status', function () {
    $exception = new BdException(BdException::NOT_FOUND, 'Issue not found', 'Check the ID');

    $response = $exception->render();

    expect($response->getStatusCode())->toBe(404)
        ->and($response->getData(true))->toBe(['error' => ['code' => 'not_found', 'message' => 'Issue not found', 'hint' => 'Check the ID']]);
});

it('serialises calls through the workspace lock', function () {
    Process::fake(['*' => Process::result(envelope([]))]);
    $runner = makeRunner($this);
    $lock = WorkspaceLock::forWorkspace((new Workspace($this->sandbox('workspace')))->path, $this->sandbox('locks'), 1);

    touch($lock->lockFile);
    $other = fopen($lock->lockFile, 'c');
    flock($other, LOCK_EX);

    $slowRunner = new BdRunner(
        new BdBinary($this->sandbox('bin').'/bd', null),
        new Workspace($this->sandbox('workspace')),
        WorkspaceLock::forWorkspace((new Workspace($this->sandbox('workspace')))->path, $this->sandbox('locks'), 0.05),
    );

    try {
        expect(fn () => $slowRunner->read(['export']))->toThrow(LockTimeoutException::class);
        Process::assertNothingRan();
    } finally {
        flock($other, LOCK_UN);
        fclose($other);
    }

    expect($runner->read(['export'])->successful())->toBeTrue();
});
