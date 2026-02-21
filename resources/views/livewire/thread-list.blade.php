<div class="flex h-[calc(100vh-12rem)] gap-0 overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
    {{-- Left sidebar: Thread list --}}
    <div class="w-80 flex-shrink-0 overflow-y-auto border-r border-gray-200 dark:border-gray-700">
        <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Threads</h3>
        </div>

        @forelse ($this->threads as $thread)
            @php
                $lastMessage = $thread->messages->first();
                $isSelected = $this->selectedThreadId === $thread->id;
            @endphp
            <button wire:click="selectThread('{{ $thread->id }}')"
                    class="w-full border-b border-gray-100 px-4 py-3 text-left transition hover:bg-gray-50 dark:border-gray-700/50 dark:hover:bg-gray-700/50
                           {{ $isSelected ? 'bg-indigo-50 dark:bg-indigo-950/20' : '' }}
                           {{ $thread->is_resolved ? 'opacity-60' : '' }}">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-900 dark:text-white {{ $thread->is_resolved ? 'line-through' : '' }}">
                        @if ($thread->is_resolved)
                            <x-heroicon-o-check-circle class="mr-1 inline h-3.5 w-3.5 text-emerald-500" />
                        @endif
                        {{ $thread->subject ?? 'Untitled Thread' }}
                    </span>
                    <span class="text-xs text-gray-400">{{ $thread->created_at->diffForHumans(short: true) }}</span>
                </div>

                {{-- Task link --}}
                @if ($thread->task)
                    <div class="mt-1 flex items-center gap-1">
                        <x-heroicon-o-clipboard-document-list class="h-3 w-3 text-indigo-400" />
                        <span class="truncate text-xs text-indigo-500 dark:text-indigo-400">{{ $thread->task->title }}</span>
                    </div>
                @endif

                {{-- Last message preview --}}
                @if ($lastMessage)
                    <p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">
                        <span class="font-medium">{{ $lastMessage->fromAgent?->name ?? $lastMessage->fromUser?->name ?? 'System' }}:</span>
                        {{ Str::limit($lastMessage->content, 60) }}
                    </p>
                @endif

                {{-- Message count --}}
                <div class="mt-1 flex items-center gap-2">
                    <span class="text-xs text-gray-400">{{ $thread->messages_count }} {{ Str::plural('message', $thread->messages_count) }}</span>
                    @if ($thread->startedByAgent)
                        <x-agent-avatar :agent="$thread->startedByAgent" size="sm" />
                    @endif
                </div>
            </button>
        @empty
            <div class="px-4 py-8 text-center">
                <x-heroicon-o-chat-bubble-left-right class="mx-auto h-8 w-8 text-gray-300 dark:text-gray-600" />
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No threads yet</p>
            </div>
        @endforelse
    </div>

    {{-- Right area: Thread messages --}}
    <div class="flex flex-1 flex-col">
        @if ($this->selectedThread)
            <livewire:thread-chat :thread="$this->selectedThread" :key="'thread-chat-'.$this->selectedThreadId" />
        @else
            <div class="flex flex-1 items-center justify-center">
                <div class="text-center">
                    <x-heroicon-o-chat-bubble-left-right class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600" />
                    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">Select a thread to view messages</p>
                </div>
            </div>
        @endif
    </div>
</div>
