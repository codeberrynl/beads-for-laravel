<?php

declare(strict_types=1);

use Codeberry\Beads\Cli\BdRunner;
use Codeberry\Beads\Snapshot\Fingerprint;
use Codeberry\Beads\Snapshot\Snapshot;
use Codeberry\Beads\Snapshot\SnapshotRepository;
use Codeberry\Beads\Snapshot\SnapshotStore;
use Illuminate\Support\Facades\Process;

function repository(): SnapshotRepository
{
    // Fresh instances, like a new request.
    app()->forgetInstance(SnapshotRepository::class);

    return app(SnapshotRepository::class);
}

it('ignores lock files and last-touched in the fingerprint', function () {
    $beads = fakeWorkspace($this).'/.beads';
    $before = Fingerprint::of($beads);

    file_put_contents($beads.'/last-touched', 'demo-1');
    file_put_contents($beads.'/embeddeddolt.gate.lock', '');
    file_put_contents($beads.'/something.lock.tmp', '');

    expect(Fingerprint::of($beads))->toBe($before);

    file_put_contents($beads.'/issues.jsonl', '{}');

    expect(Fingerprint::of($beads))->not->toBe($before);
});

it('changes the fingerprint on mtime or size changes', function () {
    $beads = fakeWorkspace($this).'/.beads';
    $before = Fingerprint::of($beads);

    touch($beads.'/config.yaml', 1_700_000_050);
    $touched = Fingerprint::of($beads);

    expect($touched)->not->toBe($before);

    file_put_contents($beads.'/config.yaml', 'prefix: demo2');
    touch($beads.'/config.yaml', 1_700_000_050);

    expect(Fingerprint::of($beads))->not->toBe($touched);
});

it('exports once and serves the cache while the fingerprint is unchanged', function () {
    fakeBd($this);

    $snapshot = repository()->current();
    expect(bdCalls())->toBe(['export', 'statuses', 'types'])
        ->and($snapshot->issues())->toHaveCount(count(fixtureIds()));

    repository()->current();
    repository()->currentHash();

    expect(bdCalls())->toBe(['export', 'statuses', 'types']);
});

it('does not let its own export trigger the next poll', function () {
    fakeBd($this);

    repository()->current();
    $hash = repository()->currentHash();

    // The fake touched the Dolt files during every call, like bd does.
    expect(repository()->currentHash())->toBe($hash)
        ->and(bdCalls())->toHaveCount(3);
});

it('re-exports after an outside change', function () {
    fakeBd($this);
    repository()->current();

    simulateExternalWrite($this);
    repository()->current();

    // Definitions were fetched less than the TTL ago: only the export runs.
    expect(bdCalls())->toBe(['export', 'statuses', 'types', 'export']);
});

it('re-reads the definitions after the TTL', function () {
    fakeBd($this);
    repository()->current();

    $store = app(SnapshotStore::class);
    $meta = $store->meta();
    $store->writeMeta($meta['fingerprint'], $meta['hash'], $meta['exported_at'], time() - SnapshotRepository::DEFINITIONS_TTL - 1);

    simulateExternalWrite($this);
    repository()->current();

    expect(bdCalls())->toBe(['export', 'statuses', 'types', 'export', 'statuses', 'types']);
});

it('gives equal content an equal hash', function () {
    $a = Snapshot::make(fixtureContents('export.jsonl'), ['s' => 1], ['t' => 1]);
    $b = Snapshot::make(fixtureContents('export.jsonl'), ['s' => 1], ['t' => 1]);
    $c = Snapshot::make(fixtureContents('export.jsonl')."\n{\"id\":\"x\"}", ['s' => 1], ['t' => 1]);

    expect($a->hash)->toBe($b->hash)
        ->and($a->hash)->not->toBe($c->hash);
});

it('keeps the hash when an outside change leaves the export equal', function () {
    fakeBd($this);
    $hash = repository()->currentHash();

    simulateExternalWrite($this);

    expect(repository()->currentHash())->toBe($hash)
        ->and(bdCalls())->toBe(['export', 'statuses', 'types', 'export']);
});

it('refreshes after a write inside the same lock, without the definitions', function () {
    fakeBd($this);
    repository()->current();

    [$result, $snapshot] = repository()->afterWrite(fn () => app(BdRunner::class)->write(['update', 'demo-1'], ['title' => 'x']));

    expect(bdCalls())->toBe(['export', 'statuses', 'types', 'update', 'export'])
        ->and($snapshot)->toBeInstanceOf(Snapshot::class);

    repository()->current();
    expect(bdCalls())->toHaveCount(5);
});

it('runs extra reads quietly without invalidating the snapshot', function () {
    fakeBd($this);
    repository()->current();

    repository()->quietly(fn () => app(BdRunner::class)->read(['show', 'demo-1']));
    repository()->current();

    expect(bdCalls())->toBe(['export', 'statuses', 'types', 'show']);
});

it('still notices an outside change that happened before a quiet read', function () {
    fakeBd($this);
    repository()->current();

    simulateExternalWrite($this);
    repository()->quietly(fn () => app(BdRunner::class)->read(['show', 'demo-1']));
    repository()->current();

    expect(bdCalls())->toBe(['export', 'statuses', 'types', 'show', 'export']);
});

it('stores the snapshot under storage/framework/beads/<sha1(workspace)>', function () {
    fakeBd($this);
    repository()->current();

    $directory = storage_path('framework/beads/'.sha1(realpath($this->sandbox('workspace'))));

    expect($directory.'/snapshot.json')->toBeFile()
        ->and($directory.'/meta.json')->toBeFile();
});

it('re-reads the definitions when an issue uses an unknown status', function () {
    $export = json_encode(['_type' => 'issue', 'id' => 'demo-1', 'title' => 'New', 'status' => 'qa_testing']);
    fakeBd($this);
    repository()->current();

    fakeBd($this, ['export' => Process::result($export)]);
    repository()->afterWrite(fn () => null);

    // After the first full refresh: the export, then the definitions again.
    expect(array_slice(bdCalls(), 3))->toBe(['export', 'statuses', 'types']);
});
