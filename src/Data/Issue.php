<?php

declare(strict_types=1);

namespace Codeberry\Beads\Data;

use JsonSerializable;

/**
 * A full bd issue, mapped tolerantly from `bd export` or `bd show` output.
 * Unknown fields are ignored and missing fields get defaults.
 */
final class Issue implements JsonSerializable
{
    /** Text fields that are edited as markdown, keyed by API name. */
    public const TEXT_FIELDS = ['description', 'design', 'acceptance_criteria', 'notes'];

    /**
     * @param  list<string>  $labels
     * @param  list<Dependency>  $dependencies
     * @param  list<Comment>  $comments
     */
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $status = 'open',
        public readonly int $priority = 2,
        public readonly string $type = 'task',
        public readonly string $description = '',
        public readonly string $design = '',
        public readonly string $acceptanceCriteria = '',
        public readonly string $notes = '',
        public readonly ?string $assignee = null,
        public readonly ?string $owner = null,
        public readonly ?string $createdBy = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
        public readonly ?string $startedAt = null,
        public readonly ?string $closedAt = null,
        public readonly ?string $closeReason = null,
        public readonly ?string $deferUntil = null,
        public readonly ?string $dueAt = null,
        public readonly ?string $externalRef = null,
        public readonly ?string $parent = null,
        public readonly array $labels = [],
        public readonly array $dependencies = [],
        public readonly array $comments = [],
        public readonly int $commentCount = 0,
        public readonly ?string $revision = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $id = Cast::string($data['id'] ?? null);

        $dependencies = [];
        foreach (Cast::rows($data['dependencies'] ?? null) as $row) {
            $dependency = Dependency::fromArray($row, $id);

            if ($dependency !== null && $dependency->issueId === $id) {
                $dependencies[] = $dependency;
            }
        }

        $comments = array_map(fn (array $row): Comment => Comment::fromArray($row, $id), Cast::rows($data['comments'] ?? null));

        $parent = Cast::nullableString($data['parent'] ?? null);
        if ($parent === null) {
            foreach ($dependencies as $dependency) {
                if ($dependency->type === Dependency::PARENT_CHILD) {
                    $parent = $dependency->dependsOnId;
                    break;
                }
            }
        }

        $labels = Cast::strings($data['labels'] ?? null);
        sort($labels);

        return new self(
            id: $id,
            title: Cast::string($data['title'] ?? null),
            status: Cast::nullableString($data['status'] ?? null) ?? 'open',
            priority: Cast::int($data['priority'] ?? null, 2),
            type: Cast::nullableString($data['issue_type'] ?? null) ?? Cast::nullableString($data['type'] ?? null) ?? 'task',
            description: Cast::string($data['description'] ?? null),
            design: Cast::string($data['design'] ?? null),
            acceptanceCriteria: Cast::string($data['acceptance_criteria'] ?? null),
            notes: Cast::string($data['notes'] ?? null),
            assignee: Cast::nullableString($data['assignee'] ?? null),
            owner: Cast::nullableString($data['owner'] ?? null),
            createdBy: Cast::nullableString($data['created_by'] ?? null),
            createdAt: Cast::nullableString($data['created_at'] ?? null),
            updatedAt: Cast::nullableString($data['updated_at'] ?? null),
            startedAt: Cast::nullableString($data['started_at'] ?? null),
            closedAt: Cast::nullableString($data['closed_at'] ?? null),
            closeReason: Cast::nullableString($data['close_reason'] ?? null),
            deferUntil: Cast::nullableString($data['defer_until'] ?? null),
            dueAt: Cast::nullableString($data['due_at'] ?? ($data['due'] ?? null)),
            externalRef: Cast::nullableString($data['external_ref'] ?? null),
            parent: $parent,
            labels: $labels,
            dependencies: $dependencies,
            comments: $comments,
            commentCount: max(Cast::int($data['comment_count'] ?? null, 0), count($comments)),
            revision: Cast::nullableString($data['revision'] ?? null),
        );
    }

    /**
     * bd show returns an array, also for one ID; older output may be an
     * object. Returns the issue with the given ID (or the first one).
     */
    public static function fromShow(mixed $payload, ?string $id = null): ?self
    {
        if (! is_array($payload)) {
            return null;
        }

        $rows = array_is_list($payload) ? Cast::rows($payload) : [$payload];

        foreach ($rows as $row) {
            if ($id === null || ($row['id'] ?? null) === $id) {
                return self::fromArray($row);
            }
        }

        return null;
    }

    /**
     * The value of a markdown text field by API name.
     */
    public function text(string $field): string
    {
        return match ($field) {
            'title' => $this->title,
            'description' => $this->description,
            'design' => $this->design,
            'acceptance_criteria' => $this->acceptanceCriteria,
            'notes' => $this->notes,
            default => '',
        };
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
            'description' => $this->description,
            'design' => $this->design,
            'acceptance_criteria' => $this->acceptanceCriteria,
            'notes' => $this->notes,
            'assignee' => $this->assignee,
            'owner' => $this->owner,
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'started_at' => $this->startedAt,
            'closed_at' => $this->closedAt,
            'close_reason' => $this->closeReason,
            'defer_until' => $this->deferUntil,
            'due_at' => $this->dueAt,
            'external_ref' => $this->externalRef,
            'parent' => $this->parent,
            'labels' => $this->labels,
            'dependencies' => $this->dependencies,
            'comments' => $this->comments,
            'comment_count' => $this->commentCount,
        ];
    }
}
