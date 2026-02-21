<?php

namespace Database\Factories;

use App\Enums\MessageType;
use App\Models\Agent;
use App\Models\Message;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Message> */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'content' => fake()->paragraph(),
            'message_type' => MessageType::Chat,
            'mentions' => [],
            'read_by' => [],
        ];
    }

    public function fromAgent(Agent $agent): static
    {
        return $this->state(['from_agent_id' => $agent->id]);
    }

    public function statusUpdate(): static
    {
        return $this->state(['message_type' => MessageType::StatusUpdate]);
    }

    public function reviewRequest(): static
    {
        return $this->state(['message_type' => MessageType::ReviewRequest]);
    }

    public function standup(): static
    {
        return $this->state(['message_type' => MessageType::Standup]);
    }
}
