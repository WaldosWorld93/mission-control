<div class="flex h-full flex-col">
    {{-- Thread header --}}
    <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-700">
        <div class="min-w-0 flex-1">
            <h4 class="truncate text-sm font-semibold text-gray-900 dark:text-white">
                {{ $thread->subject ?? 'Conversation' }}
            </h4>
            @if ($thread->task)
                <div class="mt-0.5 flex items-center gap-1.5">
                    <x-heroicon-o-clipboard-document-list class="h-3 w-3 text-indigo-400" />
                    <span class="text-xs text-indigo-600 dark:text-indigo-400">{{ $thread->task->title }}</span>
                    <span class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-medium
                        {{ match($thread->task->status->value) {
                            'blocked' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
                            'done' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
                            'in_progress' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400',
                            default => 'bg-slate-100 text-slate-700 dark:bg-slate-900/30 dark:text-slate-400',
                        } }}">
                        {{ $thread->task->status->value }}
                    </span>
                </div>
            @endif
        </div>
        <div class="flex items-center gap-2">
            @if ($thread->is_resolved)
                <button wire:click="reopenThread"
                        class="rounded-lg px-2 py-1 text-xs text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700">
                    Reopen
                </button>
            @else
                <button wire:click="markResolved"
                        class="rounded-lg px-2 py-1 text-xs text-emerald-600 hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-950/30">
                    <x-heroicon-o-check-circle class="mr-1 inline h-3.5 w-3.5" />
                    Resolve
                </button>
            @endif
        </div>
    </div>

    {{-- Messages area --}}
    <div class="flex-1 overflow-y-auto px-4 py-4 space-y-4"
         x-data
         x-ref="messageArea"
         x-on:message-sent.window="$nextTick(() => $refs.messageArea.scrollTop = $refs.messageArea.scrollHeight)"
         x-init="$nextTick(() => $refs.messageArea.scrollTop = $refs.messageArea.scrollHeight)">
        @forelse ($this->messages as $message)
            @php
                $isHuman = $message->from_user_id !== null;
                $sender = $isHuman ? $message->fromUser : $message->fromAgent;
                $senderName = $sender?->name ?? 'System';
            @endphp
            <div class="{{ $isHuman ? 'chat-message-human' : 'chat-message-agent' }}">
                {{-- Sender info --}}
                <div class="mb-1.5 flex items-center gap-2">
                    @if ($message->fromAgent)
                        <x-agent-avatar :agent="$message->fromAgent" size="sm" />
                    @else
                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-indigo-500 text-xs font-semibold text-white">
                            {{ mb_strtoupper(mb_substr($senderName, 0, 1)) }}
                        </span>
                    @endif
                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $senderName }}</span>
                    <span class="text-xs text-gray-400">{{ $message->created_at->diffForHumans() }}</span>
                </div>

                {{-- Message content with @mention highlighting --}}
                <div class="pl-8 text-sm text-gray-700 dark:text-gray-300">
                    {!! preg_replace(
                        '/@([\w-]+)/',
                        '<span class="chat-mention">@$1</span>',
                        e($message->content)
                    ) !!}
                </div>
            </div>
        @empty
            <div class="flex h-full items-center justify-center">
                <p class="text-sm text-gray-400 dark:text-gray-500">No messages yet. Start the conversation below.</p>
            </div>
        @endforelse
    </div>

    {{-- Compose area --}}
    @if (! $compact)
        <div class="border-t border-gray-200 px-4 py-3 dark:border-gray-700">
            <form wire:submit="sendMessage" class="flex items-end gap-2">
                <div class="flex-1">
                    <textarea wire:model="newMessage"
                              rows="2"
                              placeholder="Type a message... Use @name to mention an agent"
                              class="w-full resize-none rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-500"
                              @keydown.enter.prevent="if (!$event.shiftKey) $wire.sendMessage()"
                    ></textarea>
                </div>
                <button type="submit"
                        class="flex-shrink-0 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:bg-indigo-500 dark:hover:bg-indigo-400">
                    Send
                </button>
            </form>
        </div>
    @endif
</div>
