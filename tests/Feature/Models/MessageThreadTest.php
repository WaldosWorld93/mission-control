<?php

use App\Models\Agent;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

beforeEach(function () {
    $this->team = createTeam();
});

it('creates a thread with factory defaults', function () {
    $thread = MessageThread::factory()->create(['team_id' => $this->team->id]);

    expect($thread)
        ->is_resolved->toBeFalse()
        ->message_count->toBe(0);
});

it('belongs to a project', function () {
    $project = Project::factory()->create(['team_id' => $this->team->id]);
    $thread = MessageThread::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $project->id,
    ]);

    expect($thread->project->id)->toBe($project->id);
});

it('can be linked to a task', function () {
    $project = Project::factory()->create(['team_id' => $this->team->id]);
    $task = Task::factory()->create(['team_id' => $this->team->id, 'project_id' => $project->id]);
    $thread = MessageThread::factory()->create([
        'team_id' => $this->team->id,
        'task_id' => $task->id,
    ]);

    expect($thread->task->id)->toBe($task->id);
});

it('tracks who started it (agent)', function () {
    $agent = Agent::factory()->create(['team_id' => $this->team->id]);
    $thread = MessageThread::factory()->create([
        'team_id' => $this->team->id,
        'started_by_agent_id' => $agent->id,
    ]);

    expect($thread->startedByAgent->id)->toBe($agent->id);
});

it('tracks who started it (user)', function () {
    $user = User::factory()->create();
    $thread = MessageThread::factory()->create([
        'team_id' => $this->team->id,
        'started_by_user_id' => $user->id,
    ]);

    expect($thread->startedByUser->id)->toBe($user->id);
});

it('has many messages ordered by sequence', function () {
    $thread = MessageThread::factory()->create(['team_id' => $this->team->id]);
    Message::factory()->create([
        'team_id' => $this->team->id,
        'thread_id' => $thread->id,
        'sequence_in_thread' => 2,
        'content' => 'Second',
    ]);
    Message::factory()->create([
        'team_id' => $this->team->id,
        'thread_id' => $thread->id,
        'sequence_in_thread' => 1,
        'content' => 'First',
    ]);

    $messages = $thread->messages;
    expect($messages)->toHaveCount(2)
        ->and($messages->first()->content)->toBe('First')
        ->and($messages->last()->content)->toBe('Second');
});

it('can be resolved', function () {
    $thread = MessageThread::factory()->resolved()->create(['team_id' => $this->team->id]);

    expect($thread->is_resolved)->toBeTrue();
});
