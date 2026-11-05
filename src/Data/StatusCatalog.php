<?php

declare(strict_types=1);

namespace Codeberry\Beads\Data;

/**
 * Status lookups by category, with the UI order
 * frozen → active → wip → blocked → done.
 */
final class StatusCatalog
{
    /** Categories of the built-in statuses, for when definitions are missing. */
    public const BUILT_IN = [
        'open' => 'active',
        'in_progress' => 'wip',
        'blocked' => 'wip',
        'deferred' => 'frozen',
        'closed' => 'done',
        'pinned' => 'frozen',
        'hooked' => 'wip',
    ];

    /** @var array<string, StatusDefinition> */
    private array $definitions = [];

    /**
     * @param  list<StatusDefinition>  $definitions
     */
    public function __construct(array $definitions = [])
    {
        foreach ($definitions as $definition) {
            $this->definitions[$definition->name] = $definition;
        }
    }

    public function category(string $status): ?string
    {
        return $this->definitions[$status]->category ?? self::BUILT_IN[$status] ?? null;
    }

    public function isDone(string $status): bool
    {
        return $this->category($status) === 'done';
    }

    public function isActive(string $status): bool
    {
        return $this->category($status) === 'active';
    }

    public function rank(string $status): int
    {
        if ($status === 'blocked') {
            return 4;
        }

        return match ($this->category($status)) {
            'frozen' => 0,
            'active' => 1,
            'wip' => 2,
            'done' => 5,
            default => 3,
        };
    }

    /**
     * All known statuses in UI order (stable within a category).
     *
     * @return list<StatusDefinition>
     */
    public function ordered(): array
    {
        $definitions = $this->definitions;

        foreach (self::BUILT_IN as $name => $category) {
            $definitions[$name] ??= new StatusDefinition($name, $category, builtIn: true);
        }

        $positions = array_flip(array_keys($definitions));
        $list = array_values($definitions);

        usort($list, fn (StatusDefinition $a, StatusDefinition $b): int => [$this->rank($a->name), $positions[$a->name]] <=> [$this->rank($b->name), $positions[$b->name]]);

        return $list;
    }
}
