<?php

declare(strict_types=1);

namespace Codeberry\Beads\Data;

use JsonSerializable;

/**
 * The slim list row sent to the client: no long text and no comments,
 * plus the graph facts the list needs.
 */
final class IssueRow implements JsonSerializable
{
    /**
     * @param  list<string>  $labels
     * @param  list<string>  $blockedBy  open blockers (issue IDs)
     * @param  array{done: int, total: int}|null  $progress  for issues with children
     */
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $status,
        public readonly int $priority,
        public readonly string $type,
        public readonly ?string $assignee,
        public readonly ?string $createdBy,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
        public readonly ?string $closedAt,
        public readonly ?string $deferUntil,
        public readonly ?string $dueAt,
        public readonly ?string $parent,
        public readonly array $labels,
        public readonly int $commentCount,
        public readonly array $blockedBy = [],
        public readonly bool $ready = false,
        public readonly ?array $progress = null,
    ) {}

    /**
     * @param  list<string>  $blockedBy
     * @param  array{done: int, total: int}|null  $progress
     */
    public static function fromIssue(Issue $issue, array $blockedBy = [], bool $ready = false, ?array $progress = null): self
    {
        return new self(
            $issue->id,
            $issue->title,
            $issue->status,
            $issue->priority,
            $issue->type,
            $issue->assignee,
            $issue->createdBy,
            $issue->createdAt,
            $issue->updatedAt,
            $issue->closedAt,
            $issue->deferUntil,
            $issue->dueAt,
            $issue->parent,
            $issue->labels,
            $issue->commentCount,
            $blockedBy,
            $ready,
            $progress,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => $this->status,
            'priority' => $this->priority,
            'type' => $this->type,
            'assignee' => $this->assignee,
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'closed_at' => $this->closedAt,
            'defer_until' => $this->deferUntil,
            'due_at' => $this->dueAt,
            'parent' => $this->parent,
            'labels' => $this->labels,
            'comment_count' => $this->commentCount,
            'blocked_by' => $this->blockedBy,
            'ready' => $this->ready,
            'progress' => $this->progress,
        ];
    }
}
