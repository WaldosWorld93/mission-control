<?php

namespace Database\Factories;

use App\Enums\ArtifactType;
use App\Models\Task;
use App\Models\TaskArtifact;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TaskArtifact> */
class TaskArtifactFactory extends Factory
{
    protected $model = TaskArtifact::class;

    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'team_id' => Team::factory(),
            'filename' => fake()->word().'.md',
            'display_name' => fake()->words(3, true),
            'mime_type' => 'text/markdown',
            'size_bytes' => fake()->numberBetween(100, 50000),
            'storage_path' => 'artifacts/'.fake()->uuid().'.md',
            'artifact_type' => ArtifactType::Document,
            'version' => 1,
            'metadata' => [],
        ];
    }

    public function document(): static
    {
        return $this->state(['artifact_type' => ArtifactType::Document]);
    }

    public function code(): static
    {
        return $this->state([
            'artifact_type' => ArtifactType::Code,
            'mime_type' => 'text/plain',
            'filename' => fake()->word().'.php',
        ]);
    }

    public function image(): static
    {
        return $this->state([
            'artifact_type' => ArtifactType::Image,
            'mime_type' => 'image/png',
            'filename' => fake()->word().'.png',
        ]);
    }

    public function withContent(string $content = 'Sample content'): static
    {
        return $this->state([
            'content_text' => $content,
            'size_bytes' => strlen($content),
        ]);
    }

    public function version(int $version): static
    {
        return $this->state(['version' => $version]);
    }
}
