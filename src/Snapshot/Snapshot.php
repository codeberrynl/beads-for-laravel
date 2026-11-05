<?php

declare(strict_types=1);

namespace Codeberry\Beads\Snapshot;

use Codeberry\Beads\Cli\BdResult;
use Codeberry\Beads\Data\Issue;
use Codeberry\Beads\Data\IssueTypeDefinition;
use Codeberry\Beads\Data\StatusCatalog;
use Codeberry\Beads\Data\StatusDefinition;
use Codeberry\Beads\Graph\IssueGraph;
use DateTimeInterface;

/**
 * One `bd export` plus the status and type definitions, with a content
 * hash that serves as the ETag.
 */
final class Snapshot
{
    /** @var array<string, Issue>|null */
    private ?array $issues = null;

    private ?IssueGraph $graph = null;

    /**
     * @param  mixed  $statuses  raw `bd statuses --json` payload
     * @param  mixed  $types  raw `bd types --json` payload
     */
    public function __construct(
        public readonly string $export,
        public readonly mixed $statuses,
        public readonly mixed $types,
        public readonly string $hash,
        public readonly string $exportedAt,
    ) {}

    public static function make(string $export, mixed $statuses, mixed $types, ?string $exportedAt = null): self
    {
        $hash = sha1($export."\n".json_encode($statuses)."\n".json_encode($types));

        return new self($export, $statuses, $types, $hash, $exportedAt ?? gmdate('Y-m-d\TH:i:s\Z'));
    }

    /**
     * @return array<string, Issue>
     */
    public function issues(): array
    {
        if ($this->issues === null) {
            $this->issues = [];

            foreach ((new BdResult([], 0, $this->export, ''))->jsonLines() as $row) {
                if (($row['_type'] ?? 'issue') !== 'issue') {
                    continue;
                }

                $issue = Issue::fromArray($row);

                if ($issue->id !== '') {
                    $this->issues[$issue->id] = $issue;
                }
            }
        }

        return $this->issues;
    }

    public function find(string $id): ?Issue
    {
        return $this->issues()[$id] ?? null;
    }

    /**
     * @return list<StatusDefinition>
     */
    public function statusDefinitions(): array
    {
        return StatusDefinition::listFrom($this->statuses);
    }

    /**
     * @return list<IssueTypeDefinition>
     */
    public function typeDefinitions(): array
    {
        return IssueTypeDefinition::listFrom($this->types);
    }

    public function catalog(): StatusCatalog
    {
        return new StatusCatalog($this->statusDefinitions());
    }

    public function graph(?DateTimeInterface $now = null): IssueGraph
    {
        if ($now !== null) {
            return new IssueGraph($this->issues(), $this->catalog(), $now);
        }

        return $this->graph ??= new IssueGraph($this->issues(), $this->catalog());
    }

    /**
     * Whether an issue uses a status the definitions do not know.
     */
    public function hasUnknownStatuses(): bool
    {
        $known = array_map(fn (StatusDefinition $definition): string => $definition->name, $this->statusDefinitions());

        foreach ($this->issues() as $issue) {
            if (! in_array($issue->status, $known, true) && ! isset(StatusCatalog::BUILT_IN[$issue->status])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{export: string, statuses: mixed, types: mixed, hash: string, exported_at: string}
     */
    public function toArray(): array
    {
        return [
            'export' => $this->export,
            'statuses' => $this->statuses,
            'types' => $this->types,
            'hash' => $this->hash,
            'exported_at' => $this->exportedAt,
        ];
    }

    /**
     * @param  array<mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        if (! is_string($data['export'] ?? null) || ! is_string($data['hash'] ?? null)) {
            return null;
        }

        return new self(
            $data['export'],
            $data['statuses'] ?? null,
            $data['types'] ?? null,
            $data['hash'],
            is_string($data['exported_at'] ?? null) ? $data['exported_at'] : '',
        );
    }
}
