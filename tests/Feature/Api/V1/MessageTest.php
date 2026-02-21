<?php

use App\Enums\MessageType;
use App\Models\Agent;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\Project;

beforeEach(function (): void {
    $this->team = createTeam();
    [$this->agent, $this->token] = createAgentWithToken($this->team);
    $this->project = Project::factory()->create(['team_id' => $this->team->id]);
    $this->agent->projects()->attach($this->project);
});

// --- STORE ---

it('creates a message and auto-creates a thread', function (): void {
    $this->postJson('/api/v1/messages', [
        'content' => 'Hello team!',
        'project_id' => $this->project->id,
        'subject' => 'General discussion',
    ], agentHeaders($this->token))
        ->assertStatus(201)
        ->assertJsonPath('data.content', 'Hello team!')
        ->assertJsonPath('data.sequence_in_thread', 1);

    expect(MessageThread::count())->toBe(1);
    expect(MessageThread::first()->message_count)->toBe(1);
    expect(MessageThread::first()->subject)->toBe('General discussion');
    expect(MessageThread::first()->started_by_agent_id)->toBe($this->agent->id);
});

it('appends a message to an existing thread', function (): void {
    $thread = MessageThread::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
        'started_by_agent_id' => $this->agent->id,
        'message_count' => 1,
    ]);

    Message::factory()->create([
        'team_id' => $this->team->id,
        'thread_id' => $thread->id,
        'from_agent_id' => $this->agent->id,
        'sequence_in_thread' => 1,
    ]);

    $this->postJson('/api/v1/messages', [
        'content' => 'Follow up message',
        'thread_id' => $thread->id,
    ], agentHeaders($this->token))
        ->assertStatus(201)
        ->assertJsonPath('data.sequence_in_thread', 2);

    expect($thread->fresh()->message_count)->toBe(2);
});

it('parses @mentions and stores agent IDs', function (): void {
    $otherAgent = Agent::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'writer',
    ]);

    $this->postJson('/api/v1/messages', [
        'content' => 'Hey @writer, can you review this?',
        'project_id' => $this->project->id,
    ], agentHeaders($this->token))
        ->assertStatus(201);

    $message = Message::latest()->first();
    expect($message->mentions)->toContain($otherAgent->id);
});

it('sets read_by to include the sender', function (): void {
    $this->postJson('/api/v1/messages', [
        'content' => 'New message',
        'project_id' => $this->project->id,
    ], agentHeaders($this->token))
        ->assertStatus(201);

    $message = Message::latest()->first();
    expect($message->read_by)->toContain($this->agent->id);
});

it('increments sequence atomically within a thread', function (): void {
    $thread = MessageThread::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
        'started_by_agent_id' => $this->agent->id,
        'message_count' => 0,
    ]);

    // Create 3 messages sequentially
    for ($i = 1; $i <= 3; $i++) {
        $this->postJson('/api/v1/messages', [
            'content' => "Message {$i}",
            'thread_id' => $thread->id,
        ], agentHeaders($this->token))
            ->assertStatus(201)
            ->assertJsonPath('data.sequence_in_thread', $i);
    }

    expect($thread->fresh()->message_count)->toBe(3);
});

it('creates a message with specific message_type', function (): void {
    $this->postJson('/api/v1/messages', [
        'content' => 'Daily standup update',
        'project_id' => $this->project->id,
        'message_type' => 'standup',
    ], agentHeaders($this->token))
        ->assertStatus(201)
        ->assertJsonPath('data.message_type', 'standup');
});

it('defaults message_type to chat', function (): void {
    $this->postJson('/api/v1/messages', [
        'content' => 'Regular message',
        'project_id' => $this->project->id,
    ], agentHeaders($this->token))
        ->assertStatus(201)
        ->assertJsonPath('data.message_type', 'chat');
});

it('requires content to create a message', function (): void {
    $this->postJson('/api/v1/messages', [
        'project_id' => $this->project->id,
    ], agentHeaders($this->token))
        ->assertStatus(422)
        ->assertJsonValidationErrors('content');
});

it('rejects content exceeding max length', function (): void {
    $this->postJson('/api/v1/messages', [
        'content' => str_repeat('x', 51201),
        'project_id' => $this->project->id,
    ], agentHeaders($this->token))
        ->assertStatus(422)
        ->assertJsonValidationErrors('content');
});

it('rejects invalid thread_id', function (): void {
    $this->postJson('/api/v1/messages', [
        'content' => 'Test',
        'thread_id' => 'not-a-uuid',
    ], agentHeaders($this->token))
        ->assertStatus(422)
        ->assertJsonValidationErrors('thread_id');
});

it('rejects nonexistent thread_id', function (): void {
    $this->postJson('/api/v1/messages', [
        'content' => 'Test',
        'thread_id' => fake()->uuid(),
    ], agentHeaders($this->token))
        ->assertStatus(422)
        ->assertJsonValidationErrors('thread_id');
});

it('sets project_id from thread when appending', function (): void {
    $thread = MessageThread::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
        'started_by_agent_id' => $this->agent->id,
        'message_count' => 0,
    ]);

    $this->postJson('/api/v1/messages', [
        'content' => 'Appending to thread',
        'thread_id' => $thread->id,
    ], agentHeaders($this->token))
        ->assertStatus(201)
        ->assertJsonPath('data.project_id', $this->project->id);
});

it('handles mentions of nonexistent agent names gracefully', function (): void {
    $this->postJson('/api/v1/messages', [
        'content' => 'Hey @nonexistent-agent, check this',
        'project_id' => $this->project->id,
    ], agentHeaders($this->token))
        ->assertStatus(201);

    $message = Message::latest()->first();
    expect($message->mentions)->toBe([]);
});

it('handles multiple mentions in one message', function (): void {
    $agent1 = Agent::factory()->create(['team_id' => $this->team->id, 'name' => 'reviewer']);
    $agent2 = Agent::factory()->create(['team_id' => $this->team->id, 'name' => 'tester']);

    $this->postJson('/api/v1/messages', [
        'content' => '@reviewer and @tester please look at this',
        'project_id' => $this->project->id,
    ], agentHeaders($this->token))
        ->assertStatus(201);

    $message = Message::latest()->first();
    expect($message->mentions)->toContain($agent1->id)
        ->toContain($agent2->id);
});

// --- INDEX ---

it('lists messages ordered by created_at descending', function (): void {
    $thread = MessageThread::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    Message::factory()->count(3)->create([
        'team_id' => $this->team->id,
        'thread_id' => $thread->id,
        'from_agent_id' => $this->agent->id,
    ]);

    $this->getJson('/api/v1/messages', agentHeaders($this->token))
        ->assertStatus(200)
        ->assertJsonCount(3, 'data');
});

it('filters messages by thread_id', function (): void {
    $thread1 = MessageThread::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);
    $thread2 = MessageThread::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    Message::factory()->count(2)->create([
        'team_id' => $this->team->id,
        'thread_id' => $thread1->id,
        'from_agent_id' => $this->agent->id,
    ]);
    Message::factory()->create([
        'team_id' => $this->team->id,
        'thread_id' => $thread2->id,
        'from_agent_id' => $this->agent->id,
    ]);

    $this->getJson("/api/v1/messages?thread_id={$thread1->id}", agentHeaders($this->token))
        ->assertStatus(200)
        ->assertJsonCount(2, 'data');
});

it('filters messages by project_id', function (): void {
    $otherProject = Project::factory()->create(['team_id' => $this->team->id]);
    $thread1 = MessageThread::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);
    $thread2 = MessageThread::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $otherProject->id,
    ]);

    Message::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
        'thread_id' => $thread1->id,
        'from_agent_id' => $this->agent->id,
    ]);
    Message::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $otherProject->id,
        'thread_id' => $thread2->id,
        'from_agent_id' => $this->agent->id,
    ]);

    $this->getJson("/api/v1/messages?project_id={$this->project->id}", agentHeaders($this->token))
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

it('filters messages by message_type', function (): void {
    $thread = MessageThread::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    Message::factory()->create([
        'team_id' => $this->team->id,
        'thread_id' => $thread->id,
        'from_agent_id' => $this->agent->id,
        'message_type' => MessageType::Chat,
    ]);
    Message::factory()->standup()->create([
        'team_id' => $this->team->id,
        'thread_id' => $thread->id,
        'from_agent_id' => $this->agent->id,
    ]);

    $this->getJson('/api/v1/messages?message_type=standup', agentHeaders($this->token))
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

it('filters messages mentioning me', function (): void {
    $thread = MessageThread::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    Message::factory()->create([
        'team_id' => $this->team->id,
        'thread_id' => $thread->id,
        'from_agent_id' => $this->agent->id,
        'mentions' => [$this->agent->id],
        'sequence_in_thread' => 1,
    ]);
    Message::factory()->create([
        'team_id' => $this->team->id,
        'thread_id' => $thread->id,
        'from_agent_id' => $this->agent->id,
        'mentions' => [],
        'sequence_in_thread' => 2,
    ]);

    $this->getJson('/api/v1/messages?mentioning=me', agentHeaders($this->token))
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

it('includes thread_context for mentioning=me filter', function (): void {
    $otherAgent = Agent::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'sender',
    ]);

    $thread = MessageThread::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    Message::factory()->create([
        'team_id' => $this->team->id,
        'thread_id' => $thread->id,
        'from_agent_id' => $otherAgent->id,
        'sequence_in_thread' => 1,
        'content' => 'First message',
        'mentions' => [],
    ]);

    Message::factory()->create([
        'team_id' => $this->team->id,
        'thread_id' => $thread->id,
        'from_agent_id' => $otherAgent->id,
        'sequence_in_thread' => 2,
        'content' => 'Hey @'.$this->agent->name,
        'mentions' => [$this->agent->id],
    ]);

    $response = $this->getJson('/api/v1/messages?mentioning=me', agentHeaders($this->token))
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');

    $data = $response->json('data.0');
    expect($data['thread_context'])->toHaveCount(2);
    expect($data['thread_context'][0]['content'])->toBe('First message');
});

it('enforces team isolation on messages', function (): void {
    $otherTeam = createTeam();
    [$otherAgent] = createAgentWithToken($otherTeam);
    $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
    $otherThread = MessageThread::factory()->create([
        'team_id' => $otherTeam->id,
        'project_id' => $otherProject->id,
    ]);

    Message::factory()->count(2)->create([
        'team_id' => $otherTeam->id,
        'thread_id' => $otherThread->id,
        'from_agent_id' => $otherAgent->id,
        'project_id' => $otherProject->id,
    ]);

    // Reset team context to original team
    app(\App\Services\TeamContext::class)->set($this->team);

    $this->getJson('/api/v1/messages', agentHeaders($this->token))
        ->assertStatus(200)
        ->assertJsonCount(0, 'data');
});

it('returns thread in message response', function (): void {
    $this->postJson('/api/v1/messages', [
        'content' => 'Hello!',
        'project_id' => $this->project->id,
        'subject' => 'Test thread',
    ], agentHeaders($this->token))
        ->assertStatus(201)
        ->assertJsonPath('data.thread.subject', 'Test thread');
});

it('handles duplicate mentions of the same agent', function (): void {
    $otherAgent = Agent::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'reviewer',
    ]);

    $this->postJson('/api/v1/messages', [
        'content' => '@reviewer first thing, @reviewer second thing',
        'project_id' => $this->project->id,
    ], agentHeaders($this->token))
        ->assertStatus(201);

    $message = Message::latest()->first();
    expect($message->mentions)->toHaveCount(1)
        ->toContain($otherAgent->id);
});

it('creates message without project_id or thread_id', function (): void {
    $this->postJson('/api/v1/messages', [
        'content' => 'General broadcast',
    ], agentHeaders($this->token))
        ->assertStatus(201)
        ->assertJsonPath('data.project_id', null);
});

it('orders messages by sequence when filtering by thread', function (): void {
    $thread = MessageThread::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
        'message_count' => 3,
    ]);

    Message::factory()->create([
        'team_id' => $this->team->id,
        'thread_id' => $thread->id,
        'from_agent_id' => $this->agent->id,
        'sequence_in_thread' => 3,
        'created_at' => now()->subMinutes(1),
    ]);
    Message::factory()->create([
        'team_id' => $this->team->id,
        'thread_id' => $thread->id,
        'from_agent_id' => $this->agent->id,
        'sequence_in_thread' => 1,
        'created_at' => now()->subMinutes(3),
    ]);
    Message::factory()->create([
        'team_id' => $this->team->id,
        'thread_id' => $thread->id,
        'from_agent_id' => $this->agent->id,
        'sequence_in_thread' => 2,
        'created_at' => now()->subMinutes(2),
    ]);

    $response = $this->getJson("/api/v1/messages?thread_id={$thread->id}", agentHeaders($this->token))
        ->assertStatus(200);

    $sequences = collect($response->json('data'))->pluck('sequence_in_thread')->all();
    expect($sequences)->toBe([1, 2, 3]);
});
