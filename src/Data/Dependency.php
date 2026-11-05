<?php

declare(strict_types=1);

namespace Codeberry\Beads\Data;

use JsonSerializable;

/**
 * An edge in the bd graph: $issueId depends on $dependsOnId with $type.
 *
 * "blocks" means dependsOn blocks issue; "parent-child" means dependsOn is
 * the parent of issue.
 */
final class Dependency implements JsonSerializable
{
    public const BLOCKS = 'blocks';

    public const PARENT_CHILD = 'parent-child';

    public const RELATED = 'related';

    public const RELATES_TO = 'relates-to';

    public function __construct(
        public readonly string $issueId,
        public readonly string $dependsOnId,
        public readonly string $type,
        public readonly ?string $createdAt = null,
        public readonly ?string $createdBy = null,
    ) {}

    /**
     * Accepts the export shape ({issue_id, depends_on_id, type}) and the
     * show shape (an embedded issue with id and dependency_type).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, ?string $issueId = null): ?self
    {
        $from = Cast::nullableString($data['issue_id'] ?? null) ?? $issueId;
        $to = Cast::nullableString($data['depends_on_id'] ?? null) ?? Cast::nullableString($data['id'] ?? null);
        $type = Cast::nullableString($data['type'] ?? null) ?? Cast::nullableString($data['dependency_type'] ?? null) ?? self::BLOCKS;

        if ($from === null || $to === null) {
            return null;
        }

        return new self(
            $from,
            $to,
            $type,
            Cast::nullableString($data['created_at'] ?? null),
            Cast::nullableString($data['created_by'] ?? null),
        );
    }

    public function isRelated(): bool
    {
        return $this->type === self::RELATED || $this->type === self::RELATES_TO;
    }

    /**
     * @return array{issue_id: string, depends_on_id: string, type: string, created_at: ?string, created_by: ?string}
     */
    public function jsonSerialize(): array
    {
        return [
            'issue_id' => $this->issueId,
            'depends_on_id' => $this->dependsOnId,
            'type' => $this->type,
            'created_at' => $this->createdAt,
            'created_by' => $this->createdBy,
        ];
    }
}
