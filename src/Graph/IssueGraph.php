<?php

declare(strict_types=1);

namespace Codeberry\Beads\Graph;

use Codeberry\Beads\Data\Dependency;
use Codeberry\Beads\Data\Issue;
use Codeberry\Beads\Data\IssueTypeDefinition;
use Codeberry\Beads\Data\StatusCatalog;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;

/**
 * Relations, ready/blocked and epic progress, derived from one export.
 *
 * Ready mirrors `bd ready`: a status in category "active", not a system
 * type, no open blocker (a "blocks" dependency on an issue that is not in
 * category "done"), not under a parent that is blocked by dependencies
 * (this propagates down the tree), no defer_until in the future and no
 * direct parent with a defer_until in the future.
 *
 * Blocked means status "blocked" or blocked by dependencies.
 */
final class IssueGraph
{
    /** @var array<string, Issue> */
    private array $issues = [];

    /** @var array<string, list<string>> */
    private array $children = [];

    /** @var array<string, list<string>> */
    private array $blockers = [];

    /** @var array<string, list<string>> */
    private array $blocking = [];

    /** @var array<string, list<string>> */
    private array $related = [];

    /** @var array<string, list<Dependency>> */
    private array $other = [];

    /** @var array<string, bool> */
    private array $dependencyBlocked = [];

    private readonly DateTimeImmutable $now;

    /**
     * @param  iterable<Issue>  $issues
     */
    public function __construct(iterable $issues, private readonly StatusCatalog $statuses, ?DateTimeInterface $now = null)
    {
        $this->now = $now !== null ? DateTimeImmutable::createFromInterface($now) : new DateTimeImmutable;

        foreach ($issues as $issue) {
            $this->issues[$issue->id] = $issue;
        }

        foreach ($this->issues as $issue) {
            if ($issue->parent !== null) {
                $this->children[$issue->parent][] = $issue->id;
            }

            foreach ($issue->dependencies as $dependency) {
                match (true) {
                    $dependency->type === Dependency::PARENT_CHILD => null,
                    $dependency->type === Dependency::BLOCKS => $this->addBlocks($dependency),
                    $dependency->isRelated() => $this->addRelated($dependency),
                    default => $this->addOther($dependency),
                };
            }
        }
    }

    public function statuses(): StatusCatalog
    {
        return $this->statuses;
    }

    /**
     * @return array<string, Issue>
     */
    public function issues(): array
    {
        return $this->issues;
    }

    public function issue(string $id): ?Issue
    {
        return $this->issues[$id] ?? null;
    }

    public function parent(string $id): ?string
    {
        return $this->issues[$id]->parent ?? null;
    }

    /**
     * @return list<string>
     */
    public function children(string $id): array
    {
        return $this->children[$id] ?? [];
    }

    /**
     * Issues that block this one (all, also closed ones).
     *
     * @return list<string>
     */
    public function blockers(string $id): array
    {
        return $this->blockers[$id] ?? [];
    }

    /**
     * Issues this one blocks.
     *
     * @return list<string>
     */
    public function blocking(string $id): array
    {
        return $this->blocking[$id] ?? [];
    }

    /**
     * Blockers that are not done yet.
     *
     * @return list<string>
     */
    public function openBlockers(string $id): array
    {
        return array_values(array_filter($this->blockers($id), fn (string $blocker): bool => $this->isOpenBlocker($blocker)));
    }

    /**
     * Related issues (related and relates-to, both directions).
     *
     * @return list<string>
     */
    public function related(string $id): array
    {
        return $this->related[$id] ?? [];
    }

    /**
     * Other dependency types touching this issue, in either direction.
     *
     * @return list<Dependency>
     */
    public function otherRelations(string $id): array
    {
        return $this->other[$id] ?? [];
    }

    /**
     * What this issue waits on: open blockers, or its parent when that is
     * blocked by dependencies (as `bd blocked` reports it).
     *
     * @return list<string>
     */
    public function blockedBy(string $id): array
    {
        $blockedBy = $this->openBlockers($id);
        $parent = $this->parent($id);

        if ($parent !== null && isset($this->issues[$parent]) && $this->isBlockedByDependencies($parent)) {
            $blockedBy[] = $parent;
        }

        return array_values(array_unique($blockedBy));
    }

    public function isBlockedByDependencies(string $id): bool
    {
        return $this->dependencyBlocked[$id] ??= $this->computeDependencyBlocked($id, []);
    }

    public function isBlocked(string $id): bool
    {
        $issue = $this->issue($id);

        if ($issue === null || $this->statuses->isDone($issue->status)) {
            return false;
        }

        return $issue->status === 'blocked' || $this->isBlockedByDependencies($id);
    }

    public function isReady(string $id): bool
    {
        $issue = $this->issue($id);

        if ($issue === null || $this->isSystem($issue) || ! $this->statuses->isActive($issue->status)) {
            return false;
        }

        if ($this->isDeferred($issue)) {
            return false;
        }

        $parent = $issue->parent !== null ? $this->issue($issue->parent) : null;

        if ($parent !== null && $this->isDeferred($parent)) {
            return false;
        }

        return ! $this->isBlockedByDependencies($id);
    }

    /**
     * Children in category done ÷ all children. Null without children.
     *
     * @return array{done: int, total: int}|null
     */
    public function progress(string $id): ?array
    {
        $children = $this->children($id);

        if ($children === []) {
            return null;
        }

        $done = count(array_filter($children, fn (string $child): bool => $this->statuses->isDone($this->issues[$child]->status)));

        return ['done' => $done, 'total' => count($children)];
    }

    /**
     * @return list<string>
     */
    public function readyIds(): array
    {
        return array_values(array_filter(array_keys($this->issues), fn (string $id): bool => $this->isReady($id)));
    }

    /**
     * Not-done, non-system issues that are blocked (by status or by
     * dependencies).
     *
     * @return list<string>
     */
    public function blockedIds(): array
    {
        return array_values(array_filter(
            array_keys($this->issues),
            fn (string $id): bool => ! $this->isSystem($this->issues[$id]) && $this->isBlocked($id),
        ));
    }

    public function isSystem(Issue $issue): bool
    {
        return in_array($issue->type, IssueTypeDefinition::SYSTEM_TYPES, true);
    }

    private function isOpenBlocker(string $id): bool
    {
        $issue = $this->issue($id);

        if ($issue === null) {
            // External references block until shipped; unknown local IDs do not.
            return str_starts_with($id, 'external:');
        }

        return ! $this->statuses->isDone($issue->status);
    }

    /**
     * @param  array<string, true>  $visiting
     */
    private function computeDependencyBlocked(string $id, array $visiting): bool
    {
        if (isset($visiting[$id]) || ! isset($this->issues[$id])) {
            return false;
        }

        if ($this->openBlockers($id) !== []) {
            return true;
        }

        $parent = $this->parent($id);

        if ($parent === null) {
            return false;
        }

        $visiting[$id] = true;

        return $this->dependencyBlocked[$parent] ??= $this->computeDependencyBlocked($parent, $visiting);
    }

    private function isDeferred(Issue $issue): bool
    {
        if ($issue->deferUntil === null) {
            return false;
        }

        try {
            return new DateTimeImmutable($issue->deferUntil) > $this->now;
        } catch (Exception) {
            return false;
        }
    }

    private function addBlocks(Dependency $dependency): void
    {
        $this->blockers[$dependency->issueId][] = $dependency->dependsOnId;
        $this->blocking[$dependency->dependsOnId][] = $dependency->issueId;
    }

    private function addRelated(Dependency $dependency): void
    {
        foreach ([[$dependency->issueId, $dependency->dependsOnId], [$dependency->dependsOnId, $dependency->issueId]] as [$a, $b]) {
            if (! in_array($b, $this->related[$a] ?? [], true)) {
                $this->related[$a][] = $b;
            }
        }
    }

    private function addOther(Dependency $dependency): void
    {
        $this->other[$dependency->issueId][] = $dependency;

        if ($dependency->dependsOnId !== $dependency->issueId) {
            $this->other[$dependency->dependsOnId][] = $dependency;
        }
    }
}
