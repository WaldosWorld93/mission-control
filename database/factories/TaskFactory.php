<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Agent;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Task> */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'project_id' => Project::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'status' => TaskStatus::Backlog,
            'priority' => TaskPriority::Medium,
            'depth' => 0,
            'sort_order' => 0,
            'tags' => [],
        ];
    }

    public function blocked(): static
    {
        return $this->state(['status' => TaskStatus::Blocked]);
    }

    public function assigned(): static
    {
        return $this->state(['status' => TaskStatus::Assigned]);
    }

    public function inProgress(): static
    {
        return $this->state([
            'status' => TaskStatus::InProgress,
            'started_at' => now(),
        ]);
    }

    public function inReview(): static
    {
        return $this->state(['status' => TaskStatus::InReview]);
    }

    public function done(): static
    {
        return $this->state([
            'status' => TaskStatus::Done,
            'completed_at' => now(),
            'result' => fake()->paragraph(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => TaskStatus::Cancelled]);
    }

    public function critical(): static
    {
        return $this->state(['priority' => TaskPriority::Critical]);
    }

    public function high(): static
    {
        return $this->state(['priority' => TaskPriority::High]);
    }

    public function low(): static
    {
        return $this->state(['priority' => TaskPriority::Low]);
    }

    public function subtaskOf(Task $parent): static
    {
        return $this->state([
            'parent_task_id' => $parent->id,
            'project_id' => $parent->project_id,
            'team_id' => $parent->team_id,
            'depth' => $parent->depth + 1,
        ]);
    }

    public function assignedTo(Agent $agent): static
    {
        return $this->state([
            'assigned_agent_id' => $agent->id,
            'status' => TaskStatus::Assigned,
            'claimed_at' => now(),
        ]);
    }

    public function createdByAgent(Agent $agent): static
    {
        return $this->state(['created_by_agent_id' => $agent->id]);
    }
}
