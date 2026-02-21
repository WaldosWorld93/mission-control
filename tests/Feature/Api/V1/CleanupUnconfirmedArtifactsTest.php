<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskArtifact;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->team = createTeam();
    $this->project = Project::factory()->create(['team_id' => $this->team->id]);
    $this->task = Task::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);
    Storage::fake(config('filesystems.artifact_disk'));
});

it('deletes unconfirmed artifacts older than 1 hour', function (): void {
    $artifact = TaskArtifact::factory()->create([
        'team_id' => $this->team->id,
        'task_id' => $this->task->id,
        'confirmed_at' => null,
        'created_at' => now()->subHours(2),
        'storage_path' => 'artifacts/old-file.txt',
    ]);

    Storage::disk(config('filesystems.artifact_disk'))->put('artifacts/old-file.txt', 'content');

    $this->artisan('artifacts:cleanup-unconfirmed')
        ->assertExitCode(0);

    expect(TaskArtifact::withoutGlobalScopes()->find($artifact->id))->toBeNull();
    Storage::disk(config('filesystems.artifact_disk'))->assertMissing('artifacts/old-file.txt');
});

it('preserves confirmed artifacts', function (): void {
    $artifact = TaskArtifact::factory()->create([
        'team_id' => $this->team->id,
        'task_id' => $this->task->id,
        'confirmed_at' => now()->subHours(2),
        'created_at' => now()->subHours(3),
        'storage_path' => 'artifacts/confirmed-file.txt',
    ]);

    Storage::disk(config('filesystems.artifact_disk'))->put('artifacts/confirmed-file.txt', 'content');

    $this->artisan('artifacts:cleanup-unconfirmed')
        ->assertExitCode(0);

    expect(TaskArtifact::withoutGlobalScopes()->find($artifact->id))->not->toBeNull();
    Storage::disk(config('filesystems.artifact_disk'))->assertExists('artifacts/confirmed-file.txt');
});

it('preserves recent unconfirmed artifacts', function (): void {
    $artifact = TaskArtifact::factory()->create([
        'team_id' => $this->team->id,
        'task_id' => $this->task->id,
        'confirmed_at' => null,
        'created_at' => now()->subMinutes(30),
    ]);

    $this->artisan('artifacts:cleanup-unconfirmed')
        ->assertExitCode(0);

    expect(TaskArtifact::withoutGlobalScopes()->find($artifact->id))->not->toBeNull();
});

it('handles artifacts without storage files gracefully', function (): void {
    $artifact = TaskArtifact::factory()->create([
        'team_id' => $this->team->id,
        'task_id' => $this->task->id,
        'confirmed_at' => null,
        'created_at' => now()->subHours(2),
        'storage_path' => 'artifacts/nonexistent.txt',
    ]);

    $this->artisan('artifacts:cleanup-unconfirmed')
        ->assertExitCode(0);

    expect(TaskArtifact::withoutGlobalScopes()->find($artifact->id))->toBeNull();
});

it('deletes multiple unconfirmed artifacts', function (): void {
    for ($i = 0; $i < 5; $i++) {
        TaskArtifact::factory()->create([
            'team_id' => $this->team->id,
            'task_id' => $this->task->id,
            'confirmed_at' => null,
            'created_at' => now()->subHours(2),
        ]);
    }

    $this->artisan('artifacts:cleanup-unconfirmed')
        ->assertExitCode(0);

    expect(TaskArtifact::withoutGlobalScopes()->whereNull('confirmed_at')->count())->toBe(0);
});

it('works across teams using withoutGlobalScopes', function (): void {
    $otherTeam = createTeam();
    $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
    $otherTask = Task::factory()->create([
        'team_id' => $otherTeam->id,
        'project_id' => $otherProject->id,
    ]);

    // Reset to first team
    app(\App\Services\TeamContext::class)->set($this->team);

    TaskArtifact::factory()->create([
        'team_id' => $this->team->id,
        'task_id' => $this->task->id,
        'confirmed_at' => null,
        'created_at' => now()->subHours(2),
    ]);

    // Switch to other team context to create artifact
    app(\App\Services\TeamContext::class)->set($otherTeam);
    TaskArtifact::factory()->create([
        'team_id' => $otherTeam->id,
        'task_id' => $otherTask->id,
        'confirmed_at' => null,
        'created_at' => now()->subHours(2),
    ]);

    $this->artisan('artifacts:cleanup-unconfirmed')
        ->assertExitCode(0);

    expect(TaskArtifact::withoutGlobalScopes()->whereNull('confirmed_at')->count())->toBe(0);
});

it('outputs count of deleted artifacts', function (): void {
    for ($i = 0; $i < 3; $i++) {
        TaskArtifact::factory()->create([
            'team_id' => $this->team->id,
            'task_id' => $this->task->id,
            'confirmed_at' => null,
            'created_at' => now()->subHours(2),
        ]);
    }

    $this->artisan('artifacts:cleanup-unconfirmed')
        ->expectsOutput('Deleted 3 unconfirmed artifact(s).')
        ->assertExitCode(0);
});

it('handles empty result set gracefully', function (): void {
    $this->artisan('artifacts:cleanup-unconfirmed')
        ->expectsOutput('Deleted 0 unconfirmed artifact(s).')
        ->assertExitCode(0);
});
