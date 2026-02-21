<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Project> */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'team_id' => Team::factory(),
            'name' => $name,
            'slug' => str($name)->slug(),
            'description' => fake()->paragraph(),
            'status' => ProjectStatus::Active,
            'color' => fake()->hexColor(),
            'sort_order' => 0,
        ];
    }

    public function paused(): static
    {
        return $this->state(['status' => ProjectStatus::Paused]);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => ProjectStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(['status' => ProjectStatus::Archived]);
    }
}
