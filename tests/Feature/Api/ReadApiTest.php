<?php

declare(strict_types=1);

use Codeberry\Beads\Cli\BdBinary;
use Codeberry\Beads\Cli\BdRunner;
use Codeberry\Beads\Cli\WorkspaceLock;
use Codeberry\Beads\Snapshot\SnapshotPresenter;
use Codeberry\Beads\Snapshot\SnapshotRepository;
use Codeberry\Beads\Workspace\Actor;
use Codeberry\Beads\Workspace\HealthCheck;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    // The fixtures were recorded on this day.
    Carbon::setTestNow('2026-11-05T13:00:00Z');
});

afterEach(fn () => Carbon::setTestNow());

it('returns the bootstrap payload', function () {
    fakeBd($this);

    $response = $this->getJson('/beads/api/bootstrap')->assertOk();

    expect($response->json())
        ->actor->toBe('Tester')
        ->humans->toBe(['Tester'])
        ->bd_version->toBe('1.3.2')
        ->poll_interval->toBe(3)
        ->closed_window_days->toBe(30)
        ->and($response->json('health.state'))->toBe('ok')
        ->and($response->json('assets'))->toHaveKeys(['hot', 'published', 'stale', 'command'])
        ->and(array_column($response->json('statuses'), 'name'))->toBe(['deferred', 'pinned', 'on_hold', 'open', 'in_progress', 'hooked', 'in_review', 'blocked', 'closed'])
        ->and(array_column($response->json('types'), 'name'))->toContain('task', 'epic', 'gate');
});

it('reports the health state in the bootstrap payload without calling bd', function () {
    Process::fake();
    config(['beads.binary' => $this->sandbox('bin').'/nope']);
    app()->forgetInstance(BdBinary::class);
    app()->forgetInstance(HealthCheck::class);

    $this->getJson('/beads/api/bootstrap')
        ->assertOk()
        ->assertJsonPath('health.state', 'binary_missing')
        ->assertJsonPath('statuses', []);

    Process::assertNothingRan();
});

it('reports a schema migration in the bootstrap payload', function () {
    fakeBd($this, ['export' => Process::result('', '{"error":"database schema needs migration: run bd migrate"}', 1)]);

    $this->getJson('/beads/api/bootstrap')
        ->assertOk()
        ->assertJsonPath('health.state', 'schema_migration_required');
});

it('answers API calls with a 503 health payload when unhealthy', function () {
    fakeBd($this);
    @rmdir($this->sandbox('workspace/.beads/embeddeddolt/demo/.dolt/noms'));
    (new Filesystem)->deleteDirectory($this->sandbox('workspace').'/.beads');

    $this->getJson('/beads/api/snapshot')
        ->assertStatus(503)
        ->assertJsonPath('health.state', 'workspace_missing');

    $this->getJson('/beads/api/issues/demo-1')
        ->assertStatus(503)
        ->assertJsonPath('health.state', 'workspace_missing');
});

it('returns slim rows with relations and graph facts', function () {
    fakeBd($this);
    $ids = fixtureIds();

    $response = $this->getJson('/beads/api/snapshot')->assertOk()->assertHeader('ETag');

    $rows = collect($response->json('issues'))->keyBy('id');

    expect($rows)->toHaveCount(count($ids))
        ->and($rows->first())->not->toHaveKeys(['description', 'comments', 'design', 'notes'])
        ->and($rows[$ids['Payment step: support iDEAL']]['blocked_by'])->toBe([$ids['Upgrade the payment SDK to v5']])
        ->and($rows[$ids['Upgrade the payment SDK to v5']]['ready'])->toBeTrue()
        ->and($rows[$ids['Checkout redesign']]['progress'])->toBe(['done' => 1, 'total' => 4])
        ->and($response->json('relations'))->toContain(['from' => $ids['Payment step: support iDEAL'], 'to' => $ids['Upgrade the payment SDK to v5'], 'type' => 'blocks'])
        ->and($response->json('hash'))->toBeString();
});

it('answers 304 when the ETag matches', function () {
    fakeBd($this);

    $etag = $this->getJson('/beads/api/snapshot')->headers->get('ETag');

    $this->getJson('/beads/api/snapshot', ['If-None-Match' => $etag])
        ->assertStatus(304)
        ->assertHeader('ETag', $etag);

    // Only the first request exported.
    expect(bdCalls())->toBe(['export', 'statuses', 'types']);
});

it('answers 200 again after an outside change', function () {
    fakeBd($this);
    $etag = $this->getJson('/beads/api/snapshot')->headers->get('ETag');

    $changed = fixtureContents('export.jsonl')."\n".json_encode(['_type' => 'issue', 'id' => 'demo-new', 'title' => 'New']);
    fakeBd($this, ['export' => Process::result($changed)]);
    simulateExternalWrite($this);

    $response = $this->getJson('/beads/api/snapshot', ['If-None-Match' => $etag])->assertOk();

    expect($response->headers->get('ETag'))->not->toBe($etag)
        ->and(collect($response->json('issues'))->pluck('id'))->toContain('demo-new');
});

it('hides closed issues older than the window unless closed=all', function () {
    $export = implode("\n", [
        json_encode(['_type' => 'issue', 'id' => 'demo-open', 'title' => 'Open', 'status' => 'open']),
        json_encode(['_type' => 'issue', 'id' => 'demo-recent', 'title' => 'Recent', 'status' => 'closed', 'closed_at' => '2026-10-20T10:00:00Z']),
        json_encode(['_type' => 'issue', 'id' => 'demo-old', 'title' => 'Old', 'status' => 'closed', 'closed_at' => '2026-09-01T10:00:00Z']),
        json_encode(['_type' => 'issue', 'id' => 'demo-gate', 'title' => 'Gate', 'status' => 'open', 'issue_type' => 'gate']),
    ]);
    fakeBd($this, ['export' => Process::result($export)]);

    $window = $this->getJson('/beads/api/snapshot')->assertOk();
    $all = $this->getJson('/beads/api/snapshot?closed=all')->assertOk();

    expect(collect($window->json('issues'))->pluck('id')->all())->toBe(['demo-open', 'demo-recent'])
        ->and($window->json('hidden_closed'))->toBe(1)
        ->and($window->json('closed'))->toBe('window')
        ->and(collect($all->json('issues'))->pluck('id')->all())->toBe(['demo-open', 'demo-recent', 'demo-old'])
        ->and($all->json('closed'))->toBe('all')
        ->and($all->headers->get('ETag'))->not->toBe($window->headers->get('ETag'));
});

it('honours the configured closed window', function () {
    config(['beads.closed_window_days' => 10]);
    app()->forgetInstance(SnapshotPresenter::class);
    $export = json_encode(['_type' => 'issue', 'id' => 'demo-recent', 'title' => 'Recent', 'status' => 'closed', 'closed_at' => '2026-10-20T10:00:00Z']);
    fakeBd($this, ['export' => Process::result($export)]);

    expect($this->getJson('/beads/api/snapshot')->json('issues'))->toBe([]);
});

it('returns the full issue detail from the snapshot', function () {
    fakeBd($this);
    $ids = fixtureIds();
    $id = $ids['Payment step: support iDEAL'];

    $response = $this->getJson("/beads/api/issues/{$id}")->assertOk();

    expect($response->json('issue'))
        ->id->toBe($id)
        ->design->toContain('hosted page')
        ->blocked_by->toBe([$ids['Upgrade the payment SDK to v5']])
        ->ready->toBeFalse()
        ->author_is_human->toBeFalse()
        ->and($response->json('issue.comments'))->toHaveCount(2)
        ->and($response->json('relations.parent'))->toBe($ids['Checkout redesign'])
        ->and($response->json('relations.blocked_by'))->toBe([$ids['Upgrade the payment SDK to v5']])
        ->and($response->json('issues'))->toHaveKeys([$ids['Checkout redesign'], $ids['Upgrade the payment SDK to v5']]);

    // No extra bd call for the detail.
    expect(bdCalls())->toBe(['export', 'statuses', 'types']);
});

it('marks issues created by a configured human', function () {
    fakeBd($this);
    $ids = fixtureIds();

    $this->getJson('/beads/api/issues/'.$ids['Checkout redesign'])->assertJsonPath('issue.author_is_human', false);

    config(['beads.humans' => ['Jurrid']]);
    app()->forgetInstance(Actor::class);
    app()->forgetInstance(SnapshotPresenter::class);

    $this->getJson('/beads/api/issues/'.$ids['Checkout redesign'])->assertJsonPath('issue.author_is_human', true);
});

it('answers 404 for an unknown issue', function () {
    fakeBd($this);

    $this->getJson('/beads/api/issues/demo-unknown')
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');
});

it('answers 404 for an invalid ID without calling bd', function () {
    fakeBd($this);

    $this->getJson('/beads/api/issues/-rf')->assertNotFound();

    expect(bdCalls())->toBe([]);
});

it('answers 404 JSON for unknown API endpoints instead of the SPA shell', function () {
    $this->getJson('/beads/api/nope')->assertNotFound()->assertJsonPath('error.code', 'not_found');
});

it('answers 503 when the workspace lock times out', function () {
    fakeBd($this);
    config(['beads.lock_timeout' => 0.05]);
    app()->forgetInstance(WorkspaceLock::class);
    app()->forgetInstance(BdRunner::class);
    app()->forgetInstance(SnapshotRepository::class);

    $lock = app(WorkspaceLock::class);
    @mkdir(dirname($lock->lockFile), 0777, true);
    touch($lock->lockFile);
    $other = fopen($lock->lockFile, 'c');
    flock($other, LOCK_EX);

    try {
        $this->getJson('/beads/api/snapshot')
            ->assertStatus(503)
            ->assertHeader('Retry-After', '1')
            ->assertJsonPath('error.code', 'workspace_busy');
    } finally {
        flock($other, LOCK_UN);
        fclose($other);
    }
});

it('passes bd errors through as JSON', function () {
    fakeBd($this, ['export' => Process::result('', 'something broke', 1)]);

    $this->getJson('/beads/api/snapshot')
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'failed')
        ->assertJsonPath('error.message', 'something broke');
});
