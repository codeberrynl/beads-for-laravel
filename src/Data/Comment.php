<?php

declare(strict_types=1);

namespace Codeberry\Beads\Data;

use JsonSerializable;

final class Comment implements JsonSerializable
{
    public function __construct(
        public readonly string $id,
        public readonly string $issueId,
        public readonly string $author,
        public readonly string $text,
        public readonly ?string $createdAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, ?string $issueId = null): self
    {
        return new self(
            Cast::string($data['id'] ?? null),
            Cast::nullableString($data['issue_id'] ?? null) ?? (string) $issueId,
            Cast::string($data['author'] ?? null),
            Cast::string($data['text'] ?? ($data['body'] ?? null)),
            Cast::nullableString($data['created_at'] ?? null),
        );
    }

    /**
     * @return array{id: string, issue_id: string, author: string, text: string, created_at: ?string}
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'issue_id' => $this->issueId,
            'author' => $this->author,
            'text' => $this->text,
            'created_at' => $this->createdAt,
        ];
    }
}
