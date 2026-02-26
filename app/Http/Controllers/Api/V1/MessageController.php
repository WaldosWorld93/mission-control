<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MessageType;
use App\Events\MessageCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateMessageRequest;
use App\Http\Requests\Api\V1\ListMessagesRequest;
use App\Models\Message;
use App\Models\MessageThread;
use App\Services\MentionParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MessageController extends Controller
{
    public function __construct(
        private MentionParser $mentionParser,
    ) {}

    public function store(CreateMessageRequest $request): JsonResponse
    {
        $agent = $request->attributes->get('agent');
        $validated = $request->validated();

        $message = DB::transaction(function () use ($agent, $validated) {
            $thread = null;

            if (! empty($validated['thread_id'])) {
                $thread = MessageThread::where('id', $validated['thread_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                $sequence = $thread->messages()->max('sequence_in_thread') + 1;
            } else {
                $thread = MessageThread::create([
                    'project_id' => $validated['project_id'] ?? null,
                    'subject' => $validated['subject'] ?? null,
                    'started_by_agent_id' => $agent->id,
                    'message_count' => 0,
                ]);

                $sequence = 1;
            }

            $mentions = $this->mentionParser->parse($validated['content']);

            $message = Message::create([
                'project_id' => $thread->project_id,
                'from_agent_id' => $agent->id,
                'thread_id' => $thread->id,
                'sequence_in_thread' => $sequence,
                'content' => $validated['content'],
                'mentions' => $mentions['agent_ids'],
                'read_by' => [$agent->id],
                'message_type' => MessageType::tryFrom($validated['message_type'] ?? '') ?? MessageType::Chat,
            ]);

            $thread->increment('message_count');

            return $message;
        });

        $message->load('thread');

        MessageCreated::dispatch(
            $agent->team_id,
            $message->id,
            $agent->name,
            Str::limit($message->content, 80),
            $message->thread?->subject,
            $message->thread_id,
            $message->project_id,
        );

        return response()->json(['data' => $message], 201);
    }

    public function index(ListMessagesRequest $request): JsonResponse
    {
        $agent = $request->attributes->get('agent');
        $query = Message::query()->with(['fromAgent:id,name', 'thread:id,subject,task_id']);

        if ($request->filled('thread_id')) {
            $query->where('thread_id', $request->input('thread_id'))
                ->orderBy('sequence_in_thread');
        } else {
            $query->orderByDesc('created_at');
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->input('project_id'));
        }

        if ($request->filled('message_type')) {
            $query->where('message_type', $request->input('message_type'));
        }

        if ($request->input('mentioning') === 'me') {
            $query->whereJsonContains('mentions', $agent->id);
        }

        $messages = $query->limit(100)->get();

        // Add thread_context for unread mentions
        if ($request->input('mentioning') === 'me') {
            $threadIds = $messages->pluck('thread_id')->unique()->filter();
            $threadMessages = Message::whereIn('thread_id', $threadIds)
                ->orderBy('sequence_in_thread')
                ->get()
                ->groupBy('thread_id');

            $messages->each(function (Message $message) use ($threadMessages) {
                $allInThread = $threadMessages->get($message->thread_id, collect());

                if ($allInThread->count() <= 21) {
                    $message->setAttribute('thread_context', $allInThread->map(fn (Message $m) => [
                        'sequence' => $m->sequence_in_thread,
                        'from' => $m->fromAgent?->name,
                        'content' => $m->content,
                        'created_at' => $m->created_at->toISOString(),
                    ])->values()->all());
                } else {
                    $first = $allInThread->first();
                    $last20 = $allInThread->slice(-20);
                    $context = collect([$first])->merge($last20)->unique('id');

                    $message->setAttribute('thread_context', $context->map(fn (Message $m) => [
                        'sequence' => $m->sequence_in_thread,
                        'from' => $m->fromAgent?->name,
                        'content' => $m->content,
                        'created_at' => $m->created_at->toISOString(),
                    ])->values()->all());
                }
            });
        }

        return response()->json(['data' => $messages]);
    }
}
