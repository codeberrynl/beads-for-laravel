<?php

declare(strict_types=1);

use Codeberry\Beads\Data\Issue;
use Codeberry\Beads\Data\StatusCatalog;
use Codeberry\Beads\Data\StatusDefinition;
use Codeberry\Beads\Graph\IssueGraph;

function fixtureGraph(): IssueGraph
{
    $statuses = new StatusCatalog(StatusDefinition::listFrom(fixtureJson('statuses.json')));
    $recordedAt = new DateTimeImmutable(json_decode(fixtureContents('meta.json'), true)['recorded_at']);

    return new IssueGraph(fixtureIssues(), $statuses, $recordedAt);
}

/**
 * @param  list<array<string, mixed>>  $rows
 */
function graphOf(array $rows, array $statuses = [], ?string $now = '2026-11-05T12:00:00Z'): IssueGraph
{
    return new IssueGraph(
        array_map(fn (array $row) => Issue::fromArray($row), $rows),
        new StatusCatalog(StatusDefinition::listFrom($statuses)),
        new DateTimeImmutable($now),
    );
}

function blocks(string $issue, string $blocker): array
{
    return ['issue_id' => $issue, 'depends_on_id' => $blocker, 'type' => 'blocks'];
}

it('matches bd ready on the fixture', function () {
    $expected = array_map(fn (array $issue) => $issue['id'], fixtureJson('ready.json'));

    expect(fixtureGraph()->readyIds())->toEqualCanonicalizing($expected);
});

it('matches bd blocked on the fixture, including what each issue waits on', function () {
    $graph = fixtureGraph();
    $expected = [];

    foreach (fixtureJson('blocked.json') as $issue) {
        $expected[$issue['id']] = $issue['blocked_by'];
    }

    $actual = [];

    foreach ($graph->issues() as $id => $issue) {
        if ($graph->isBlockedByDependencies($id) && ! $graph->statuses()->isDone($issue->status)) {
            $actual[$id] = $graph->blockedBy($id);
        }
    }

    ksort($expected);
    ksort($actual);

    expect($actual)->toEqualCanonicalizing($expected);
});

it('counts status blocked as blocked even without dependencies', function () {
    $graph = graphOf([['id' => 'a', 'status' => 'blocked']]);

    expect($graph->isBlocked('a'))->toBeTrue()
        ->and($graph->isBlockedByDependencies('a'))->toBeFalse()
        ->and($graph->blockedIds())->toBe(['a'])
        ->and($graph->isReady('a'))->toBeFalse();
});

it('does not block on a closed blocker', function () {
    $graph = graphOf([
        ['id' => 'a', 'dependencies' => [blocks('a', 'b')]],
        ['id' => 'b', 'status' => 'closed'],
    ]);

    expect($graph->blockers('a'))->toBe(['b'])
        ->and($graph->openBlockers('a'))->toBe([])
        ->and($graph->isReady('a'))->toBeTrue();
});

it('blocks on a blocker in a custom done status only when it is not done', function () {
    $statuses = ['built_in_statuses' => [['name' => 'open', 'category' => 'active'], ['name' => 'closed', 'category' => 'done']], 'custom_statuses' => [['name' => 'shipped', 'category' => 'done'], ['name' => 'review', 'category' => 'wip']]];

    $graph = graphOf([
        ['id' => 'a', 'dependencies' => [blocks('a', 'b')]],
        ['id' => 'b', 'status' => 'shipped'],
        ['id' => 'c', 'dependencies' => [blocks('c', 'd')]],
        ['id' => 'd', 'status' => 'review'],
    ], $statuses);

    expect($graph->isReady('a'))->toBeTrue()
        ->and($graph->isReady('c'))->toBeFalse()
        ->and($graph->blockedBy('c'))->toBe(['d']);
});

it('treats custom statuses by category for ready', function () {
    $statuses = ['custom_statuses' => [['name' => 'triage', 'category' => 'active'], ['name' => 'review', 'category' => 'wip'], ['name' => 'icebox', 'category' => 'frozen']]];

    $graph = graphOf([
        ['id' => 'a', 'status' => 'triage'],
        ['id' => 'b', 'status' => 'review'],
        ['id' => 'c', 'status' => 'icebox'],
        ['id' => 'd', 'status' => 'legacy-without-category'],
    ], $statuses);

    expect($graph->readyIds())->toBe(['a']);
});

it('propagates dependency blocking down the parent tree', function () {
    $graph = graphOf([
        ['id' => 'epic', 'issue_type' => 'epic', 'dependencies' => [blocks('epic', 'x')]],
        ['id' => 'x'],
        ['id' => 'child', 'parent' => 'epic'],
        ['id' => 'grandchild', 'parent' => 'child'],
    ]);

    expect($graph->isReady('grandchild'))->toBeFalse()
        ->and($graph->blockedBy('child'))->toBe(['epic'])
        ->and($graph->blockedBy('grandchild'))->toBe(['child'])
        ->and($graph->isReady('x'))->toBeTrue();
});

it('does not propagate a blocked status without dependencies', function () {
    $graph = graphOf([
        ['id' => 'parent', 'status' => 'blocked'],
        ['id' => 'child', 'parent' => 'parent'],
    ]);

    expect($graph->isReady('child'))->toBeTrue();
});

it('hides issues deferred into the future and children of such a parent', function () {
    $graph = graphOf([
        ['id' => 'later', 'status' => 'open', 'defer_until' => '2026-12-01T00:00:00Z'],
        ['id' => 'past', 'status' => 'open', 'defer_until' => '2026-01-01T00:00:00Z'],
        ['id' => 'child', 'parent' => 'later'],
        ['id' => 'grandchild', 'parent' => 'child'],
    ]);

    expect($graph->isReady('later'))->toBeFalse()
        ->and($graph->isReady('past'))->toBeTrue()
        ->and($graph->isReady('child'))->toBeFalse()
        ->and($graph->isReady('grandchild'))->toBeTrue();
});

it('excludes system types from ready and blocked', function () {
    $graph = graphOf([
        ['id' => 'g', 'issue_type' => 'gate'],
        ['id' => 'm', 'issue_type' => 'message', 'status' => 'blocked'],
    ]);

    expect($graph->readyIds())->toBe([])
        ->and($graph->blockedIds())->toBe([])
        ->and($graph->isSystem($graph->issue('g')))->toBeTrue();
});

it('computes epic progress from the children', function () {
    $graph = graphOf([
        ['id' => 'epic', 'issue_type' => 'epic'],
        ['id' => 'a', 'parent' => 'epic', 'status' => 'closed'],
        ['id' => 'b', 'parent' => 'epic'],
        ['id' => 'c', 'parent' => 'epic', 'status' => 'in_progress'],
        ['id' => 'empty', 'issue_type' => 'epic'],
    ]);

    expect($graph->progress('epic'))->toBe(['done' => 1, 'total' => 3])
        ->and($graph->children('epic'))->toBe(['a', 'b', 'c'])
        ->and($graph->progress('empty'))->toBeNull();
});

it('computes epic progress on the fixture', function () {
    $graph = fixtureGraph();
    $ids = fixtureIds();

    expect($graph->progress($ids['Checkout redesign']))->toBe(['done' => 1, 'total' => 4])
        ->and($graph->progress($ids['Search improvements']))->toBe(['done' => 0, 'total' => 3]);
});

it('exposes blocks in both directions, related and other relations', function () {
    $graph = graphOf([
        ['id' => 'a', 'dependencies' => [
            blocks('a', 'b'),
            ['issue_id' => 'a', 'depends_on_id' => 'c', 'type' => 'relates-to'],
            ['issue_id' => 'a', 'depends_on_id' => 'd', 'type' => 'discovered-from'],
        ]],
        ['id' => 'b'],
        ['id' => 'c', 'dependencies' => [
            ['issue_id' => 'c', 'depends_on_id' => 'a', 'type' => 'relates-to'],
            ['issue_id' => 'c', 'depends_on_id' => 'b', 'type' => 'related'],
        ]],
        ['id' => 'd'],
    ]);

    expect($graph->blockers('a'))->toBe(['b'])
        ->and($graph->blocking('b'))->toBe(['a'])
        ->and($graph->related('a'))->toBe(['c'])
        ->and($graph->related('c'))->toBe(['a', 'b'])
        ->and($graph->related('b'))->toBe(['c'])
        ->and($graph->otherRelations('a'))->toHaveCount(1)
        ->and($graph->otherRelations('d')[0]->type)->toBe('discovered-from');
});

it('treats unknown local blockers as not blocking and external ones as blocking', function () {
    $graph = graphOf([
        ['id' => 'a', 'dependencies' => [blocks('a', 'gone-1')]],
        ['id' => 'b', 'dependencies' => [blocks('b', 'external:other:capability')]],
    ]);

    expect($graph->isReady('a'))->toBeTrue()
        ->and($graph->isReady('b'))->toBeFalse();
});

it('survives a parent cycle', function () {
    $graph = graphOf([
        ['id' => 'a', 'parent' => 'b'],
        ['id' => 'b', 'parent' => 'a'],
    ]);

    expect($graph->isBlockedByDependencies('a'))->toBeFalse();
});
