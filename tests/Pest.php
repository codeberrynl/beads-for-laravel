<?php

declare(strict_types=1);

use Codeberry\Beads\Cli\BdBinary;
use Codeberry\Beads\Cli\BdResult;
use Codeberry\Beads\Cli\BdRunner;
use Codeberry\Beads\Cli\WorkspaceLock;
use Codeberry\Beads\Data\Issue;
use Codeberry\Beads\Tests\TestCase;
use Codeberry\Beads\Workspace\Workspace;
use Illuminate\Process\Factory;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

uses(TestCase::class)->in(__DIR__);

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function fakeExecutable(string $path): string
{
    @mkdir(dirname($path), 0777, true);
    file_put_contents($path, "#!/bin/sh\necho 'bd version 1.3.2'\n");
    chmod($path, 0755);

    return $path;
}

function makeRunner(object $test, ?string $actor = 'Tester'): BdRunner
{
    $binary = fakeExecutable($test->sandbox('bin').'/bd');
    $workspace = new Workspace($test->sandbox('workspace'));

    return new BdRunner(
        new BdBinary($binary, null),
        $workspace,
        WorkspaceLock::forWorkspace($workspace->path, $test->sandbox('locks'), 1),
        fn () => $actor,
        30,
        '/home/tester',
    );
}

function envelope(mixed $data): string
{
    return json_encode(['data' => $data, 'schema_version' => 1]);
}

function fixturePath(string $name = ''): string
{
    return __DIR__.'/Fixtures/bd-1.3.2'.($name === '' ? '' : '/'.$name);
}

function fixtureContents(string $name): string
{
    return (string) file_get_contents(fixturePath($name));
}

/**
 * The decoded payload of a fixture, with the bd envelope unwrapped.
 */
function fixtureJson(string $name): mixed
{
    return BdResult::decode(fixtureContents($name));
}

/**
 * @return array<string, string> title => id of the demo workspace
 */
function fixtureIds(): array
{
    return json_decode(fixtureContents('meta.json'), true)['ids'];
}

/**
 * @return list<Issue>
 */
function fixtureIssues(): array
{
    $result = new BdResult([], 0, fixtureContents('export.jsonl'), '');

    return array_map(fn (array $row) => Issue::fromArray($row), $result->jsonLines());
}

/**
 * Create a fake .beads workspace in the test sandbox (beads.path).
 */
function fakeWorkspace(object $test): string
{
    $journal = $test->sandbox('workspace/.beads/embeddeddolt/demo/.dolt/noms').'/journal';

    if (! is_file($journal)) {
        file_put_contents($journal, 'journal');
        file_put_contents(dirname($journal).'/manifest', 'manifest');
        touch($journal, 1_700_000_000);
        touch(dirname($journal).'/manifest', 1_700_000_000);
        file_put_contents($test->sandbox('workspace/.beads').'/config.yaml', 'prefix: demo');
        touch($test->sandbox('workspace/.beads').'/config.yaml', 1_700_000_000);
    }

    return $test->sandbox('workspace');
}

/**
 * Simulate bd touching the Dolt files (as every real call does).
 */
function simulateBdTouch(object $test): void
{
    static $tick = 1_700_000_100;
    $tick += 7;

    foreach (['journal', 'manifest'] as $file) {
        touch($test->sandbox('workspace/.beads/embeddeddolt/demo/.dolt/noms').'/'.$file, $tick);
    }

    touch($test->sandbox('workspace/.beads').'/last-touched', $tick);
}

/**
 * Simulate an outside write (an agent running bd): the journal grows.
 */
function simulateExternalWrite(object $test): void
{
    file_put_contents($test->sandbox('workspace/.beads/embeddeddolt/demo/.dolt/noms').'/journal', 'x', FILE_APPEND);
    simulateBdTouch($test);
}

/**
 * Fake bd with fixture output per subcommand. Every call touches the Dolt
 * files like the real bd does. $responses maps a subcommand (e.g. "export",
 * "show", "update") to a ProcessResult or a closure returning one.
 *
 * @param  array<string, mixed>  $responses
 */
function fakeBd(object $test, array $responses = []): void
{
    fakeWorkspace($test);
    fakeExecutable($test->sandbox('bin').'/bd');
    @mkdir(dirname(config('beads.binary')), 0777, true);

    if (! is_file(config('beads.binary'))) {
        fakeExecutable(config('beads.binary'));
    }

    $defaults = [
        'export' => fn () => Process::result(fixtureContents('export.jsonl')),
        'statuses' => fn () => Process::result(fixtureContents('statuses.json')),
        'types' => fn () => Process::result(fixtureContents('types.json')),
        '--version' => fn () => Process::result('bd version 1.3.2 (Homebrew)'),
    ];

    $responses = array_merge($defaults, $responses);

    Process::fake(function (PendingProcess $process) use ($test, $responses) {
        $command = (array) $process->command;
        $subcommand = $command[1] ?? '';
        $key = isset($command[2]) && isset($responses[$subcommand.' '.$command[2]]) ? $subcommand.' '.$command[2] : $subcommand;

        if ($subcommand !== '--version') {
            simulateBdTouch($test);
        }

        $response = $responses[$key] ?? Process::result(envelope([]));

        return $response instanceof Closure ? $response($process) : $response;
    });
}

/**
 * The bd subcommands that ran (e.g. ["export", "statuses", "types"]).
 *
 * @return list<string>
 */
function bdCalls(): array
{
    $calls = [];

    foreach ((new ReflectionProperty(Factory::class, 'recorded'))->getValue(Process::getFacadeRoot()) as [$process]) {
        $command = (array) $process->command;

        if (($command[1] ?? '') !== '--version') {
            $calls[] = $command[1] ?? '';
        }
    }

    return $calls;
}

/**
 * A fixture export plus extra issue rows.
 *
 * @param  list<array<string, mixed>>  $extra
 */
function exportWith(array $extra): string
{
    return rtrim(fixtureContents('export.jsonl'))."\n".implode("\n", array_map(fn (array $row) => json_encode(['_type' => 'issue'] + $row), $extra))."\n";
}

/**
 * The argv (without the binary) of the recorded bd calls for a subcommand.
 *
 * @return list<list<string>>
 */
function bdArgs(string $subcommand): array
{
    $calls = [];

    foreach ((new ReflectionProperty(Factory::class, 'recorded'))->getValue(Process::getFacadeRoot()) as [$process]) {
        $command = (array) $process->command;

        if (($command[1] ?? '') === $subcommand) {
            $calls[] = array_values(array_slice($command, 1));
        }
    }

    return $calls;
}
