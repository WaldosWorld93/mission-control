<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListThreadsRequest;
use App\Http\Requests\Api\V1\UpdateThreadRequest;
use App\Models\MessageThread;
use Illuminate\Http\JsonResponse;

class ThreadController extends Controller
{
    public function index(ListThreadsRequest $request): JsonResponse
    {
        $agent = app('agent');
        $projectIds = $agent->projects()->pluck('projects.id');

        $query = MessageThread::whereIn('project_id', $projectIds)
            ->with(['startedByAgent:id,name'])
            ->orderByDesc('updated_at');

        if ($request->has('is_resolved')) {
            $query->where('is_resolved', $request->boolean('is_resolved'));
        }

        if ($request->filled('task_id')) {
            $query->where('task_id', $request->input('task_id'));
        }

        $threads = $query->get();

        return response()->json(['data' => $threads]);
    }

    public function update(UpdateThreadRequest $request, MessageThread $thread): JsonResponse
    {
        $thread->update([
            'is_resolved' => $request->boolean('is_resolved'),
        ]);

        return response()->json(['data' => $thread->fresh()]);
    }
}
