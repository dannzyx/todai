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
            'title.required' => 'Geef de taak een titel.',
            'title.max' => 'De titel mag maximaal 255 tekens bevatten.',
            'due_date.date' => 'Kies een geldige datum.',
            'project_id.exists' => 'Dit project bestaat niet of is niet van jou.',
        ];
    }
}
