<?php

declare(strict_types=1);

use Codeberry\Beads\Data\Comment;
use Codeberry\Beads\Data\Dependency;
use Codeberry\Beads\Data\Issue;
use Codeberry\Beads\Data\IssueRow;

it('maps every issue of the export fixture', function () {
    $issues = fixtureIssues();
    $ids = fixtureIds();

    expect($issues)->toHaveCount(count($ids))
        ->and(array_map(fn (Issue $issue) => $issue->id, $issues))->toEqualCanonicalizing(array_values($ids));
});

it('maps fields, labels, dependencies and comments from the export', function () {
    $ids = fixtureIds();
    $issue = collect(fixtureIssues())->firstWhere('id', $ids['Payment step: support iDEAL']);

    expect($issue)
        ->title->toBe('Payment step: support iDEAL')
        ->status->toBe('open')
        ->priority->toBe(1)
        ->type->toBe('feature')
        ->design->toContain('hosted page')
        ->createdBy->toBe('Jurrid')
        ->parent->toBe($ids['Checkout redesign'])
        ->labels->toBe(['backend', 'checkout', 'payments'])
        ->commentCount->toBe(2);

    expect($issue->comments)->toHaveCount(2)
        ->and($issue->comments[0])->toBeInstanceOf(Comment::class)
        ->and($issue->comments[1]->author)->toBe('claude-agent');

    $types = array_map(fn (Dependency $dependency) => [$dependency->type, $dependency->dependsOnId], $issue->dependencies);

    expect($types)->toContain([Dependency::PARENT_CHILD, $ids['Checkout redesign']])
        ->and($types)->toContain([Dependency::BLOCKS, $ids['Upgrade the payment SDK to v5']]);
});

it('maps closed, deferred and acceptance fields', function () {
    $ids = fixtureIds();
    $issues = collect(fixtureIssues())->keyBy('id');

    expect($issues[$ids['Fix flaky test in OrderTest']])
        ->status->toBe('closed')
        ->closedAt->not->toBeNull()
        ->closeReason->toBe('Fixed the time-dependent assertion.');

    expect($issues[$ids['Migrate CI to GitHub Actions']])
        ->status->toBe('deferred')
        ->deferUntil->not->toBeNull();

    expect($issues[$ids['Implement address autocomplete']]->acceptanceCriteria)->toContain('Postcode');
});

it('maps bd show output, which is an array also for one ID', function () {
    $ids = fixtureIds();
    $issue = Issue::fromShow(fixtureJson('show.json'));

    expect($issue)->not->toBeNull()
        ->id->toBe($ids['Payment step: support iDEAL'])
        ->parent->toBe($ids['Checkout redesign']);

    $blocks = array_values(array_filter($issue->dependencies, fn (Dependency $d) => $d->type === Dependency::BLOCKS));

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]->issueId)->toBe($issue->id)
        ->and($blocks[0]->dependsOnId)->toBe($ids['Upgrade the payment SDK to v5']);
});

it('picks the requested issue from a multi-issue show', function () {
    $ids = fixtureIds();

    expect(Issue::fromShow(fixtureJson('show-multiple.json'), $ids['Rate limiting for the public API'])?->title)
        ->toBe('Rate limiting for the public API');
});

it('accepts show output as a single object', function () {
    expect(Issue::fromShow(['id' => 'x-1', 'title' => 'Object'])?->title)->toBe('Object');
});

it('returns null for show output without issues', function () {
    expect(Issue::fromShow(null))->toBeNull()
        ->and(Issue::fromShow([]))->toBeNull()
        ->and(Issue::fromShow([['id' => 'a']], 'b'))->toBeNull();
});

it('uses defaults for missing fields', function () {
    $issue = Issue::fromArray(['id' => 'x-1']);

    expect($issue)
        ->title->toBe('')
        ->status->toBe('open')
        ->priority->toBe(2)
        ->type->toBe('task')
        ->description->toBe('')
        ->assignee->toBeNull()
        ->labels->toBe([])
        ->dependencies->toBe([])
        ->comments->toBe([])
        ->commentCount->toBe(0);
});

it('ignores unknown fields and odd values', function () {
    $issue = Issue::fromArray([
        'id' => 'x-1',
        'title' => 'Odd',
        'priority' => 'P1',
        'labels' => ['b', null, ['nested'], 'a', ''],
        'dependencies' => ['not-an-array', ['depends_on_id' => 'x-2']],
        'comments' => 'nope',
        'some_future_field' => ['whatever' => true],
        'assignee' => '',
    ]);

    expect($issue)
        ->priority->toBe(1)
        ->labels->toBe(['a', 'b'])
        ->assignee->toBeNull()
        ->comments->toBe([]);

    expect($issue->dependencies)->toHaveCount(1)
        ->and($issue->dependencies[0]->type)->toBe(Dependency::BLOCKS);
});

it('serialises to the API shape', function () {
    $json = json_decode(json_encode(Issue::fromArray(['id' => 'x-1', 'title' => 'T', 'issue_type' => 'bug'])), true);

    expect($json)->toMatchArray(['id' => 'x-1', 'title' => 'T', 'type' => 'bug', 'labels' => [], 'parent' => null])
        ->and($json)->toHaveKeys(['description', 'design', 'acceptance_criteria', 'notes', 'comments', 'dependencies']);
});

it('builds slim rows without long text or comments', function () {
    $ids = fixtureIds();
    $issue = collect(fixtureIssues())->firstWhere('id', $ids['Payment step: support iDEAL']);

    $row = json_decode(json_encode(IssueRow::fromIssue($issue, ['demo-x'], false, null)), true);

    expect($row)->not->toHaveKeys(['description', 'design', 'notes', 'comments', 'acceptance_criteria'])
        ->and($row)->toMatchArray(['id' => $issue->id, 'comment_count' => 2, 'blocked_by' => ['demo-x'], 'ready' => false]);
});

it('returns text fields by API name', function () {
    $issue = Issue::fromArray(['id' => 'x', 'title' => 'T', 'acceptance_criteria' => 'A', 'notes' => 'N']);

    expect($issue->text('title'))->toBe('T')
        ->and($issue->text('acceptance_criteria'))->toBe('A')
        ->and($issue->text('notes'))->toBe('N')
        ->and($issue->text('unknown'))->toBe('');
});
