<?php

declare(strict_types=1);

namespace Codeberry\Beads\Actions;

use Codeberry\Beads\Cli\BdRunner;
use Codeberry\Beads\Snapshot\Snapshot;
use Codeberry\Beads\Snapshot\SnapshotRepository;

final class ReopenIssue
{
    public function __construct(
        private readonly BdRunner $runner,
        private readonly SnapshotRepository $snapshots,
    ) {}

    public function handle(string $id, ?string $reason = null): Snapshot
    {
        $flags = ['reason' => $reason !== null && trim($reason) !== '' ? $reason : null];

        [, $snapshot] = $this->snapshots->afterWrite(fn () => $this->runner->write(['reopen', $id], $flags));

        return $snapshot;
    }
}
