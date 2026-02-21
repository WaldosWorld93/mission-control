<?php

namespace App\Events;

use App\Events\Concerns\LogsActivity;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, LogsActivity, SerializesModels;

    public function __construct(
        public int $teamId,
        public string $messageId,
        public string $fromName,
        public string $contentPreview,
        public ?string $threadSubject = null,
        public ?string $threadId = null,
        public ?string $projectId = null,
    ) {
        $this->logActivity();
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("team.{$this->teamId}")];
    }

    protected function eventType(): string
    {
        return 'message.created';
    }

    protected function actorType(): string
    {
        return 'agent';
    }

    protected function actorId(): ?string
    {
        return null;
    }

    protected function activityDescription(): string
    {
        $thread = $this->threadSubject ? " in {$this->threadSubject}" : '';

        return "{$this->fromName} sent a message{$thread}";
    }

    protected function activityMetadata(): ?array
    {
        return [
            'message_id' => $this->messageId,
            'from_name' => $this->fromName,
            'content_preview' => $this->contentPreview,
            'thread_subject' => $this->threadSubject,
            'thread_id' => $this->threadId,
        ];
    }

    protected function projectId(): ?string
    {
        return $this->projectId;
    }
}
