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

it('creates task with initial_message atomically', function (): void {
    $this->postJson('/api/v1/tasks', [
        'title' => 'Review the PR',
        'project_id' => $this->project->id,
        'initial_message' => 'Please review the latest changes to the auth module.',
    ], agentHeaders($this->token))
        ->assertStatus(201);

    $task = Task::latest()->first();
    $thread = MessageThread::where('task_id', $task->id)->first();

    expect($thread)->not->toBeNull();
    expect($thread->subject)->toBe('Review the PR');
    expect($thread->project_id)->toBe($this->project->id);
    expect($thread->started_by_agent_id)->toBe($this->agent->id);
    expect($thread->message_count)->toBe(1);

    $message = $thread->messages()->first();
    expect($message->content)->toBe('Please review the latest changes to the auth module.');
    expect($message->from_agent_id)->toBe($this->agent->id);
    expect($message->sequence_in_thread)->toBe(1);
    expect($message->message_type->value)->toBe('task_update');
});

it('creates task without initial_message and no thread', function (): void {
    $this->postJson('/api/v1/tasks', [
        'title' => 'Simple task',
        'project_id' => $this->project->id,
    ], agentHeaders($this->token))
        ->assertStatus(201);

    $task = Task::latest()->first();
    expect(MessageThread::where('task_id', $task->id)->exists())->toBeFalse();
});

it('parses mentions in initial_message', function (): void {
    $otherAgent = Agent::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'reviewer',
    ]);

    $this->postJson('/api/v1/tasks', [
        'title' => 'Need review',
        'project_id' => $this->project->id,
        'initial_message' => '@reviewer please review this task',
    ], agentHeaders($this->token))
        ->assertStatus(201);

    $task = Task::latest()->first();
    $message = MessageThread::where('task_id', $task->id)->first()->messages()->first();

    expect($message->mentions)->toContain($otherAgent->id);
});

it('links thread to task correctly', function (): void {
    $response = $this->postJson('/api/v1/tasks', [
        'title' => 'Linked task',
        'project_id' => $this->project->id,
        'initial_message' => 'Starting discussion.',
    ], agentHeaders($this->token));

    $taskId = $response->json('data.id');
    $task = Task::find($taskId);

    expect($task->thread)->not->toBeNull();
    expect($task->thread->task_id)->toBe($task->id);
});

it('validates initial_message max length', function (): void {
    $this->postJson('/api/v1/tasks', [
        'title' => 'Task',
        'project_id' => $this->project->id,
        'initial_message' => str_repeat('x', 51201),
    ], agentHeaders($this->token))
        ->assertStatus(422)
        ->assertJsonValidationErrors('initial_message');
});

it('sets initial_message read_by to sender', function (): void {
    $this->postJson('/api/v1/tasks', [
        'title' => 'New task',
        'project_id' => $this->project->id,
        'initial_message' => 'Starting this task.',
    ], agentHeaders($this->token))
        ->assertStatus(201);

    $task = Task::latest()->first();
    $message = MessageThread::where('task_id', $task->id)->first()->messages()->first();

    expect($message->read_by)->toContain($this->agent->id);
});

it('creates thread and message in same project as task', function (): void {
    $this->postJson('/api/v1/tasks', [
        'title' => 'Project task',
        'project_id' => $this->project->id,
        'initial_message' => 'Context for the team.',
    ], agentHeaders($this->token))
        ->assertStatus(201);

    $task = Task::latest()->first();
    $thread = MessageThread::where('task_id', $task->id)->first();
    $message = $thread->messages()->first();

    expect($thread->project_id)->toBe($this->project->id);
    expect($message->project_id)->toBe($this->project->id);
});

it('can append messages to task thread after creation', function (): void {
    $this->postJson('/api/v1/tasks', [
        'title' => 'Ongoing task',
        'project_id' => $this->project->id,
        'initial_message' => 'Starting discussion.',
    ], agentHeaders($this->token));

    $task = Task::latest()->first();
    $thread = MessageThread::where('task_id', $task->id)->first();

    $this->postJson('/api/v1/messages', [
        'content' => 'Follow up on the task.',
        'thread_id' => $thread->id,
    ], agentHeaders($this->token))
        ->assertStatus(201)
        ->assertJsonPath('data.sequence_in_thread', 2);

    expect($thread->fresh()->message_count)->toBe(2);
});

it('allows empty initial_message to be treated as null', function (): void {
    $this->postJson('/api/v1/tasks', [
        'title' => 'Task without message',
        'project_id' => $this->project->id,
        'initial_message' => null,
    ], agentHeaders($this->token))
        ->assertStatus(201);

    $task = Task::latest()->first();
    expect(MessageThread::where('task_id', $task->id)->exists())->toBeFalse();
});
