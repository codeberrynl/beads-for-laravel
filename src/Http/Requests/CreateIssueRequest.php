<?php

declare(strict_types=1);

namespace Codeberry\Beads\Http\Requests;

use Illuminate\Validation\Rule;

final class CreateIssueRequest extends BeadsRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:500'],
            'type' => ['nullable', 'string', Rule::in($this->typeNames())],
            'priority' => ['nullable', 'integer', 'between:0,4'],
            'status' => ['nullable', 'string', Rule::in($this->statusNames())],
            'description' => ['nullable', 'string', 'max:100000'],
            'design' => ['nullable', 'string', 'max:100000'],
            'acceptance_criteria' => ['nullable', 'string', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:100000'],
            'assignee' => ['nullable', 'string', 'max:200'],
            'labels' => ['nullable', 'array', 'max:50'],
            'labels.*' => ['string', 'regex:'.self::LABEL_PATTERN],
            'parent' => ['nullable', 'string', $this->issueIdRule(), $this->existingIssue()],
        ];
    }
}
