<?php

namespace App\Livewire;

use App\Models\MessageThread;
use App\Models\Project;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ThreadList extends Component
{
    public Project $project;

    public ?string $selectedThreadId = null;

    /**
     * @return array<string, string>
     */
    protected function getListeners(): array
    {
        $teamId = auth()->user()?->current_team_id;

        if (! $teamId) {
            return [];
        }

        return [
            "echo-private:team.{$teamId},MessageCreated" => '$refresh',
        ];
    }

    public function mount(Project $project): void
    {
        $this->project = $project;
    }

    #[Computed]
    public function threads(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->project->messageThreads()
            ->with(['startedByAgent', 'startedByUser', 'task', 'messages' => fn ($q) => $q->latest('sequence_in_thread')->limit(1)])
            ->withCount('messages')
            ->latest()
            ->get();
    }

    public function selectThread(string $threadId): void
    {
        $this->selectedThreadId = $threadId;
        $this->dispatch('thread-selected', threadId: $threadId);
    }

    #[Computed]
    public function selectedThread(): ?MessageThread
    {
        if (! $this->selectedThreadId) {
            return null;
        }

        return MessageThread::with([
            'messages.fromAgent',
            'messages.fromUser',
            'task',
        ])->find($this->selectedThreadId);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.thread-list');
    }
}
