<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;

function ideal(): string
{
    return fixtureIds()['Payment step: support iDEAL'];
}

/**
 * Fake bd where `bd show` returns the iDEAL fixture with overrides.
 *
 * @param  array<string, mixed>  $overrides
 */
function fakeUpdate(object $test, array $overrides = []): void
{
    $show = json_decode(fixtureContents('show.json'), true);
    $show['data'][0] = array_merge($show['data'][0], $overrides);

    fakeBd($test, [
        'show' => Process::result(json_encode($show)),
        'update' => Process::result(envelope([['id' => ideal()]])),
    ]);
}

it('writes one picker field without reading the issue first', function (string $field, mixed $value, string $flag) {
    fakeUpdate($this);

    $this->withCsrf()->patchJson('/beads/api/issues/'.ideal(), [$field => $value])->assertOk();

    expect(bdArgs('update'))->toBe([['update', ideal(), $flag, '--json', '--sandbox']])
        ->and(bdArgs('show'))->toBe([]);
})->with([
    ['status', 'in_progress', '--status=in_progress'],
    ['status', 'in_review', '--status=in_review'],
    ['priority', 0, '--priority=0'],
    ['type', 'bug', '--type=bug'],
    ['assignee', 'claude-agent', '--assignee=claude-agent'],
    ['assignee', null, '--assignee='],
    ['parent', null, '--parent='],
]);

it('sends labels as an add/remove diff', function () {
    fakeUpdate($this);

    $this->withCsrf()->patchJson('/beads/api/issues/'.ideal(), ['labels' => ['add' => ['urgent', 'ux'], 'remove' => ['payments']]])->assertOk();

    expect(bdArgs('update'))->toBe([['update', ideal(), '--add-label=urgent', '--add-label=ux', '--remove-label=payments', '--json', '--sandbox']]);
});

it('writes a text field when it is unchanged since the base', function () {
    fakeUpdate($this, ['description' => "Original text\n"]);

    $this->withCsrf()->patchJson('/beads/api/issues/'.ideal(), [
        'description' => 'New text',
        'base' => ['description' => 'Original text'],
    ])->assertOk()->assertJsonPath('issue.id', ideal());

    expect(bdArgs('show'))->toHaveCount(1)
        ->and(bdArgs('update'))->toBe([['update', ideal(), '--description=New text', '--json', '--sandbox']]);
});

it('answers 409 with their version when the text changed meanwhile', function () {
    fakeUpdate($this, ['description' => 'Changed by an agent']);

    $this->withCsrf()->patchJson('/beads/api/issues/'.ideal(), [
        'description' => 'My text',
        'base' => ['description' => 'Original text'],
    ])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'conflict')
        ->assertJsonPath('field', 'description')
        ->assertJsonPath('theirs', 'Changed by an agent');

    expect(bdArgs('update'))->toBe([]);
});

it('detects conflicts on the title and the other text fields', function (string $field, string $bdField) {
    fakeUpdate($this, [$bdField => 'Theirs']);

    $this->withCsrf()->patchJson('/beads/api/issues/'.ideal(), [$field => 'Mine', 'base' => [$field => 'Base']])
        ->assertStatus(409)
        ->assertJsonPath('field', $field);
})->with([
    ['title', 'title'],
    ['design', 'design'],
    ['acceptance_criteria', 'acceptance_criteria'],
    ['notes', 'notes'],
]);

it('overwrites when the client sends their version as the base', function () {
    fakeUpdate($this, ['description' => 'Changed by an agent']);

    $this->withCsrf()->patchJson('/beads/api/issues/'.ideal(), [
        'description' => 'My text',
        'base' => ['description' => 'Changed by an agent'],
    ])->assertOk();

    expect(bdArgs('update'))->toHaveCount(1);
});

it('maps acceptance_criteria to --acceptance and clears empty text', function () {
    fakeUpdate($this, ['acceptance_criteria' => 'Old']);

    $this->withCsrf()->patchJson('/beads/api/issues/'.ideal(), [
        'acceptance_criteria' => '',
        'base' => ['acceptance_criteria' => 'Old'],
    ])->assertOk();

    expect(bdArgs('update'))->toBe([['update', ideal(), '--acceptance=', '--json', '--sandbox']]);
});

it('does not invalidate the snapshot with the conflict check', function () {
    fakeUpdate($this, ['description' => 'Changed by an agent']);
    $this->getJson('/beads/api/snapshot');

    $this->withCsrf()->patchJson('/beads/api/issues/'.ideal(), ['description' => 'x', 'base' => ['description' => 'y']])->assertStatus(409);
    $this->getJson('/beads/api/snapshot')->assertOk();

    expect(bdCalls())->toBe(['export', 'statuses', 'types', 'show']);
});

it('validates the fields', function (array $input, string $error) {
    fakeUpdate($this);

    $this->withCsrf()->patchJson('/beads/api/issues/'.ideal(), $input)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($error);

    expect(bdArgs('update'))->toBe([]);
})->with([
    'nothing to update' => [[], '_fields'],
    'unknown status' => [['status' => 'nope'], 'status'],
    'empty title' => [['title' => ''], 'title'],
    'priority out of range' => [['priority' => -1], 'priority'],
    'own parent' => [['parent' => 'demo-ycn.3'], 'parent'],
    'unknown parent' => [['parent' => 'demo-nope'], 'parent'],
    'label with comma' => [['labels' => ['add' => ['a,b']]], 'labels.add.0'],
    'labels as a list' => [['labels' => ['a']], 'labels'],
]);

it('answers 404 for an unknown issue', function () {
    fakeUpdate($this);

    $this->withCsrf()->patchJson('/beads/api/issues/demo-missing', ['priority' => 1])->assertNotFound();

    expect(bdArgs('update'))->toBe([]);
});

it('requires a CSRF token', function () {
    fakeUpdate($this);

    $this->patchJson('/beads/api/issues/'.ideal(), ['priority' => 1])->assertStatus(419);
});

it('passes a guard mismatch or refusal through', function () {
    fakeBd($this, ['update' => Process::result('', "Error updating demo-ycn.3: cannot close blocked issue: demo-ycn.3 is blocked by [demo-rif]\n", 1)]);

    $this->withCsrf()->patchJson('/beads/api/issues/'.ideal(), ['status' => 'closed'])
        ->assertStatus(422)
        ->assertJsonPath('error.message', 'cannot close blocked issue: demo-ycn.3 is blocked by [demo-rif]');
});
