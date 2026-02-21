<div class="flex items-center gap-3">
    <x-status-dot :status="$getRecord()->status" />
    <x-agent-avatar :agent="$getRecord()" />
    <span class="font-medium">{{ $getRecord()->name }}</span>
</div>
