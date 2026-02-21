<?php

use App\Enums\MessageType;
use App\Models\Agent;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    $this->team = createTeam();
});

it('creates a message with factory defaults', function () {
    $message = Message::factory()->create(['team_id' => $this->team->id]);

    expect($message)
        ->content->not->toBeEmpty()
        ->message_type->toBe(MessageType::Chat);
});

it('belongs to a project', function () {
    $project = Project::factory()->create(['team_id' => $this->team->id]);
    $message = Message::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $project->id,
    ]);

    expect($message->project->id)->toBe($project->id);
});

it('can be from an agent', function () {
    $agent = Agent::factory()->create(['team_id' => $this->team->id]);
    $message = Message::factory()->fromAgent($agent)->create(['team_id' => $this->team->id]);

    expect($message->fromAgent->id)->toBe($agent->id);
});

it('can be from a user', function () {
    $user = User::factory()->create();
    $message = Message::factory()->create([
        'team_id' => $this->team->id,
        'from_user_id' => $user->id,
    ]);

    expect($message->fromUser->id)->toBe($user->id);
});

it('belongs to a thread', function () {
    $thread = MessageThread::factory()->create(['team_id' => $this->team->id]);
    $message = Message::factory()->create([
        'team_id' => $this->team->id,
        'thread_id' => $thread->id,
        'sequence_in_thread' => 1,
    ]);

    expect($message->thread->id)->toBe($thread->id);
});

it('casts mentions and read_by to arrays', function () {
    $agent = Agent::factory()->create(['team_id' => $this->team->id]);
    $message = Message::factory()->create([
        'team_id' => $this->team->id,
        'mentions' => [$agent->id],
        'read_by' => [$agent->id],
    ]);

    expect($message->mentions)->toBe([$agent->id])
        ->and($message->read_by)->toBe([$agent->id]);
});

it('supports different message types', function () {
    $standup = Message::factory()->standup()->create(['team_id' => $this->team->id]);
    $review = Message::factory()->reviewRequest()->create(['team_id' => $this->team->id]);

    expect($standup->message_type)->toBe(MessageType::Standup)
        ->and($review->message_type)->toBe(MessageType::ReviewRequest);
});
