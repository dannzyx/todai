<?php

namespace App\Concerns;

use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait TaskValidationRules
{
    /**
     * Rule ensuring the given project id (if any) belongs to the current user.
     *
     * @return array<int, ValidationRule|string>
     */
    protected function ownedProjectRule(): array
    {
        return [
            'nullable',
            'string',
            Rule::exists(Project::class, 'id')->where('user_id', $this->user()->id),
        ];
    }

    /**
     * The Dutch validation messages shared across task requests.
     *
     * @return array<string, string>
     */
    protected function taskMessages(): array
    {
        return [
            'title.required' => 'Give the task a title.',
            'title.max' => 'The title may be at most 255 characters.',
            'due_date.date' => 'Choose a valid date.',
            'project_id.exists' => 'This project does not exist or is not yours.',
        ];
    }
}
