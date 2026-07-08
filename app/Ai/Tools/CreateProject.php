<?php

namespace App\Ai\Tools;

use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateProject implements Tool
{
    /**
     * @param  Collection<int, Project>  $createdProjects  Collector for projects made this turn.
     */
    public function __construct(
        protected User $user,
        protected Collection $createdProjects,
    ) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Create one new project for the user. Only call this when the user '
            .'clearly asks to create or start a new project. Reuse an existing '
            .'project instead of creating a duplicate.';
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required(),
            'description' => $schema->string()->nullable(),
            'color' => $schema->string()->nullable(), // hex color like #6B7280
        ];
    }

    /**
     * Execute the tool: create the project for the current (injected) user.
     */
    public function handle(Request $request): Stringable|string
    {
        $data = $request->all();
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            return 'No project created: a name was missing.';
        }

        // Don't create duplicates — reuse an existing active project by name.
        $existing = $this->user->projects()
            ->active()
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing !== null) {
            return sprintf('Project "%s" already exists.', $existing->name);
        }

        $project = $this->user->projects()->create([
            'name' => $name,
            'description' => ($data['description'] ?? null) ?: null,
            'color' => $this->resolveColor($data['color'] ?? null),
        ]);

        $this->createdProjects->push($project);

        return sprintf('Created project: "%s".', $project->name);
    }

    /**
     * Validate an incoming hex color string, or return null.
     */
    protected function resolveColor(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $trimmed = trim($value);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $trimmed) === 1
            ? mb_strtoupper($trimmed)
            : null;
    }
}
