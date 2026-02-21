<?php

namespace Database\Factories;

use App\Enums\TeamPlan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Team> */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => str($name)->slug(),
            'owner_id' => User::factory(),
            'plan' => TeamPlan::Free,
            'default_heartbeat_model' => 'anthropic/claude-haiku-4-5',
            'default_work_model' => 'anthropic/claude-sonnet-4-5',
        ];
    }

    public function pro(): static
    {
        return $this->state(['plan' => TeamPlan::Pro]);
    }

    public function enterprise(): static
    {
        return $this->state(['plan' => TeamPlan::Enterprise]);
    }
}
