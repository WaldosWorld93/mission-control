@if ($getRecord()->thread)
    <div class="h-96 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
        <livewire:thread-chat :thread="$getRecord()->thread" :key="'task-thread-'.$getRecord()->thread->id" />
    </div>
@endif
