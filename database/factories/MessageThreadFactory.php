<?php

namespace Database\Factories;

use App\Models\MessageThread;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MessageThread> */
class MessageThreadFactory extends Factory
{
    protected $model = MessageThread::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'subject' => fake()->sentence(4),
            'is_resolved' => false,
            'message_count' => 0,
        ];
    }

    public function resolved(): static
    {
        return $this->state(['is_resolved' => true]);
    }
}
