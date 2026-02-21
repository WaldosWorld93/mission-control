<?php

use App\Enums\ArtifactType;
use App\Models\Agent;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskArtifact;
use App\Models\User;

beforeEach(function () {
    $this->team = createTeam();
    $this->project = Project::factory()->create(['team_id' => $this->team->id]);
    $this->task = Task::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);
});

it('creates an artifact with factory defaults', function () {
    $artifact = TaskArtifact::factory()->create([
        'task_id' => $this->task->id,
        'team_id' => $this->team->id,
    ]);

    expect($artifact)
        ->artifact_type->toBe(ArtifactType::Document)
        ->version->toBe(1);
});

it('belongs to a task', function () {
    $artifact = TaskArtifact::factory()->create([
        'task_id' => $this->task->id,
        'team_id' => $this->team->id,
    ]);

    expect($artifact->task->id)->toBe($this->task->id);
});

it('tracks uploaded by agent', function () {
    $agent = Agent::factory()->create(['team_id' => $this->team->id]);
    $artifact = TaskArtifact::factory()->create([
        'task_id' => $this->task->id,
        'team_id' => $this->team->id,
        'uploaded_by_agent_id' => $agent->id,
    ]);

    expect($artifact->uploadedByAgent->id)->toBe($agent->id);
});

it('tracks uploaded by user', function () {
    $user = User::factory()->create();
    $artifact = TaskArtifact::factory()->create([
        'task_id' => $this->task->id,
        'team_id' => $this->team->id,
        'uploaded_by_user_id' => $user->id,
    ]);

    expect($artifact->uploadedByUser->id)->toBe($user->id);
});

it('supports different artifact types', function () {
    $code = TaskArtifact::factory()->code()->create([
        'task_id' => $this->task->id,
        'team_id' => $this->team->id,
    ]);
    $image = TaskArtifact::factory()->image()->create([
        'task_id' => $this->task->id,
        'team_id' => $this->team->id,
    ]);

    expect($code->artifact_type)->toBe(ArtifactType::Code)
        ->and($image->artifact_type)->toBe(ArtifactType::Image);
});

it('supports inline content for text artifacts', function () {
    $artifact = TaskArtifact::factory()->withContent('# Hello World')->create([
        'task_id' => $this->task->id,
        'team_id' => $this->team->id,
    ]);

    expect($artifact->content_text)->toBe('# Hello World')
        ->and($artifact->size_bytes)->toBe(strlen('# Hello World'));
});

it('supports versioning', function () {
    $v1 = TaskArtifact::factory()->create([
        'task_id' => $this->task->id,
        'team_id' => $this->team->id,
        'filename' => 'draft.md',
        'version' => 1,
    ]);
    $v2 = TaskArtifact::factory()->version(2)->create([
        'task_id' => $this->task->id,
        'team_id' => $this->team->id,
        'filename' => 'draft.md',
    ]);

    expect($v1->version)->toBe(1)
        ->and($v2->version)->toBe(2);
});

it('casts metadata to array', function () {
    $artifact = TaskArtifact::factory()->create([
        'task_id' => $this->task->id,
        'team_id' => $this->team->id,
        'metadata' => ['word_count' => 1500, 'language' => 'php'],
    ]);

    expect($artifact->metadata)->toBe(['word_count' => 1500, 'language' => 'php']);
});
