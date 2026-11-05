<?php

declare(strict_types=1);

namespace Codeberry\Beads\Data;

use JsonSerializable;

/**
 * A bd status with its category (active, wip, frozen, done; or null for
 * legacy statuses without a category).
 */
final class StatusDefinition implements JsonSerializable
{
    public const CATEGORIES = ['frozen', 'active', 'wip', 'done'];

    public function __construct(
        public readonly string $name,
        public readonly ?string $category,
        public readonly ?string $icon = null,
        public readonly ?string $description = null,
        public readonly bool $builtIn = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, bool $builtIn = false): ?self
    {
        $name = Cast::nullableString($data['name'] ?? null);

        if ($name === null) {
            return null;
        }

        $category = Cast::nullableString($data['category'] ?? null);

        return new self(
            $name,
            $category !== null ? strtolower($category) : null,
            Cast::nullableString($data['icon'] ?? null),
            Cast::nullableString($data['description'] ?? null),
            $builtIn,
        );
    }

    /**
     * Maps `bd statuses --json` ({built_in_statuses, custom_statuses}) or a
     * plain list of definitions.
     *
     * @return list<self>
     */
    public static function listFrom(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $groups = array_is_list($payload)
            ? [[$payload, false]]
            : [[$payload['built_in_statuses'] ?? [], true], [$payload['custom_statuses'] ?? [], false]];

        $definitions = [];

        foreach ($groups as [$rows, $builtIn]) {
            foreach (is_array($rows) ? $rows : [] as $row) {
                $definition = match (true) {
                    is_array($row) => self::fromArray($row, $builtIn),
                    is_string($row) && $row !== '' => self::fromString($row, $builtIn),
                    default => null,
                };

                if ($definition !== null && ! isset($definitions[$definition->name])) {
                    $definitions[$definition->name] = $definition;
                }
            }
        }

        return array_values($definitions);
    }

    /**
     * "in_review:wip" style (as in status.custom).
     */
    public static function fromString(string $value, bool $builtIn = false): self
    {
        [$name, $category] = array_pad(explode(':', $value, 2), 2, null);

        return new self(trim((string) $name), $category !== null && trim($category) !== '' ? strtolower(trim($category)) : null, builtIn: $builtIn);
    }

    /**
     * @return array{name: string, category: ?string, icon: ?string, description: ?string, built_in: bool}
     */
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'category' => $this->category,
            'icon' => $this->icon,
            'description' => $this->description,
            'built_in' => $this->builtIn,
        ];
    }
}
