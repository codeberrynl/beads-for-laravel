<?php

declare(strict_types=1);

namespace Codeberry\Beads\Http\Requests;

use Codeberry\Beads\Data\IssueTypeDefinition;
use Codeberry\Beads\Data\StatusDefinition;
use Codeberry\Beads\Snapshot\Snapshot;
use Codeberry\Beads\Snapshot\SnapshotRepository;
use Codeberry\Beads\Support\IssueId;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared rules: types and statuses come from the bd definitions.
 */
abstract class BeadsRequest extends FormRequest
{
    public const LABEL_PATTERN = '/^[^,\s][^,]{0,99}$/u';

    private ?Snapshot $snapshot = null;

    public function authorize(): bool
    {
        return true;
    }

    protected function snapshot(): Snapshot
    {
        return $this->snapshot ??= $this->container->make(SnapshotRepository::class)->current();
    }

    /**
     * @return list<string>
     */
    protected function statusNames(): array
    {
        return array_map(fn (StatusDefinition $definition): string => $definition->name, $this->snapshot()->catalog()->ordered());
    }

    /**
     * Types that can be chosen in the UI (no system types).
     *
     * @return list<string>
     */
    protected function typeNames(): array
    {
        $types = array_filter($this->snapshot()->typeDefinitions(), fn (IssueTypeDefinition $type): bool => ! $type->isSystem());

        return array_values(array_map(fn (IssueTypeDefinition $type): string => $type->name, $types));
    }

    protected function issueIdRule(): string
    {
        return 'regex:/^'.IssueId::PATTERN.'$/';
    }

    /**
     * @return \Closure(string, mixed, \Closure): void
     */
    protected function existingIssue(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (is_string($value) && $value !== '' && $this->snapshot()->find($value) === null) {
                $fail("Issue {$value} does not exist.");
            }
        };
    }
}
