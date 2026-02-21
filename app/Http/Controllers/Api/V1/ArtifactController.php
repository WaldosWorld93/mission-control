<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ArtifactType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateArtifactRequest;
use App\Http\Requests\Api\V1\ListArtifactsRequest;
use App\Http\Requests\Api\V1\UploadArtifactRequest;
use App\Models\Task;
use App\Models\TaskArtifact;
use App\Services\ArtifactMetadataExtractor;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ArtifactController extends Controller
{
    public function __construct(
        private ArtifactMetadataExtractor $metadataExtractor,
    ) {}

    public function store(CreateArtifactRequest $request, Task $task): JsonResponse
    {
        $agent = app('agent');
        $validated = $request->validated();
        $disk = Storage::disk(config('filesystems.artifact_disk'));

        $artifact = DB::transaction(function () use ($agent, $validated, $task, $disk) {
            $version = TaskArtifact::where('task_id', $task->id)
                ->where('filename', $validated['filename'])
                ->lockForUpdate()
                ->max('version') + 1;

            $storagePath = "artifacts/{$task->team_id}/{$task->id}/{$validated['filename']}_v{$version}";

            $artifactData = [
                'task_id' => $task->id,
                'filename' => $validated['filename'],
                'display_name' => $validated['display_name'] ?? null,
                'mime_type' => $validated['mime_type'],
                'artifact_type' => ArtifactType::tryFrom($validated['artifact_type'] ?? '') ?? ArtifactType::Document,
                'version' => $version,
                'uploaded_by_agent_id' => $agent->id,
                'storage_path' => $storagePath,
            ];

            if (isset($validated['content'])) {
                // Inline upload path
                $disk->put($storagePath, $validated['content']);

                $metadata = $this->metadataExtractor->extract($validated['filename'], $validated['content']);

                $artifactData['content_text'] = $validated['content'];
                $artifactData['size_bytes'] = strlen($validated['content']);
                $artifactData['confirmed_at'] = now();
                $artifactData['metadata'] = $metadata;
            } else {
                // Presigned upload path — leave unconfirmed
                $artifactData['size_bytes'] = 0;
                $artifactData['metadata'] = $this->metadataExtractor->extract($validated['filename']);
            }

            return TaskArtifact::create($artifactData);
        });

        $response = ['data' => $artifact];

        // For presigned flow, return upload URL
        if (! $artifact->confirmed_at) {
            $diskName = config('filesystems.artifact_disk');

            if ($diskName === 's3') {
                $response['upload_url'] = $disk->temporaryUrl($artifact->storage_path, now()->addMinutes(30), [
                    'Content-Type' => $artifact->mime_type,
                ]);
                $response['upload_method'] = 'PUT';
            } else {
                $response['upload_url'] = url("/api/v1/artifacts/{$artifact->id}/upload");
                $response['upload_method'] = 'POST';
            }
        }

        return response()->json($response, 201);
    }

    public function index(ListArtifactsRequest $request, Task $task): JsonResponse
    {
        $includeVersions = $request->boolean('include_versions');

        if ($includeVersions) {
            $artifacts = $task->artifacts()
                ->whereNotNull('confirmed_at')
                ->orderBy('filename')
                ->orderByDesc('version')
                ->get();
        } else {
            // Latest version per filename
            $latestIds = $task->artifacts()
                ->whereNotNull('confirmed_at')
                ->select(DB::raw('MAX(id) as id'))
                ->groupBy('filename')
                ->pluck('id');

            $artifacts = TaskArtifact::whereIn('id', $latestIds)
                ->orderBy('filename')
                ->get();
        }

        $diskName = config('filesystems.artifact_disk');
        $disk = Storage::disk($diskName);

        $artifacts->each(function (TaskArtifact $artifact) use ($diskName, $disk) {
            // Include content inline for small text files
            if ($artifact->content_text !== null && $artifact->size_bytes <= 51200) {
                $artifact->setAttribute('content_inline', $artifact->content_text);
            }

            // Generate download URL for S3
            if ($diskName === 's3' && $disk->exists($artifact->storage_path)) {
                $artifact->setAttribute('download_url', $disk->temporaryUrl($artifact->storage_path, now()->addMinutes(30)));
            }
        });

        return response()->json(['data' => $artifacts]);
    }

    public function confirm(TaskArtifact $artifact): JsonResponse
    {
        if ($artifact->confirmed_at) {
            return response()->json(['message' => 'Artifact already confirmed.'], 409);
        }

        $disk = Storage::disk(config('filesystems.artifact_disk'));

        if (! $disk->exists($artifact->storage_path)) {
            return response()->json(['message' => 'File not found on storage.'], 422);
        }

        $size = $disk->size($artifact->storage_path);
        $metadata = $this->metadataExtractor->extract($artifact->filename);

        // Read content for text files
        $contentText = null;
        if (str_starts_with($artifact->mime_type, 'text/') && $size <= 51200) {
            $contentText = $disk->get($artifact->storage_path);
            $metadata = array_merge($metadata, $this->metadataExtractor->extract($artifact->filename, $contentText));
        }

        $artifact->update([
            'confirmed_at' => now(),
            'size_bytes' => $size,
            'content_text' => $contentText,
            'metadata' => array_merge($artifact->metadata ?? [], $metadata),
        ]);

        return response()->json(['data' => $artifact->fresh()]);
    }

    public function upload(UploadArtifactRequest $request, TaskArtifact $artifact): JsonResponse
    {
        if ($artifact->confirmed_at) {
            return response()->json(['message' => 'Artifact already confirmed.'], 409);
        }

        $file = $request->file('file');
        $disk = Storage::disk(config('filesystems.artifact_disk'));
        $disk->put($artifact->storage_path, file_get_contents($file->getRealPath()));

        $size = $disk->size($artifact->storage_path);
        $metadata = $this->metadataExtractor->extract($artifact->filename);

        // Read content for text files
        $contentText = null;
        if (str_starts_with($artifact->mime_type, 'text/') && $size <= 51200) {
            $contentText = $disk->get($artifact->storage_path);
            $metadata = array_merge($metadata, $this->metadataExtractor->extract($artifact->filename, $contentText));
        }

        $artifact->update([
            'confirmed_at' => now(),
            'size_bytes' => $size,
            'content_text' => $contentText,
            'metadata' => array_merge($artifact->metadata ?? [], $metadata),
        ]);

        return response()->json(['data' => $artifact->fresh()]);
    }
}
