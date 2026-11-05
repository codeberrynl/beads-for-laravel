<?php

declare(strict_types=1);

namespace Codeberry\Beads\Snapshot;

use Carbon\CarbonImmutable;
use Codeberry\Beads\Data\Issue;
use Codeberry\Beads\Data\IssueRow;
use Codeberry\Beads\Graph\IssueGraph;
use Codeberry\Beads\Workspace\Actor;
use DateTimeInterface;
use Exception;

/**
 * Builds the JSON payloads of the read API from a snapshot.
 */
final class SnapshotPresenter
{
    public function __construct(
        private readonly Actor $actor,
        private readonly int $closedWindowDays,
    ) {}

    /**
     * The ETag of the snapshot payload for a variant.
     */
    public function etag(string $hash, bool $allClosed, ?DateTimeInterface $now = null): string
    {
        $now = CarbonImmutable::instance($now ?? CarbonImmutable::now());
        $variant = $allClosed ? 'all' : 'w'.$this->closedWindowDays.'-'.$now->utc()->format('Ymd');

        return '"'.$hash.'-'.$variant.'"';
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(Snapshot $snapshot, bool $allClosed, ?DateTimeInterface $now = null): array
    {
        $now = CarbonImmutable::instance($now ?? CarbonImmutable::now());
        $graph = $snapshot->graph($now);
        $catalog = $graph->statuses();
        $cutoff = $now->subDays($this->closedWindowDays);

        $rows = [];
        $relations = [];
        $hiddenClosed = 0;

        foreach ($graph->issues() as $issue) {
            if ($graph->isSystem($issue)) {
                continue;
            }

            if (! $allClosed && $catalog->isDone($issue->status) && ! $this->closedSince($issue, $cutoff)) {
                $hiddenClosed++;

                continue;
            }

            $rows[] = $this->row($issue, $graph);

            foreach ($issue->dependencies as $dependency) {
                $relations[] = ['from' => $dependency->issueId, 'to' => $dependency->dependsOnId, 'type' => $dependency->type];
            }
        }

        return [
            'hash' => $snapshot->hash,
            'exported_at' => $snapshot->exportedAt,
            'closed' => $allClosed ? 'all' : 'window',
            'hidden_closed' => $hiddenClosed,
            'issues' => $rows,
            'relations' => $relations,
        ];
    }

    /**
     * The full detail of one issue, with its relations and the rows of every
     * issue it refers to.
     *
     * @return array<string, mixed>|null
     */
    public function issue(Snapshot $snapshot, string $id, ?DateTimeInterface $now = null): ?array
    {
        $graph = $snapshot->graph(CarbonImmutable::instance($now ?? CarbonImmutable::now()));
        $issue = $graph->issue($id);

        if ($issue === null) {
            return null;
        }

        $relations = [
            'parent' => $issue->parent,
            'children' => $graph->children($id),
            'blocked_by' => $graph->blockers($id),
            'blocking' => $graph->blocking($id),
            'related' => $graph->related($id),
            'other' => $graph->otherRelations($id),
        ];

        $referenced = array_merge(
            $issue->parent !== null ? [$issue->parent] : [],
            $relations['children'],
            $relations['blocked_by'],
            $relations['blocking'],
            $relations['related'],
        );

        foreach ($relations['other'] as $dependency) {
            $referenced[] = $dependency->issueId;
            $referenced[] = $dependency->dependsOnId;
        }

        $rows = [];

        foreach (array_unique($referenced) as $referencedId) {
            $referencedIssue = $graph->issue($referencedId);

            if ($referencedIssue !== null && $referencedId !== $id) {
                $rows[$referencedId] = $this->row($referencedIssue, $graph);
            }
        }

        return [
            'hash' => $snapshot->hash,
            'issue' => array_merge($issue->jsonSerialize(), [
                'blocked_by' => $graph->blockedBy($id),
                'ready' => $graph->isReady($id),
                'progress' => $graph->progress($id),
                'author_is_human' => $this->actor->isHuman($issue->createdBy),
            ]),
            'relations' => $relations,
            'issues' => (object) $rows,
        ];
    }

    public function row(Issue $issue, IssueGraph $graph): IssueRow
    {
        return IssueRow::fromIssue(
            $issue,
            $graph->blockedBy($issue->id),
            $graph->isReady($issue->id),
            $graph->progress($issue->id),
        );
    }

    private function closedSince(Issue $issue, CarbonImmutable $cutoff): bool
    {
        $closedAt = $issue->closedAt ?? $issue->updatedAt;

        if ($closedAt === null) {
            return true;
        }

        try {
            return CarbonImmutable::parse($closedAt)->greaterThanOrEqualTo($cutoff);
        } catch (Exception) {
            return true;
        }
    }
}
