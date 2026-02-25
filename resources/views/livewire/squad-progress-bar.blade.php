<div>
    @if ($agents->isNotEmpty())
        <div class="mt-6 rounded-xl" style="padding: 16px 24px; background-color: #f8fafc; border: 1px solid #e2e8f0;">
            <div class="flex items-center gap-1">
                @foreach ($agents as $index => $squadAgent)
                    @php
                        $isConnected = $squadAgent->last_heartbeat_at !== null;
                        $isCurrent = $squadAgent->id === $currentAgentId;
                    @endphp

                    {{-- Agent pill --}}
                    <div class="flex items-center gap-1.5 rounded-full px-3 py-1.5" style="{{ $isCurrent ? 'background-color: #e0e7ff;' : '' }}">
                        {{-- Status dot --}}
                        @if ($isConnected)
                            <div class="rounded-full" style="width: 8px; height: 8px; background-color: #10b981;"></div>
                        @else
                            <div class="relative flex items-center justify-center" style="width: 8px; height: 8px;">
                                <div class="absolute inset-0 rounded-full animate-ping" style="background-color: rgba(245, 158, 11, 0.3);"></div>
                                <div class="relative rounded-full" style="width: 8px; height: 8px; background-color: #f59e0b;"></div>
                            </div>
                        @endif

                        <span class="text-xs {{ $isCurrent ? 'font-bold' : 'font-medium' }} text-gray-700 dark:text-gray-300 whitespace-nowrap">
                            {{ $squadAgent->name }}@if ($squadAgent->is_lead) <span class="text-[10px] font-normal" style="color: #6366f1;">(Lead)</span>@endif
                        </span>
                    </div>

                    {{-- Arrow connector --}}
                    @if (! $loop->last)
                        <svg class="flex-shrink-0" style="width: 16px; height: 12px; color: #94a3b8;" viewBox="0 0 16 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M1 6h12m0 0l-4-4m4 4l-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    @endif
                @endforeach
            </div>
        </div>
    @endif
</div>
