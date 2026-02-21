<?php

namespace Database\Factories;

use App\Enums\AttemptStatus;
use App\Models\Agent;
use App\Models\Task;
use App\Models\TaskAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TaskAttempt> */
class TaskAttemptFactory extends Factory
{
    protected $model = TaskAttempt::class;

    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'agent_id' => Agent::factory(),
            'attempt_number' => 1,
            'started_at' => now(),
            'status' => AttemptStatus::Active,
        ];
    }

    public function completed(): static
    {
        return $this->state([
            'status' => AttemptStatus::Completed,
            'ended_at' => now(),
            'result' => fake()->paragraph(),
        ]);
    }

    public function failed(string $error = 'Something went wrong'): static
    {
        return $this->state([
            'status' => AttemptStatus::Failed,
            'ended_at' => now(),
            'error_message' => $error,
        ]);
    }

    public function reassigned(): static
    {
        return $this->state([
            'status' => AttemptStatus::Reassigned,
            'ended_at' => now(),
        ]);
    }

    public function timedOut(): static
    {
        return $this->state([
            'status' => AttemptStatus::TimedOut,
            'ended_at' => now(),
        ]);
    }
}
