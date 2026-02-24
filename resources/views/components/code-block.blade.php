@props(['language' => 'bash', 'label' => null, 'maxHeight' => null])

<div class="group" x-data="{ copied: false }">
    @if ($label)
        <div class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400">{{ $label }}</div>
    @endif
    <div class="relative rounded-lg" style="background-color: #f8fafc; border: 1px solid #e2e8f0;">
        <button
            x-on:click="
                navigator.clipboard.writeText($refs.code.textContent.trim());
                copied = true;
                setTimeout(() => copied = false, 2000);
            "
            style="position: absolute; top: 8px; right: 8px; z-index: 10; display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 500; background-color: #f8fafc; color: #4b5563; border: 1px solid #e2e8f0; cursor: pointer; transition: background-color 0.15s;"
            onmouseover="this.style.backgroundColor='#f1f5f9'"
            onmouseout="this.style.backgroundColor='#f8fafc'"
        >
            <template x-if="!copied">
                <span class="flex items-center gap-1">
                    <x-heroicon-o-clipboard class="h-3.5 w-3.5" />
                    Copy
                </span>
            </template>
            <template x-if="copied">
                <span class="flex items-center gap-1" style="color: #059669;">
                    <x-heroicon-o-check class="h-3.5 w-3.5" />
                    Copied
                </span>
            </template>
        </button>
        <pre class="m-0 rounded-lg" style="overflow-x: auto; padding: 16px; padding-top: 44px; padding-right: 80px; font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 13px; line-height: 1.6; white-space: pre; background: transparent;{{ $maxHeight ? " max-height: {$maxHeight}; overflow-y: auto;" : '' }}"><code x-ref="code" class="language-{{ $language }} text-gray-800 dark:text-gray-200">{{ $slot }}</code></pre>
    </div>
</div>
