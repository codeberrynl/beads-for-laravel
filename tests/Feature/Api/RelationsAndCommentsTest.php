<?php

declare(strict_types=1);

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

function ids(): array
{
    return fixtureIds();
}

it('adds relations with the right bd command per type and direction', function (string $type, ?string $direction, Closure $expected) {
    fakeBd($this);
    $id = ids()['Write onboarding docs'];
    $target = ids()['Upgrade the payment SDK to v5'];

    $this->withCsrf()->postJson("/beads/api/issues/{$id}/relations", array_filter(['type' => $type, 'target' => $target, 'direction' => $direction]))
        ->assertOk()
        ->assertJsonPath('issue.id', $id);

    [$subcommand, $args] = $expected($id, $target);

    expect(bdArgs($subcommand))->toBe([$args]);
})->with([
    'blocked by target' => ['blocks', 'blocked_by', fn ($id, $t) => ['dep', ['dep', 'add', $id, $t, '--type=blocks', '--json', '--sandbox']]],
    'blocking target' => ['blocks', 'blocking', fn ($id, $t) => ['dep', ['dep', 'add', $t, $id, '--type=blocks', '--json', '--sandbox']]],
    'related' => ['related', null, fn ($id, $t) => ['dep', ['dep', 'relate', $id, $t, '--json', '--sandbox']]],
    'target as parent' => ['parent-child', 'parent', fn ($id, $t) => ['update', ['update', $id, '--parent='.$t, '--json', '--sandbox']]],
    'target as child' => ['parent-child', 'child', fn ($id, $t) => ['update', ['update', $t, '--parent='.$id, '--json', '--sandbox']]],
]);

it('removes relations with the right bd command per type and direction', function (string $type, ?string $direction, Closure $expected) {
    fakeBd($this);
    $id = ids()['Payment step: support iDEAL'];
    $target = ids()['Upgrade the payment SDK to v5'];

    $this->withCsrf()->deleteJson("/beads/api/issues/{$id}/relations", array_filter(['type' => $type, 'target' => $target, 'direction' => $direction]))
        ->assertOk();

    [$subcommand, $args] = $expected($id, $target);

    expect(bdArgs($subcommand))->toBe([$args]);
})->with([
    'blocked by target' => ['blocks', 'blocked_by', fn ($id, $t) => ['dep', ['dep', 'remove', $id, $t, '--json', '--sandbox']]],
    'blocking target' => ['blocks', 'blocking', fn ($id, $t) => ['dep', ['dep', 'remove', $t, $id, '--json', '--sandbox']]],
    'parent' => ['parent-child', 'parent', fn ($id, $t) => ['update', ['update', $id, '--parent=', '--json', '--sandbox']]],
    'child' => ['parent-child', 'child', fn ($id, $t) => ['update', ['update', $t, '--parent=', '--json', '--sandbox']]],
    'other, outgoing' => ['discovered-from', 'outgoing', fn ($id, $t) => ['dep', ['dep', 'remove', $id, $t, '--json', '--sandbox']]],
    'other, incoming' => ['discovered-from', 'incoming', fn ($id, $t) => ['dep', ['dep', 'remove', $t, $id, '--json', '--sandbox']]],
]);

it('removes a relates-to link with unrelate', function () {
    fakeBd($this);
    $id = ids()['Typo tolerance for search queries'];
    $target = ids()['Index product variants'];

    $this->withCsrf()->deleteJson("/beads/api/issues/{$id}/relations", ['type' => 'related', 'target' => $target])->assertOk();

    expect(bdArgs('dep'))->toBe([['dep', 'unrelate', $id, $target, '--json', '--sandbox']]);
});

it('removes plain related edges in the direction they exist', function () {
    fakeBd($this, ['export' => Process::result(exportWith([
        ['id' => 'demo-a', 'title' => 'A', 'dependencies' => [['issue_id' => 'demo-a', 'depends_on_id' => 'demo-b', 'type' => 'related']]],
        ['id' => 'demo-b', 'title' => 'B'],
    ]))]);

    $this->withCsrf()->deleteJson('/beads/api/issues/demo-b/relations', ['type' => 'related', 'target' => 'demo-a'])->assertOk();

    expect(bdArgs('dep'))->toBe([['dep', 'remove', 'demo-a', 'demo-b', '--json', '--sandbox']]);
});

it('validates relations', function (array $input, string $error) {
    fakeBd($this);
    $id = ids()['Write onboarding docs'];

    $this->withCsrf()->postJson("/beads/api/issues/{$id}/relations", $input)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($error);

    expect(bdArgs('dep'))->toBe([]);
})->with([
    'unknown type' => [['type' => 'tracks', 'target' => 'demo-rif', 'direction' => 'outgoing'], 'type'],
    'missing direction' => [['type' => 'blocks', 'target' => 'demo-rif'], 'direction'],
    'wrong direction' => [['type' => 'blocks', 'target' => 'demo-rif', 'direction' => 'parent'], 'direction'],
    'unknown target' => [['type' => 'blocks', 'target' => 'demo-none', 'direction' => 'blocking'], 'target'],
    'invalid target' => [['type' => 'blocks', 'target' => '--force', 'direction' => 'blocking'], 'target'],
    'itself' => [['type' => 'related', 'target' => 'demo-3mz'], 'target'],
]);

it('adds a comment with the actor as author and the text after --', function () {
    fakeBd($this);
    $id = ids()['Write onboarding docs'];

    $this->withCsrf()->postJson("/beads/api/issues/{$id}/comments", ['text' => '--force is not a flag here'])
        ->assertCreated()
        ->assertJsonPath('issue.id', $id);

    expect(bdArgs('comments'))->toBe([['comments', 'add', $id, '--author=Tester', '--json', '--sandbox', '--', '--force is not a flag here']]);

    Process::assertRan(fn (PendingProcess $process) => ($process->command[1] ?? null) === 'comments'
        && $process->environment['BEADS_ACTOR'] === 'Tester');
});

it('requires comment text', function () {
    fakeBd($this);

    $this->withCsrf()->postJson('/beads/api/issues/'.ids()['Write onboarding docs'].'/comments', ['text' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('text');
});

it('answers 404 for unknown issues', function () {
    fakeBd($this);

    $this->withCsrf()->postJson('/beads/api/issues/demo-missing/comments', ['text' => 'x'])->assertNotFound();
    $this->withCsrf()->postJson('/beads/api/issues/demo-missing/relations', ['type' => 'related', 'target' => 'demo-rif'])->assertNotFound();
});

it('requires a CSRF token', function () {
    fakeBd($this);
    $id = ids()['Write onboarding docs'];

    $this->postJson("/beads/api/issues/{$id}/comments", ['text' => 'x'])->assertStatus(419);
    $this->deleteJson("/beads/api/issues/{$id}/relations", ['type' => 'related', 'target' => 'demo-rif'])->assertStatus(419);
});
