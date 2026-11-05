<?php

declare(strict_types=1);

namespace Codeberry\Beads\Data;

use JsonSerializable;

final class IssueTypeDefinition implements JsonSerializable
{
    public const KIND_CORE = 'core';

    public const KIND_SYSTEM = 'system';

    public const KIND_CUSTOM = 'custom';

    /** System types are hidden by default. */
    public const SYSTEM_TYPES = ['message', 'molecule', 'gate', 'event'];

    public function __construct(
        public readonly string $name,
        public readonly string $kind = self::KIND_CORE,
        public readonly ?string $description = null,
    ) {}

    public function isSystem(): bool
    {
        return $this->kind === self::KIND_SYSTEM;
    }

    /**
     * Maps `bd types --json` ({core_types, system_types, custom_types}; the
     * custom types are plain strings in bd 1.3) or a plain list.
     *
     * @return list<self>
     */
    public static function listFrom(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $groups = array_is_list($payload)
            ? [[$payload, self::KIND_CORE]]
            : [
                [$payload['core_types'] ?? [], self::KIND_CORE],
                [$payload['custom_types'] ?? [], self::KIND_CUSTOM],
                [$payload['system_types'] ?? [], self::KIND_SYSTEM],
            ];

        $definitions = [];

        foreach ($groups as [$rows, $kind]) {
            foreach (is_array($rows) ? $rows : [] as $row) {
                $name = is_array($row) ? Cast::nullableString($row['name'] ?? null) : Cast::nullableString($row);

                if ($name === null || isset($definitions[$name])) {
                    continue;
                }

                $resolvedKind = in_array($name, self::SYSTEM_TYPES, true) ? self::KIND_SYSTEM : $kind;
                $description = is_array($row) ? Cast::nullableString($row['description'] ?? null) : null;

                $definitions[$name] = new self($name, $resolvedKind, $description);
            }
        }

        return array_values($definitions);
    }

    /**
     * @return array{name: string, kind: string, description: ?string}
     */
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'kind' => $this->kind,
            'description' => $this->description,
        ];
    }
}
