<?php

declare(strict_types=1);

use Codeberry\Beads\Cli\BdBinary;
use Codeberry\Beads\Cli\BdResult;
use Codeberry\Beads\Cli\BdRunner;
use Codeberry\Beads\Cli\WorkspaceLock;
use Codeberry\Beads\Data\Issue;
use Codeberry\Beads\Tests\TestCase;
use Codeberry\Beads\Workspace\Workspace;

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
