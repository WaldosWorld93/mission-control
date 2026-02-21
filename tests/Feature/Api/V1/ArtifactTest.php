<?php

use App\Models\Agent;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskArtifact;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->team = createTeam();
    [$this->agent, $this->token] = createAgentWithToken($this->team);
    $this->project = Project::factory()->create(['team_id' => $this->team->id]);
    $this->agent->projects()->attach($this->project);
    $this->task = Task::factory()->create([
        'team_id' => $this->team->id,
        'project_id' => $this->project->id,
    ]);
    Storage::fake(config('filesystems.artifact_disk'));
});

// --- STORE (Inline) ---

it('creates an artifact with inline content', function (): void {
    $this->postJson("/api/v1/tasks/{$this->task->id}/artifacts", [
        'filename' => 'readme.md',
        'mime_type' => 'text/markdown',
        'content' => '# Hello World',
    ], agentHeaders($this->token))
        ->assertStatus(201)
        ->assertJsonPath('data.filename', 'readme.md')
        ->assertJsonPath('data.version', 1)
        ->assertJsonPath('data.content_text', '# Hello World');

    $artifact = TaskArtifact::first();
    expect($artifact->confirmed_at)->not->toBeNull();
    expect($artifact->size_bytes)->toBe(strlen('# Hello World'));
    expect($artifact->uploaded_by_agent_id)->toBe($this->agent->id);
});

it('stores inline content to disk', function (): void {
    $this->postJson("/api/v1/tasks/{$this->task->id}/artifacts", [
        'filename' => 'test.txt',
        'mime_type' => 'text/plain',
        'content' => 'File content here',
    ], agentHeaders($this->token))
        ->assertStatus(201);

    $artifact = TaskArtifact::first();
    Storage::disk(config('filesystems.artifact_disk'))->assertExists($artifact->storage_path);
});

it('extracts metadata for code files', function (): void {
    $content = "<?php\necho 'hello';\necho 'world';";

    $this->postJson("/api/v1/tasks/{$this->task->id}/artifacts", [
        'filename' => 'app.php',
        'mime_type' => 'text/plain',
        'content' => $content,
        'artifact_type' => 'code',
    ], agentHeaders($this->token))
        ->assertStatus(201);

    $artifact = TaskArtifact::first();
    expect($artifact->metadata['language'])->toBe('php');
    expect($artifact->metadata['line_count'])->toBe(3);
});

it('defaults artifact_type to document', function (): void {
    $this->postJson("/api/v1/tasks/{$this->task->id}/artifacts", [
        'filename' => 'notes.md',
        'mime_type' => 'text/markdown',
        'content' => 'Some notes',
    ], agentHeaders($this->token))
        ->assertStatus(201)
        ->assertJsonPath('data.artifact_type', 'document');
});

// --- STORE (Presigned) ---

it('creates an unconfirmed artifact for presigned upload', function (): void {
    $response = $this->postJson("/api/v1/tasks/{$this->task->id}/artifacts", [
        'filename' => 'large-file.zip',
        'mime_type' => 'application/zip',
    ], agentHeaders($this->token))
        ->assertStatus(201);

    $artifact = TaskArtifact::first();
    expect($artifact->confirmed_at)->toBeNull();
    expect($response->json('upload_url'))->toContain("/api/v1/artifacts/{$artifact->id}/upload");
    expect($response->json('upload_method'))->toBe('POST');
});

it('does not include upload_url for inline uploads', function (): void {
    $response = $this->postJson("/api/v1/tasks/{$this->task->id}/artifacts", [
        'filename' => 'readme.md',
        'mime_type' => 'text/markdown',
        'content' => 'Inline content',
    ], agentHeaders($this->token))
        ->assertStatus(201);

    expect($response->json('upload_url'))->toBeNull();
});

// --- UPLOAD ---

it('uploads a file to an unconfirmed artifact', function (): void {
    $response = $this->postJson("/api/v1/tasks/{$this->task->id}/artifacts", [
        'filename' => 'report.txt',
        'mime_type' => 'text/plain',
    ], agentHeaders($this->token));

    $artifactId = $response->json('data.id');

    $file = UploadedFile::fake()->createWithContent('report.txt', 'Report content here');

    $this->postJson("/api/v1/artifacts/{$artifactId}/upload", [
        'file' => $file,
    ], agentHeaders($this->token))
        ->assertStatus(200);

    $artifact = TaskArtifact::find($artifactId);
    expect($artifact->confirmed_at)->not->toBeNull();
    expect($artifact->size_bytes)->toBeGreaterThan(0);
    expect($artifact->content_text)->toBe('Report content here');
});

it('rejects upload on already confirmed artifact', function (): void {
    $this->postJson("/api/v1/tasks/{$this->task->id}/artifacts", [
        'filename' => 'inline.md',
        'mime_type' => 'text/markdown',
        'content' => 'Already confirmed',
    ], agentHeaders($this->token));

    $artifact = TaskArtifact::first();

    $file = UploadedFile::fake()->create('inline.md', 100);

    $this->postJson("/api/v1/artifacts/{$artifact->id}/upload", [
        'file' => $file,
    ], agentHeaders($this->token))
        ->assertStatus(409);
});

// --- CONFIRM ---

it('confirms an artifact after file upload', function (): void {
    // Create unconfirmed artifact
    $response = $this->postJson("/api/v1/tasks/{$this->task->id}/artifacts", [
        'filename' => 'data.csv',
        'mime_type' => 'text/csv',
    ], agentHeaders($this->token));

    $artifact = TaskArtifact::first();

    // Manually put file on disk
    Storage::disk(config('filesystems.artifact_disk'))->put($artifact->storage_path, 'col1,col2\nval1,val2');

    $this->postJson("/api/v1/artifacts/{$artifact->id}/confirm", [], agentHeaders($this->token))
        ->assertStatus(200);

    expect($artifact->fresh()->confirmed_at)->not->toBeNull();
    expect($artifact->fresh()->size_bytes)->toBeGreaterThan(0);
});

it('rejects confirm when file not found on storage', function (): void {
    $response = $this->postJson("/api/v1/tasks/{$this->task->id}/artifacts", [
        'filename' => 'missing.zip',
        'mime_type' => 'application/zip',
    ], agentHeaders($this->token));

    $artifact = TaskArtifact::first();

    $this->postJson("/api/v1/artifacts/{$artifact->id}/confirm", [], agentHeaders($this->token))
        ->assertStatus(422)
        ->assertJsonPath('message', 'File not found on storage.');
});

it('rejects confirm on already confirmed artifact', function (): void {
    $this->postJson("/api/v1/tasks/{$this->task->id}/artifacts", [
        'filename' => 'confirmed.md',
        'mime_type' => 'text/markdown',
        'content' => 'Already done',
    ], agentHeaders($this->token));

    $artifact = TaskArtifact::first();

    $this->postJson("/api/v1/artifacts/{$artifact->id}/confirm", [], agentHeaders($this->token))
        ->assertStatus(409);
});

// --- VERSIONING ---

it('increments version for same filename on same task', function (): void {
    $this->postJson("/api/v1/tasks/{$this->task->id}/artifacts", [
        'filename' => 'report.md',
        'mime_type' => 'text/markdown',
        'content' => 'Version 1',
    ], agentHeaders($this->token))
        ->assertJsonPath('data.version', 1);

    $this->postJson("/api/v1/tasks/{$this->task->id}/artifacts", [
        'filename' => 'report.md',
        'mime_type' => 'text/markdown',
        'content' => 'Version 2',
    ], agentHeaders($this->token))
        ->assertJsonPath('data.version', 2);

    expect(TaskArtifact::count())->toBe(2);
});

it('does not increment version for different filename', function (): void {
    $this->postJson("/api/v1/tasks/{$this->task->id}/artifacts", [
        'filename' => 'file-a.md',
        'mime_type' => 'text/markdown',
        'content' => 'File A',
    ], agentHeaders($this->token))
        ->assertJsonPath('data.version', 1);

    $this->postJson("/api/v1/tasks/{$this->task->id}/artifacts", [
        'filename' => 'file-b.md',
        'mime_type' => 'text/markdown',
        'content' => 'File B',
    ], agentHeaders($this->token))
        ->assertJsonPath('data.version', 1);
});

it('preserves old versions when new version created', function (): void {
    for ($i = 1; $i <= 3; $i++) {
        $this->postJson("/api/v1/tasks/{$this->task->id}/artifacts", [
            'filename' => 'evolving.md',
            'mime_type' => 'text/markdown',
            'content' => "Content v{$i}",
        ], agentHeaders($this->token));
    }

    $artifacts = TaskArtifact::where('filename', 'evolving.md')->orderBy('version')->get();
    expect($artifacts)->toHaveCount(3);
    expect($artifacts[0]->version)->toBe(1);
    expect($artifacts[1]->version)->toBe(2);
    expect($artifacts[2]->version)->toBe(3);
});

// --- INDEX ---

it('lists only latest version per filename by default', function (): void {
    for ($i = 1; $i <= 3; $i++) {
        $this->postJson("/api/v1/tasks/{$this->task->id}/artifacts", [
            'filename' => 'versioned.md',
            'mime_type' => 'text/markdown',
            'content' => "Content v{$i}",
        ], agentHeaders($this->token));
    }

    $this->postJson("/api/v1/tasks/{$this->task->id}/artifacts", [
        'filename' => 'other.md',
        'mime_type' => 'text/markdown',
        'content' => 'Other file',
    ], agentHeaders($this->token));

    $this->getJson("/api/v1/tasks/{$this->task->id}/artifacts", agentHeaders($this->token))
        ->assertStatus(200)
        ->assertJsonCount(2, 'data');
});

it('lists all versions when include_versions is true', function (): void {
    for ($i = 1; $i <= 3; $i++) {
        $this->postJson("/api/v1/tasks/{$this->task->id}/artifacts", [
            'filename' => 'versioned.md',
            'mime_type' => 'text/markdown',
            'content' => "Content v{$i}",
        ], agentHeaders($this->token));
    }

    $this->getJson("/api/v1/tasks/{$this->task->id}/artifacts?include_versions=1", agentHeaders($this->token))
        ->assertStatus(200)
        ->assertJsonCount(3, 'data');
});

it('excludes unconfirmed artifacts from listing', function (): void {
    $this->postJson("/api/v1/tasks/{$this->task->id}/artifacts", [
        'filename' => 'confirmed.md',
        'mime_type' => 'text/markdown',
        'content' => 'Confirmed content',
    ], agentHeaders($this->token));

    // Create unconfirmed artifact
    $this->postJson("/api/v1/tasks/{$this->task->id}/artifacts", [
        'filename' => 'unconfirmed.zip',
        'mime_type' => 'application/zip',
    ], agentHeaders($this->token));

    $this->getJson("/api/v1/tasks/{$this->task->id}/artifacts", agentHeaders($this->token))
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

it('includes content_inline for small text files', function (): void {
    $this->postJson("/api/v1/tasks/{$this->task->id}/artifacts", [
        'filename' => 'small.txt',
        'mime_type' => 'text/plain',
        'content' => 'Small content',
    ], agentHeaders($this->token));

    $response = $this->getJson("/api/v1/tasks/{$this->task->id}/artifacts", agentHeaders($this->token))
        ->assertStatus(200);

    expect($response->json('data.0.content_inline'))->toBe('Small content');
});

// --- VALIDATION ---

it('requires filename on create', function (): void {
    $this->postJson("/api/v1/tasks/{$this->task->id}/artifacts", [
        'mime_type' => 'text/plain',
    ], agentHeaders($this->token))
        ->assertStatus(422)
        ->assertJsonValidationErrors('filename');
});

it('requires mime_type on create', function (): void {
    $this->postJson("/api/v1/tasks/{$this->task->id}/artifacts", [
        'filename' => 'test.txt',
    ], agentHeaders($this->token))
        ->assertStatus(422)
        ->assertJsonValidationErrors('mime_type');
});

it('rejects content exceeding max length', function (): void {
    $this->postJson("/api/v1/tasks/{$this->task->id}/artifacts", [
        'filename' => 'large.txt',
        'mime_type' => 'text/plain',
        'content' => str_repeat('x', 51201),
    ], agentHeaders($this->token))
        ->assertStatus(422)
        ->assertJsonValidationErrors('content');
});

// --- TEAM ISOLATION ---

it('enforces team isolation on artifact store', function (): void {
    $otherTeam = createTeam();
    $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
    $otherTask = Task::factory()->create([
        'team_id' => $otherTeam->id,
        'project_id' => $otherProject->id,
    ]);

    // Reset team context to original team
    app(\App\Services\TeamContext::class)->set($this->team);

    $this->postJson("/api/v1/tasks/{$otherTask->id}/artifacts", [
        'filename' => 'cross-team.md',
        'mime_type' => 'text/markdown',
        'content' => 'Should fail',
    ], agentHeaders($this->token))
        ->assertStatus(404);
});

it('enforces team isolation on artifact listing', function (): void {
    $otherTeam = createTeam();
    $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
    $otherTask = Task::factory()->create([
        'team_id' => $otherTeam->id,
        'project_id' => $otherProject->id,
    ]);

    // Reset team context to original team
    app(\App\Services\TeamContext::class)->set($this->team);

    $this->getJson("/api/v1/tasks/{$otherTask->id}/artifacts", agentHeaders($this->token))
        ->assertStatus(404);
});

it('enforces team isolation on artifact confirm', function (): void {
    $otherTeam = createTeam();
    $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
    $otherTask = Task::factory()->create([
        'team_id' => $otherTeam->id,
        'project_id' => $otherProject->id,
    ]);
    $otherArtifact = TaskArtifact::factory()->create([
        'team_id' => $otherTeam->id,
        'task_id' => $otherTask->id,
    ]);

    // Reset team context to original team
    app(\App\Services\TeamContext::class)->set($this->team);

    $this->postJson("/api/v1/artifacts/{$otherArtifact->id}/confirm", [], agentHeaders($this->token))
        ->assertStatus(404);
});

it('uses correct storage path format', function (): void {
    $this->postJson("/api/v1/tasks/{$this->task->id}/artifacts", [
        'filename' => 'test-path.md',
        'mime_type' => 'text/markdown',
        'content' => 'Path test',
    ], agentHeaders($this->token));

    $artifact = TaskArtifact::first();
    expect($artifact->storage_path)->toBe("artifacts/{$this->team->id}/{$this->task->id}/test-path.md_v1");
});

it('requires file on upload', function (): void {
    $response = $this->postJson("/api/v1/tasks/{$this->task->id}/artifacts", [
        'filename' => 'upload.txt',
        'mime_type' => 'text/plain',
    ], agentHeaders($this->token));

    $artifactId = $response->json('data.id');

    $this->postJson("/api/v1/artifacts/{$artifactId}/upload", [], agentHeaders($this->token))
        ->assertStatus(422)
        ->assertJsonValidationErrors('file');
});

it('reads text content on confirm for small text files', function (): void {
    $response = $this->postJson("/api/v1/tasks/{$this->task->id}/artifacts", [
        'filename' => 'readme.txt',
        'mime_type' => 'text/plain',
    ], agentHeaders($this->token));

    $artifact = TaskArtifact::first();

    Storage::disk(config('filesystems.artifact_disk'))->put($artifact->storage_path, 'Confirmed text content');

    $this->postJson("/api/v1/artifacts/{$artifact->id}/confirm", [], agentHeaders($this->token))
        ->assertStatus(200);

    expect($artifact->fresh()->content_text)->toBe('Confirmed text content');
});
