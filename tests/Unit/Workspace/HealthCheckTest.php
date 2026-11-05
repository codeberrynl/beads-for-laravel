<?php

declare(strict_types=1);

use Codeberry\Beads\Cli\BdBinary;
use Codeberry\Beads\Cli\BdVersion;
use Codeberry\Beads\Workspace\HealthCheck;
use Codeberry\Beads\Workspace\Workspace;
use Codeberry\Beads\Workspace\WorkspaceHealth;
use Illuminate\Support\Facades\Process;

function healthFor(object $test, ?string $binary, bool $beadsDirectory = true): HealthCheck
{
    $workspace = $test->sandbox('workspace');

    if ($beadsDirectory) {
        @mkdir($workspace.'/.beads', 0777, true);
    }

    $bd = new BdBinary($binary ?? $test->sandbox('bin').'/missing', null);

    return new HealthCheck($bd, new BdVersion($bd, $test->sandbox('cache').'/version.json', null), new Workspace($workspace));
}

it('reports a missing binary', function () {
    $health = healthFor($this, null);

    expect($health->state())->toBe(WorkspaceHealth::BinaryMissing)
        ->and($health->details()['searched'])->toBe([$this->sandbox('bin').'/missing']);
});

it('reports a bd that is too old', function () {
    Process::fake(['*' => Process::result('bd version 1.2.0 (Homebrew)')]);

    $health = healthFor($this, fakeExecutable($this->sandbox('bin').'/bd'));

    expect($health->state())->toBe(WorkspaceHealth::VersionTooOld)
        ->and($health->details())->toMatchArray(['version' => '1.2.0', 'minimum_version' => '1.3.0']);
});

it('reports a missing workspace', function () {
    Process::fake(['*' => Process::result('bd version 1.3.2 (Homebrew)')]);

    $health = healthFor($this, fakeExecutable($this->sandbox('bin').'/bd'), beadsDirectory: false);

    expect($health->state())->toBe(WorkspaceHealth::WorkspaceMissing);
});

it('reports ok', function () {
    Process::fake(['*' => Process::result('bd version 1.3.2 (Homebrew)')]);

    $health = healthFor($this, fakeExecutable($this->sandbox('bin').'/bd'));

    expect($health->state())->toBe(WorkspaceHealth::Ok)
        ->and($health->state()->isOk())->toBeTrue();
});

it('renders an unhealthy state as a 503 with details', function () {
    $response = healthFor($this, null)->response();

    expect($response->getStatusCode())->toBe(503)
        ->and($response->getData(true)['health']['state'])->toBe('binary_missing')
        ->and($response->getData(true)['error']['code'])->toBe('unhealthy');
});

it('has the schema migration state for bd refusals', function () {
    expect(WorkspaceHealth::from('schema_migration_required'))->toBe(WorkspaceHealth::SchemaMigrationRequired);
});
