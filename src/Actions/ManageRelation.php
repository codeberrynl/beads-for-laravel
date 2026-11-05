<?php

declare(strict_types=1);

namespace Codeberry\Beads\Actions;

use Codeberry\Beads\Cli\BdRunner;
use Codeberry\Beads\Data\Dependency;
use Codeberry\Beads\Snapshot\Snapshot;
use Codeberry\Beads\Snapshot\SnapshotRepository;
use InvalidArgumentException;

/**
 * Adds and removes relations, translated to the right bd command and
 * direction:
 *
 * - blocks, "blocked_by": target blocks the issue (bd dep add issue target)
 * - blocks, "blocking": the issue blocks target (bd dep add target issue)
 * - related: bd dep relate / unrelate (both directions at once)
 * - parent-child, "parent": target becomes the parent (bd update --parent)
 * - parent-child, "child": target becomes a child
 * - other types (remove only), "outgoing" or "incoming"
 */
final class ManageRelation
{
    /** Relation types that can be added from the UI. */
    public const EDITABLE = [Dependency::BLOCKS, Dependency::RELATED, Dependency::PARENT_CHILD];

    /** @var array<string, list<string>> */
    public const DIRECTIONS = [
        Dependency::BLOCKS => ['blocked_by', 'blocking'],
        Dependency::PARENT_CHILD => ['parent', 'child'],
        Dependency::RELATED => ['both'],
    ];

    public const OTHER_DIRECTIONS = ['outgoing', 'incoming'];

    public function __construct(
        private readonly BdRunner $runner,
        private readonly SnapshotRepository $snapshots,
    ) {}

    public function add(string $id, string $type, string $target, string $direction): Snapshot
    {
        [, $snapshot] = $this->snapshots->afterWrite(fn () => match ($type) {
            Dependency::BLOCKS => $direction === 'blocking'
                ? $this->runner->write(['dep', 'add', $target, $id], ['type' => Dependency::BLOCKS])
                : $this->runner->write(['dep', 'add', $id, $target], ['type' => Dependency::BLOCKS]),
            Dependency::RELATED => $this->runner->write(['dep', 'relate', $id, $target]),
            Dependency::PARENT_CHILD => $direction === 'child'
                ? $this->runner->write(['update', $target], ['parent' => $id])
                : $this->runner->write(['update', $id], ['parent' => $target]),
            default => throw new InvalidArgumentException("Relation type [{$type}] cannot be added."),
        });

        return $snapshot;
    }

    public function remove(Snapshot $current, string $id, string $type, string $target, string $direction): Snapshot
    {
        [, $snapshot] = $this->snapshots->afterWrite(function () use ($current, $id, $type, $target, $direction): void {
            match (true) {
                $type === Dependency::BLOCKS => $direction === 'blocking'
                    ? $this->runner->write(['dep', 'remove', $target, $id])
                    : $this->runner->write(['dep', 'remove', $id, $target]),
                $type === Dependency::RELATED || $type === Dependency::RELATES_TO => $this->removeRelated($current, $id, $target),
                $type === Dependency::PARENT_CHILD => $direction === 'child'
                    ? $this->runner->write(['update', $target], ['parent' => ''])
                    : $this->runner->write(['update', $id], ['parent' => '']),
                default => $direction === 'incoming'
                    ? $this->runner->write(['dep', 'remove', $target, $id])
                    : $this->runner->write(['dep', 'remove', $id, $target]),
            };
        });

        return $snapshot;
    }

    /**
     * "relates-to" links (bd dep relate) go with unrelate; plain "related"
     * edges are removed one by one, in whichever direction they exist.
     */
    private function removeRelated(Snapshot $current, string $id, string $target): void
    {
        $edges = [];

        foreach ([$id => $target, $target => $id] as $from => $to) {
            foreach ($current->find((string) $from)->dependencies ?? [] as $dependency) {
                if ($dependency->dependsOnId === $to && $dependency->isRelated()) {
                    $edges[] = $dependency;
                }
            }
        }

        $unrelated = false;

        foreach ($edges as $edge) {
            if ($edge->type === Dependency::RELATES_TO) {
                if (! $unrelated) {
                    $this->runner->write(['dep', 'unrelate', $id, $target]);
                    $unrelated = true;
                }

                continue;
            }

            $this->runner->write(['dep', 'remove', $edge->issueId, $edge->dependsOnId]);
        }

        if ($edges === []) {
            $this->runner->write(['dep', 'unrelate', $id, $target]);
        }
    }
}
