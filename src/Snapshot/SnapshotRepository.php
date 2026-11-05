<?php

declare(strict_types=1);

namespace Codeberry\Beads\Snapshot;

use Closure;
use Codeberry\Beads\Cli\BdRunner;
use Codeberry\Beads\Workspace\Workspace;

/**
 * The fingerprint-gated snapshot of the workspace.
 *
 * Every bd call (also a read) touches the Dolt files, so the fingerprint is
 * always recorded *after* our own calls: our own reads never trigger a new
 * export, while any outside change does.
 */
final class SnapshotRepository
{
    /** Re-read the status and type definitions at most this often (seconds). */
    public const DEFINITIONS_TTL = 30;

    private ?Snapshot $loaded = null;

    public function __construct(
        private readonly BdRunner $runner,
        private readonly SnapshotStore $store,
        private readonly Workspace $workspace,
    ) {}

    public function current(): Snapshot
    {
        $meta = $this->ensureFresh();

        if ($this->loaded !== null && $this->loaded->hash === $meta['hash']) {
            return $this->loaded;
        }

        return $this->loaded = $this->store->load() ?? $this->refresh(true);
    }

    /**
     * The content hash of the current snapshot, without loading it.
     */
    public function currentHash(): string
    {
        return $this->ensureFresh()['hash'];
    }

    /**
     * Export (and optionally re-read the definitions) inside the lock and
     * store the snapshot with the fingerprint taken after our own calls.
     */
    public function refresh(bool $withDefinitions = true): Snapshot
    {
        return $this->runner->locked(function () use ($withDefinitions): Snapshot {
            $previous = $this->store->load();
            $meta = $this->store->meta();

            $export = $this->runner->read(['export'])->output;

            $refreshDefinitions = $withDefinitions || $previous === null;
            $statuses = $previous?->statuses;
            $types = $previous?->types;

            if ($refreshDefinitions) {
                $statuses = $this->runner->read(['statuses'])->json();
                $types = $this->runner->read(['types'])->json();
            }

            $snapshot = Snapshot::make($export, $statuses, $types);

            if (! $refreshDefinitions && $snapshot->hasUnknownStatuses()) {
                $statuses = $this->runner->read(['statuses'])->json();
                $types = $this->runner->read(['types'])->json();
                $snapshot = Snapshot::make($export, $statuses, $types);
                $refreshDefinitions = true;
            }

            $definitionsAt = $refreshDefinitions ? time() : ($meta['definitions_at'] ?? 0);

            $this->store->save($snapshot, Fingerprint::of($this->workspace->beadsDirectory()), $definitionsAt);

            return $this->loaded = $snapshot;
        });
    }

    /**
     * Run a write and refresh the snapshot inside the same lock.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $write
     * @return array{0: TReturn, 1: Snapshot}
     */
    public function afterWrite(Closure $write): array
    {
        return $this->runner->locked(function () use ($write): array {
            $result = $write();

            return [$result, $this->refresh(false)];
        });
    }

    /**
     * Run extra reads (bd show, ...) without invalidating the snapshot: when
     * nothing else changed before the call, the fingerprint taken after the
     * call is recorded as current.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function quietly(Closure $callback): mixed
    {
        return $this->runner->locked(function () use ($callback): mixed {
            $before = Fingerprint::of($this->workspace->beadsDirectory());
            $result = $callback();

            if (($this->store->meta()['fingerprint'] ?? null) === $before) {
                $this->store->updateFingerprint(Fingerprint::of($this->workspace->beadsDirectory()));
            }

            return $result;
        });
    }

    /**
     * @return array{fingerprint: string, hash: string, exported_at: string, definitions_at: int}
     */
    private function ensureFresh(): array
    {
        $meta = $this->store->meta();

        if ($meta !== null && $meta['fingerprint'] === Fingerprint::of($this->workspace->beadsDirectory())) {
            return $meta;
        }

        return $this->runner->locked(function (): array {
            // Another request may have refreshed while we waited for the lock.
            $meta = $this->store->meta();

            if ($meta !== null && $meta['fingerprint'] === Fingerprint::of($this->workspace->beadsDirectory())) {
                return $meta;
            }

            $stale = $meta === null || (time() - $meta['definitions_at']) >= self::DEFINITIONS_TTL;
            $this->refresh($stale);

            return (array) $this->store->meta() + ['fingerprint' => '', 'hash' => '', 'exported_at' => '', 'definitions_at' => 0];
        });
    }
}
