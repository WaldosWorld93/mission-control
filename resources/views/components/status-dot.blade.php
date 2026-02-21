@props([
    'status' => null,
    'size' => 'md',
])

@php
    $colorClasses = match($status?->value ?? $status) {
        'online' => 'bg-emerald-500',
        'busy' => 'bg-sky-500',
        'idle' => 'bg-amber-500',
        'offline' => 'bg-rose-500',
        'error' => 'bg-rose-500',
        default => 'bg-slate-300',
    };

    $sizeClasses = match($size) {
        'sm' => 'status-dot--sm',
        'lg' => 'status-dot--lg',
        default => 'status-dot--md',
    };

    $pulseClass = ($status?->value ?? $status) === 'online' ? 'status-dot--pulse' : '';
@endphp

<span {{ $attributes->merge(['class' => "status-dot {$sizeClasses} {$colorClasses} {$pulseClass}"]) }}></span>
