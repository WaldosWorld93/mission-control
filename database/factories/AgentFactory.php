<?php

namespace Database\Factories;

use App\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Agent> */
class AgentFactory extends Factory
{
    protected $model = Agent::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->firstName(),
            'role' => fake()->jobTitle(),
            'description' => fake()->sentence(),
            'status' => AgentStatus::Offline,
            'is_lead' => false,
            'api_token' => hash('sha256', Str::random(64)),
            'skills' => [],
            'metadata' => [],
            'consecutive_errors' => 0,
            'is_paused' => false,
        ];
    }

    public function online(): static
    {
        return $this->state([
            'status' => AgentStatus::Online,
            'last_heartbeat_at' => now(),
        ]);
    }

    public function idle(): static
    {
        return $this->state(['status' => AgentStatus::Idle]);
    }

    public function busy(): static
    {
        return $this->state(['status' => AgentStatus::Busy]);
    }

    public function offline(): static
    {
        return $this->state(['status' => AgentStatus::Offline]);
    }

    public function lead(): static
    {
        return $this->state(['is_lead' => true]);
    }

    public function paused(string $reason = 'circuit_breaker'): static
    {
        return $this->state([
            'is_paused' => true,
            'paused_reason' => $reason,
            'paused_at' => now(),
        ]);
    }

    public function withSoul(string $content = '# Agent SOUL'): static
    {
        return $this->state([
            'soul_md' => $content,
            'soul_hash' => hash('sha256', $content),
        ]);
    }
}
