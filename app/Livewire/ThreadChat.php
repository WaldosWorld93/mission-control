<?php

namespace App\Livewire;

use App\Enums\MessageType;
use App\Models\Message;
use App\Models\MessageThread;
use Filament\Notifications\Notification;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ThreadChat extends Component
{
    public MessageThread $thread;

    public bool $compact = false;

    public string $newMessage = '';

    public function mount(MessageThread $thread, bool $compact = false): void
    {
        $this->thread = $thread;
        $this->compact = $compact;
    }

    #[Computed]
    public function messages(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->thread->messages()
            ->with(['fromAgent', 'fromUser'])
            ->orderBy('sequence_in_thread')
            ->get();
    }

    public function sendMessage(): void
    {
        $content = trim($this->newMessage);

        if ($content === '') {
            return;
        }

        $nextSequence = ($this->thread->messages()->max('sequence_in_thread') ?? 0) + 1;

        $mentions = $this->extractMentions($content);

        Message::create([
            'team_id' => $this->thread->team_id,
            'project_id' => $this->thread->project_id,
            'thread_id' => $this->thread->id,
            'from_user_id' => auth()->id(),
            'sequence_in_thread' => $nextSequence,
            'content' => $content,
            'mentions' => $mentions,
            'read_by' => [],
            'message_type' => MessageType::Chat,
        ]);

        $this->thread->increment('message_count');

        $this->newMessage = '';
        unset($this->messages);

        $this->dispatch('message-sent');
    }

    public function markResolved(): void
    {
        $this->thread->update(['is_resolved' => true]);

        Notification::make()
            ->title('Thread marked as resolved')
            ->success()
            ->send();
    }

    public function reopenThread(): void
    {
        $this->thread->update(['is_resolved' => false]);

        Notification::make()
            ->title('Thread reopened')
            ->success()
            ->send();
    }

    /**
     * @return list<string>
     */
    protected function extractMentions(string $content): array
    {
        preg_match_all('/@([\w-]+)/', $content, $matches);

        if (empty($matches[1])) {
            return [];
        }

        return \App\Models\Agent::whereIn('name', $matches[1])
            ->pluck('id')
            ->all();
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.thread-chat');
    }
}
