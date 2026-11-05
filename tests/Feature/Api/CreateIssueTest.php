<?php

declare(strict_types=1);

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

function fakeCreate(object $test): void
{
    $created = false;

    fakeBd($test, [
        'create' => function () use (&$created) {
            $created = true;

            return Process::result(envelope(['id' => 'demo-new', 'title' => 'New issue', 'status' => 'open']));
        },
        'export' => function () use (&$created) {
            return Process::result($created
                ? exportWith([['id' => 'demo-new', 'title' => 'New issue', 'status' => 'open', 'issue_type' => 'bug', 'priority' => 1]])
                : fixtureContents('export.jsonl'));
        },
    ]);
}

it('creates an issue with --flag=value args and returns it with the new hash', function () {
    fakeCreate($this);
    $parent = fixtureIds()['Checkout redesign'];
    $before = $this->getJson('/beads/api/snapshot')->json('hash');

    $response = $this->withCsrf()->postJson('/beads/api/issues', [
        'title' => '-Looks like a flag',
        'type' => 'bug',
        'priority' => 1,
        'status' => 'open',
        'description' => 'Some **markdown**',
        'assignee' => 'Jurrid',
        'labels' => ['frontend', 'ux'],
        'parent' => $parent,
    ])->assertCreated();

    expect($response->json('issue.id'))->toBe('demo-new')
        ->and($response->json('hash'))->not->toBe($before)
        ->and(bdArgs('create'))->toBe([[
            'create',
            '--title=-Looks like a flag',
            '--type=bug',
            '--priority=1',
            '--status=open',
            '--description=Some **markdown**',
            '--assignee=Jurrid',
            '--parent='.$parent,
            '--labels=frontend,ux',
            '--json',
            '--sandbox',
        ]]);

    Process::assertRan(fn (PendingProcess $process) => ($process->command[1] ?? null) === 'create'
        && $process->environment['BEADS_ACTOR'] === 'Tester');
});

it('leaves empty optional fields out', function () {
    fakeCreate($this);

    $this->withCsrf()->postJson('/beads/api/issues', ['title' => 'Only a title', 'description' => '', 'labels' => []])->assertCreated();

    expect(bdArgs('create'))->toBe([['create', '--title=Only a title', '--json', '--sandbox']]);
});

it('refreshes the snapshot inside the write, without re-reading definitions', function () {
    fakeCreate($this);
    $this->getJson('/beads/api/snapshot');

    $this->withCsrf()->postJson('/beads/api/issues', ['title' => 'New issue'])->assertCreated();

    expect(bdCalls())->toBe(['export', 'statuses', 'types', 'create', 'export']);
});

it('validates the input', function (array $input, string $field) {
    fakeCreate($this);

    $this->withCsrf()->postJson('/beads/api/issues', $input)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect(bdArgs('create'))->toBe([]);
})->with([
    'missing title' => [[], 'title'],
    'unknown type' => [['title' => 'x', 'type' => 'saga'], 'type'],
    'system type' => [['title' => 'x', 'type' => 'gate'], 'type'],
    'unknown status' => [['title' => 'x', 'status' => 'done-ish'], 'status'],
    'priority out of range' => [['title' => 'x', 'priority' => 7], 'priority'],
    'label with a comma' => [['title' => 'x', 'labels' => ['a,b']], 'labels.0'],
    'invalid parent ID' => [['title' => 'x', 'parent' => '-rf'], 'parent'],
    'unknown parent' => [['title' => 'x', 'parent' => 'demo-missing'], 'parent'],
]);

it('accepts custom statuses from the bd definitions', function () {
    fakeCreate($this);

    $this->withCsrf()->postJson('/beads/api/issues', ['title' => 'x', 'status' => 'in_review'])->assertCreated();
});

it('requires a CSRF token', function () {
    fakeCreate($this);

    $this->postJson('/beads/api/issues', ['title' => 'No token'])->assertStatus(419);

    expect(bdArgs('create'))->toBe([]);
});

it('passes a bd refusal through', function () {
    fakeBd($this, ['create' => Process::result('', 'Error: invalid priority', 1)]);

    $this->withCsrf()->postJson('/beads/api/issues', ['title' => 'x'])
        ->assertStatus(422)
        ->assertJsonPath('error.message', 'invalid priority');
});
