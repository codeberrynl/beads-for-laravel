<?php

declare(strict_types=1);

namespace Codeberry\Beads\Actions;

use Codeberry\Beads\Cli\BdRunner;
use Codeberry\Beads\Snapshot\Snapshot;
use Codeberry\Beads\Snapshot\SnapshotRepository;
use Codeberry\Beads\Workspace\Actor;

final class AddComment
{
    public function __construct(
        private readonly BdRunner $runner,
        private readonly SnapshotRepository $snapshots,
        private readonly Actor $actor,
    ) {}

    public function handle(string $id, string $text): Snapshot
    {
        // The text is free-form, so it goes after "--".
        [, $snapshot] = $this->snapshots->afterWrite(fn () => $this->runner->write(
            ['comments', 'add', $id],
            ['author' => $this->actor->name()],
            [$text],
        ));

        return $snapshot;
    }
}
