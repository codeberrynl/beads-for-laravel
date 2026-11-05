<?php

declare(strict_types=1);

namespace Codeberry\Beads\Actions;

use Codeberry\Beads\Cli\BdRunner;
use Codeberry\Beads\Cli\Exceptions\BdException;
use Codeberry\Beads\Data\Issue;
use Codeberry\Beads\Snapshot\Snapshot;
use Codeberry\Beads\Snapshot\SnapshotRepository;

/**
 * bd close, with an optional reason. Force is only allowed for pinned
 * issues and gates; for everything else bd's refusal (open children, an
 * open blocker) is passed on so the UI can roll back.
 */
final class CloseIssue
{
    public function __construct(
        private readonly BdRunner $runner,
        private readonly SnapshotRepository $snapshots,
    ) {}

    public static function canForce(Issue $issue): bool
    {
        return $issue->status === 'pinned' || $issue->type === 'gate';
    }

    public function handle(Issue $issue, ?string $reason = null, bool $force = false): Snapshot
    {
        if ($force && ! self::canForce($issue)) {
            throw new BdException(BdException::FAILED, 'Force close is only allowed for pinned issues and gates.');
        }

        $flags = [
            'reason' => $reason !== null && trim($reason) !== '' ? $reason : null,
            'force' => $force,
        ];

        [, $snapshot] = $this->snapshots->afterWrite(fn () => $this->runner->write(['close', $issue->id], $flags));

        return $snapshot;
    }
}
