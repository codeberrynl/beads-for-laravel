<?php

declare(strict_types=1);

namespace Codeberry\Beads\Http\Requests;

use Closure;
use Codeberry\Beads\Actions\ManageRelation;
use Codeberry\Beads\Data\Dependency;
use Illuminate\Validation\Rule;

final class RelationRequest extends BeadsRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $adding = $this->isMethod('POST');
        $type = (string) $this->input('type');

        $directions = ManageRelation::DIRECTIONS[$type] ?? ManageRelation::OTHER_DIRECTIONS;

        return [
            'type' => ['required', 'string', $adding ? Rule::in(ManageRelation::EDITABLE) : 'regex:/^[a-z][a-z-]*$/'],
            'target' => array_filter([
                'required', 'string', $this->issueIdRule(),
                $adding ? $this->existingIssue() : null,
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === $this->route('id')) {
                        $fail('An issue cannot be related to itself.');
                    }
                },
            ]),
            'direction' => [$type === Dependency::RELATED ? 'nullable' : 'required', 'string', Rule::in($directions)],
        ];
    }

    public function direction(): string
    {
        return (string) ($this->validated()['direction'] ?? 'both');
    }
}
