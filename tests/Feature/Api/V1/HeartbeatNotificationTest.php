<?php

use App\Models\Agent;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\Project;
use App\Models\Task;

beforeEach(function (): void {
    $this->team = createTeam();
    [$this->agent, $this->token] = createAgentWithToken($this->team);
    $this->project = Project::factory()->create(['team_id' => $this->team->id]);
    $this->agent->projects()->attach($this->project);
});

it('returns unread mentions in notifications', function (): void {
    $sender = Agent::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'sender-agent',
    ]);

    $thread = MessageThread::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
        'subject' => 'Review thread',
    ]);

    Message::factory()->create([
        'team_id' => $this->team->id,
        'thread_id' => $thread->id,
        'from_agent_id' => $sender->id,
        'sequence_in_thread' => 1,
        'content' => 'Hey @'.$this->agent->name.' check this',
        'mentions' => [$this->agent->id],
        'read_by' => [],
    ]);

    $response = $this->postJson('/api/v1/heartbeat', [
        'status' => 'online',
    ], agentHeaders($this->token))
        ->assertStatus(200);

    $notifications = $response->json('notifications');
    expect($notifications)->toHaveCount(1);
    expect($notifications[0]['type'])->toBe('mention');
    expect($notifications[0]['from'])->toBe('sender-agent');
    expect($notifications[0]['thread_id'])->toBe($thread->id);
    expect($notifications[0]['thread_subject'])->toBe('Review thread');
});

it('includes thread_context with first message and last 20', function (): void {
    $sender = Agent::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'sender',
    ]);

    $thread = MessageThread::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    // Create 25 messages (> 21 threshold)
    for ($i = 1; $i <= 25; $i++) {
        Message::factory()->create([
            'team_id' => $this->team->id,
            'thread_id' => $thread->id,
            'from_agent_id' => $sender->id,
            'sequence_in_thread' => $i,
            'content' => "Message {$i}",
            'mentions' => ($i === 25) ? [$this->agent->id] : [],
            'read_by' => [],
        ]);
    }

    $response = $this->postJson('/api/v1/heartbeat', [
        'status' => 'online',
    ], agentHeaders($this->token))
        ->assertStatus(200);

    $notifications = $response->json('notifications');
    expect($notifications)->toHaveCount(1);

    $context = $notifications[0]['thread_context'];
    // Should have first message + last 20 = 21
    expect($context)->toHaveCount(21);
    expect($context[0]['sequence'])->toBe(1);
    expect($context[1]['sequence'])->toBe(6);
    expect($context[20]['sequence'])->toBe(25);
});

it('includes all messages in thread_context for small threads', function (): void {
    $sender = Agent::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'sender',
    ]);

    $thread = MessageThread::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    for ($i = 1; $i <= 5; $i++) {
        Message::factory()->create([
            'team_id' => $this->team->id,
            'thread_id' => $thread->id,
            'from_agent_id' => $sender->id,
            'sequence_in_thread' => $i,
            'content' => "Message {$i}",
            'mentions' => ($i === 5) ? [$this->agent->id] : [],
            'read_by' => [],
        ]);
    }

    $response = $this->postJson('/api/v1/heartbeat', [
        'status' => 'online',
    ], agentHeaders($this->token));

    $context = $response->json('notifications.0.thread_context');
    expect($context)->toHaveCount(5);
});

it('marks mentions as read after delivery', function (): void {
    $sender = Agent::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'sender',
    ]);

    $thread = MessageThread::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    $message = Message::factory()->create([
        'team_id' => $this->team->id,
        'thread_id' => $thread->id,
        'from_agent_id' => $sender->id,
        'sequence_in_thread' => 1,
        'mentions' => [$this->agent->id],
        'read_by' => [],
    ]);

    // First heartbeat delivers the notification
    $this->postJson('/api/v1/heartbeat', ['status' => 'online'], agentHeaders($this->token))
        ->assertJsonCount(1, 'notifications');

    // Second heartbeat should have no notifications
    $this->postJson('/api/v1/heartbeat', ['status' => 'online'], agentHeaders($this->token))
        ->assertJsonCount(0, 'notifications');

    // Verify read_by was updated
    expect($message->fresh()->read_by)->toContain($this->agent->id);
});

it('includes linked_task_id in notification when thread has task', function (): void {
    $sender = Agent::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'sender',
    ]);

    $task = Task::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    $thread = MessageThread::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
        'task_id' => $task->id,
    ]);

    Message::factory()->create([
        'team_id' => $this->team->id,
        'thread_id' => $thread->id,
        'from_agent_id' => $sender->id,
        'sequence_in_thread' => 1,
        'mentions' => [$this->agent->id],
        'read_by' => [],
    ]);

    $response = $this->postJson('/api/v1/heartbeat', [
        'status' => 'online',
    ], agentHeaders($this->token));

    expect($response->json('notifications.0.linked_task_id'))->toBe($task->id);
});

it('returns empty notifications when no unread mentions', function (): void {
    $this->postJson('/api/v1/heartbeat', [
        'status' => 'online',
    ], agentHeaders($this->token))
        ->assertStatus(200)
        ->assertJsonCount(0, 'notifications');
});

it('does not include mentions already read by agent', function (): void {
    $sender = Agent::factory()->create([
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
        'from_agent_id' => $sender->id,
        'sequence_in_thread' => 1,
        'mentions' => [$this->agent->id],
        'read_by' => [$this->agent->id],
    ]);

    $this->postJson('/api/v1/heartbeat', [
        'status' => 'online',
    ], agentHeaders($this->token))
        ->assertJsonCount(0, 'notifications');
});

it('preserves existing heartbeat response fields with notifications', function (): void {
    $sender = Agent::factory()->create([
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
        'from_agent_id' => $sender->id,
        'sequence_in_thread' => 1,
        'mentions' => [$this->agent->id],
        'read_by' => [],
    ]);

    $response = $this->postJson('/api/v1/heartbeat', [
        'status' => 'online',
    ], agentHeaders($this->token))
        ->assertStatus(200);

    // Verify all standard heartbeat fields still present
    $response->assertJsonStructure([
        'status',
        'notifications',
        'tasks',
        'blocked_summary',
        'soul_sync',
        'config' => ['heartbeat_interval_seconds', 'active_projects'],
    ]);
});

it('handles multiple unread mentions across different threads', function (): void {
    $sender = Agent::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'sender',
    ]);

    $thread1 = MessageThread::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
        'subject' => 'Thread 1',
    ]);
    $thread2 = MessageThread::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
        'subject' => 'Thread 2',
    ]);

    Message::factory()->create([
        'team_id' => $this->team->id,
        'thread_id' => $thread1->id,
        'from_agent_id' => $sender->id,
        'sequence_in_thread' => 1,
        'mentions' => [$this->agent->id],
        'read_by' => [],
    ]);
    Message::factory()->create([
        'team_id' => $this->team->id,
        'thread_id' => $thread2->id,
        'from_agent_id' => $sender->id,
        'sequence_in_thread' => 1,
        'mentions' => [$this->agent->id],
        'read_by' => [],
    ]);

    $response = $this->postJson('/api/v1/heartbeat', [
        'status' => 'online',
    ], agentHeaders($this->token));

    expect($response->json('notifications'))->toHaveCount(2);
});

it('returns notification id matching the message id', function (): void {
    $sender = Agent::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'sender',
    ]);

    $thread = MessageThread::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    $message = Message::factory()->create([
        'team_id' => $this->team->id,
        'thread_id' => $thread->id,
        'from_agent_id' => $sender->id,
        'sequence_in_thread' => 1,
        'mentions' => [$this->agent->id],
        'read_by' => [],
    ]);

    $response = $this->postJson('/api/v1/heartbeat', [
        'status' => 'online',
    ], agentHeaders($this->token));

    expect($response->json('notifications.0.id'))->toBe($message->id);
});

it('does not return notifications for mentions of other agents', function (): void {
    $sender = Agent::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'sender',
    ]);
    $otherAgent = Agent::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'other',
    ]);

    $thread = MessageThread::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    Message::factory()->create([
        'team_id' => $this->team->id,
        'thread_id' => $thread->id,
        'from_agent_id' => $sender->id,
        'sequence_in_thread' => 1,
        'mentions' => [$otherAgent->id],
        'read_by' => [],
    ]);

    $this->postJson('/api/v1/heartbeat', [
        'status' => 'online',
    ], agentHeaders($this->token))
        ->assertJsonCount(0, 'notifications');
});

it('thread_context includes from agent name', function (): void {
    $sender = Agent::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'context-sender',
    ]);

    $thread = MessageThread::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);

    Message::factory()->create([
        'team_id' => $this->team->id,
        'thread_id' => $thread->id,
        'from_agent_id' => $sender->id,
        'sequence_in_thread' => 1,
        'content' => 'Initial context',
        'mentions' => [$this->agent->id],
        'read_by' => [],
    ]);

    $response = $this->postJson('/api/v1/heartbeat', [
        'status' => 'online',
    ], agentHeaders($this->token));

    $context = $response->json('notifications.0.thread_context');
    expect($context[0]['from'])->toBe('context-sender');
    expect($context[0]['content'])->toBe('Initial context');
});
