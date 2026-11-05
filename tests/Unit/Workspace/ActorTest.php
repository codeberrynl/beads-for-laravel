<?php

declare(strict_types=1);

use Codeberry\Beads\Support\Environment;
use Codeberry\Beads\Workspace\Actor;
use Codeberry\Beads\Workspace\Workspace;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

function actorFor(object $test, ?string $configured, array $humans = [], ?string $git = null): Actor
{
    return new Actor(
        $configured,
        $humans,
        new Workspace($test->sandbox('workspace')),
        $test->sandbox('cache').'/actor.json',
        $test->sandbox('home'),
        [$git ?? $test->sandbox('bin').'/missing-git'],
    );
}

it('uses the configured actor', function () {
    Process::fake();

    expect(actorFor($this, 'Configured Person')->name())->toBe('Configured Person');
    Process::assertNothingRan();
});

it('falls back to git config user.name of the workspace', function () {
    Process::fake(['*' => Process::result("Jurrid\n")]);
    $git = fakeExecutable($this->sandbox('bin').'/git');

    expect(actorFor($this, null, git: $git)->name())->toBe('Jurrid');

    Process::assertRan(fn (PendingProcess $process) => $process->command === [$git, 'config', 'user.name']
        && $process->path === realpath($this->sandbox('workspace')));
});

it('caches the git user name until a git config file changes', function () {
    Process::fake(['*' => Process::sequence()->push(Process::result('First'))->push(Process::result('Second'))]);
    $git = fakeExecutable($this->sandbox('bin').'/git');
    @mkdir($this->sandbox('workspace').'/.git', 0777, true);
    $config = $this->sandbox('workspace').'/.git/config';
    file_put_contents($config, '[user]');
    touch($config, 1_700_000_000);

    expect(actorFor($this, null, git: $git)->name())->toBe('First')
        ->and(actorFor($this, null, git: $git)->name())->toBe('First');
    Process::assertRanTimes(fn () => true, 1);

    touch($config, 1_700_000_500);

    expect(actorFor($this, null, git: $git)->name())->toBe('Second');
});

it('falls back to the system user when git has no name', function () {
    Process::fake(['*' => Process::result('', '', 1)]);
    $git = fakeExecutable($this->sandbox('bin').'/git');

    expect(actorFor($this, '', git: $git)->name())->toBe(Environment::userName() ?? 'unknown');
});

it('defaults the humans to the actor', function () {
    $actor = actorFor($this, 'Jurrid');

    expect($actor->humans())->toBe(['Jurrid'])
        ->and($actor->isHuman('Jurrid'))->toBeTrue()
        ->and($actor->isHuman('jurrid'))->toBeTrue()
        ->and($actor->isHuman('claude-agent'))->toBeFalse();
});

it('uses the configured humans', function () {
    $actor = actorFor($this, 'Jurrid', ['Alice', ' Bob ', '']);

    expect($actor->humans())->toBe(['Alice', 'Bob'])
        ->and($actor->isHuman('Jurrid'))->toBeFalse()
        ->and($actor->isHuman('Bob'))->toBeTrue();
});

it('treats a missing author as human', function () {
    expect(actorFor($this, 'Jurrid')->isHuman(null))->toBeTrue();
});
