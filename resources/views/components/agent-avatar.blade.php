@props([
    'agent' => null,
    'size' => 'md',
])

@php
    $name = is_string($agent) ? $agent : ($agent?->name ?? '??');
    $words = explode(' ', trim($name));
    $initials = count($words) >= 2
        ? mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1))
        : mb_strtoupper(mb_substr($name, 0, 2));

    $palette = [
        'bg-indigo-500', 'bg-emerald-500', 'bg-amber-500', 'bg-rose-500',
        'bg-sky-500', 'bg-violet-500', 'bg-teal-500', 'bg-pink-500',
        'bg-cyan-500', 'bg-orange-500', 'bg-lime-500', 'bg-fuchsia-500',
    ];
    $colorClass = $palette[crc32($name) % count($palette)];

    $sizeClass = match($size) {
        'xs' => 'agent-avatar--xs',
        'sm' => 'agent-avatar--sm',
        'lg' => 'agent-avatar--lg',
        default => 'agent-avatar--md',
    };
@endphp

<span {{ $attributes->merge(['class' => "agent-avatar {$sizeClass} {$colorClass}"]) }}
      title="{{ $name }}">
    {{ $initials }}
</span>
