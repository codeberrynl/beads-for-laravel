<?php

declare(strict_types=1);

namespace Codeberry\Beads\Http\Requests;

use Closure;
use Codeberry\Beads\Actions\UpdateIssue;
use Illuminate\Validation\Rule;

final class UpdateIssueRequest extends BeadsRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $fields = [...array_keys(UpdateIssue::TEXT_FIELDS), ...array_keys(UpdateIssue::PICKER_FIELDS), 'labels'];

        return [
            'title' => ['sometimes', 'required', 'string', 'max:500'],
            'description' => ['sometimes', 'present', 'nullable', 'string', 'max:100000'],
            'design' => ['sometimes', 'present', 'nullable', 'string', 'max:100000'],
            'acceptance_criteria' => ['sometimes', 'present', 'nullable', 'string', 'max:100000'],
            'notes' => ['sometimes', 'present', 'nullable', 'string', 'max:100000'],
            'base' => ['sometimes', 'array'],
            'base.*' => ['nullable', 'string'],
            'status' => ['sometimes', 'required', 'string', Rule::in($this->statusNames())],
            'priority' => ['sometimes', 'required', 'integer', 'between:0,4'],
            'type' => ['sometimes', 'required', 'string', Rule::in($this->typeNames())],
            'assignee' => ['sometimes', 'present', 'nullable', 'string', 'max:200'],
            'parent' => ['sometimes', 'present', 'nullable', 'string', $this->issueIdRule(), $this->existingIssue(), $this->notSelf()],
            'labels' => ['sometimes', 'array:add,remove'],
            'labels.add' => ['sometimes', 'array', 'max:50'],
            'labels.add.*' => ['string', 'regex:'.self::LABEL_PATTERN],
            'labels.remove' => ['sometimes', 'array', 'max:50'],
            'labels.remove.*' => ['string'],
            '_fields' => [function (string $attribute, mixed $value, Closure $fail) use ($fields): void {
                if (array_intersect(array_keys($this->all()), $fields) === []) {
                    $fail('Send at least one field to update.');
                }
            }],
        ];
    }

    /**
     * Run the "_fields" rule even though it is never sent.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['_fields' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    public function fields(): array
    {
        $data = $this->validated();
        unset($data['_fields']);

        return $data;
    }

    private function notSelf(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === $this->route('id')) {
                $fail('An issue cannot be its own parent.');
            }
        };
    }
}
