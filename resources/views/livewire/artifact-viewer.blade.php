<div>
    @if ($this->artifacts->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-200 py-10 dark:border-slate-700">
            <x-heroicon-o-paper-clip class="mb-2 h-8 w-8 text-slate-300 dark:text-slate-600" />
            <p class="text-sm text-slate-400 dark:text-slate-500">No artifacts yet.</p>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Agents will upload deliverables here as they complete work.</p>
        </div>
    @else
        <div class="space-y-2">
            @foreach ($this->artifacts as $artifact)
                @php
                    $isExpanded = $expandedArtifactId === $artifact->id;
                @endphp

                {{-- File Card --}}
                <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700">
                    <button wire:click="expandArtifact('{{ $artifact->id }}')"
                            class="flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <x-dynamic-component :component="\App\Livewire\ArtifactViewer::typeIcon($artifact->artifact_type)"
                                             class="h-5 w-5 flex-shrink-0 text-slate-400" />
                        <div class="flex-1 min-w-0">
                            <p class="truncate text-sm font-medium text-slate-900 dark:text-white">
                                {{ $artifact->display_name ?? $artifact->filename }}
                            </p>
                            <p class="text-xs text-slate-400">
                                {{ $artifact->filename }}
                                · {{ $artifact->size_bytes ? number_format($artifact->size_bytes / 1024, 1) . ' KB' : '—' }}
                            </p>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                            v{{ $artifact->version }}
                        </span>
                        <x-heroicon-o-chevron-down class="h-4 w-4 text-slate-400 transition {{ $isExpanded ? 'rotate-180' : '' }}" />
                    </button>

                    {{-- Expanded Content --}}
                    @if ($isExpanded && $this->expandedArtifact)
                        @php
                            $active = $this->expandedArtifact;
                            $versions = $this->versionHistory;
                        @endphp
                        <div class="border-t border-slate-200 dark:border-slate-700">
                            {{-- Version bar --}}
                            @if ($versions->count() > 1)
                                <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2 dark:border-slate-700">
                                    <span class="text-xs text-slate-500">Version:</span>
                                    <div class="flex gap-1">
                                        @foreach ($versions as $v)
                                            <button wire:click="selectVersion({{ $v->version }})"
                                                    class="rounded px-2 py-0.5 text-xs font-medium transition
                                                        {{ $selectedVersion === $v->version
                                                            ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400'
                                                            : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                                                v{{ $v->version }}{{ $v->version === $artifact->version ? ' (latest)' : '' }}
                                            </button>
                                        @endforeach
                                    </div>

                                    @if ($versions->count() > 1 && \App\Livewire\ArtifactViewer::isTextBased($active))
                                        <div class="ml-auto">
                                            @php
                                                $prevVersion = $versions->where('version', '<', $selectedVersion)->first()?->version
                                                    ?? $versions->where('version', '!=', $selectedVersion)->last()?->version;
                                            @endphp
                                            @if ($prevVersion)
                                                <button wire:click="toggleDiff({{ $prevVersion }})"
                                                        class="rounded px-2 py-0.5 text-xs font-medium transition
                                                            {{ $showDiff
                                                                ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
                                                                : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                                                    {{ $showDiff ? 'Hide diff' : 'Show diff' }}
                                                </button>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            @endif

                            {{-- Content Area --}}
                            <div class="max-h-[500px] overflow-auto">
                                @if ($showDiff && $this->diffData)
                                    {{-- Diff View --}}
                                    <div class="p-4">
                                        <p class="mb-2 text-xs text-slate-500">
                                            Comparing v{{ $this->diffData['old']->version }} → v{{ $this->diffData['new']->version }}
                                        </p>
                                        <div class="space-y-0 rounded-lg border border-slate-200 font-mono text-xs dark:border-slate-700">
                                            @php
                                                $oldLines = $this->diffData['oldLines'];
                                                $newLines = $this->diffData['newLines'];
                                                $maxLines = max(count($oldLines), count($newLines));
                                            @endphp
                                            @for ($i = 0; $i < $maxLines; $i++)
                                                @php
                                                    $oldLine = $oldLines[$i] ?? null;
                                                    $newLine = $newLines[$i] ?? null;
                                                    $isAdded = $oldLine === null || ($newLine !== null && $oldLine !== $newLine);
                                                    $isRemoved = $newLine === null || ($oldLine !== null && $oldLine !== $newLine);
                                                @endphp
                                                @if ($oldLine !== $newLine)
                                                    @if ($oldLine !== null)
                                                        <div class="flex bg-rose-50 dark:bg-rose-950/20">
                                                            <span class="w-8 flex-shrink-0 select-none border-r border-slate-200 px-1 text-right text-slate-400 dark:border-slate-700">-</span>
                                                            <pre class="flex-1 px-2 py-0.5 text-rose-700 dark:text-rose-400">{{ $oldLine }}</pre>
                                                        </div>
                                                    @endif
                                                    @if ($newLine !== null)
                                                        <div class="flex bg-emerald-50 dark:bg-emerald-950/20">
                                                            <span class="w-8 flex-shrink-0 select-none border-r border-slate-200 px-1 text-right text-slate-400 dark:border-slate-700">+</span>
                                                            <pre class="flex-1 px-2 py-0.5 text-emerald-700 dark:text-emerald-400">{{ $newLine }}</pre>
                                                        </div>
                                                    @endif
                                                @else
                                                    <div class="flex">
                                                        <span class="w-8 flex-shrink-0 select-none border-r border-slate-200 px-1 text-right text-slate-400 dark:border-slate-700">{{ $i + 1 }}</span>
                                                        <pre class="flex-1 px-2 py-0.5 text-slate-600 dark:text-slate-400">{{ $newLine }}</pre>
                                                    </div>
                                                @endif
                                            @endfor
                                        </div>
                                    </div>
                                @elseif (\App\Livewire\ArtifactViewer::isImage($active))
                                    {{-- Image Preview --}}
                                    <div class="flex items-center justify-center bg-slate-50 p-6 dark:bg-slate-900/50"
                                         x-data="{ lightbox: false }">
                                        @if ($active->storage_path)
                                            <img src="{{ asset('storage/' . $active->storage_path) }}"
                                                 alt="{{ $active->display_name ?? $active->filename }}"
                                                 class="max-h-80 cursor-pointer rounded-lg shadow-sm"
                                                 @click="lightbox = true" />

                                            {{-- Lightbox --}}
                                            <div x-show="lightbox"
                                                 x-transition
                                                 @click="lightbox = false"
                                                 @keydown.escape.window="lightbox = false"
                                                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-8"
                                                 x-cloak>
                                                <img src="{{ asset('storage/' . $active->storage_path) }}"
                                                     alt="{{ $active->display_name ?? $active->filename }}"
                                                     class="max-h-full max-w-full rounded-lg" />
                                            </div>
                                        @else
                                            <p class="text-sm text-slate-400">Image preview not available</p>
                                        @endif
                                    </div>
                                @elseif (\App\Livewire\ArtifactViewer::isMarkdown($active) && $active->content_text)
                                    {{-- Markdown Rendered --}}
                                    <div class="prose prose-sm prose-slate max-w-none p-4 dark:prose-invert">
                                        {!! \Illuminate\Support\Str::markdown($active->content_text) !!}
                                    </div>
                                @elseif (\App\Livewire\ArtifactViewer::isCode($active) && $active->content_text)
                                    {{-- Code Highlighted --}}
                                    <div class="relative">
                                        <span class="absolute right-2 top-2 rounded bg-slate-200 px-1.5 py-0.5 text-[10px] font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-400">
                                            {{ \App\Livewire\ArtifactViewer::getLanguage($active->filename) }}
                                        </span>
                                        <pre class="overflow-x-auto bg-slate-50 p-4 font-mono text-xs leading-relaxed text-slate-700 dark:bg-slate-900/50 dark:text-slate-300"><code>{{ $active->content_text }}</code></pre>
                                    </div>
                                @elseif (\App\Livewire\ArtifactViewer::isTextBased($active) && $active->content_text)
                                    {{-- Plain Text --}}
                                    <pre class="overflow-x-auto p-4 font-mono text-xs leading-relaxed text-slate-700 dark:text-slate-300">{{ $active->content_text }}</pre>
                                @else
                                    {{-- No preview available --}}
                                    <div class="flex flex-col items-center justify-center py-8">
                                        <x-heroicon-o-document class="mb-2 h-8 w-8 text-slate-300 dark:text-slate-600" />
                                        <p class="text-sm text-slate-400">Preview not available for this file type.</p>
                                    </div>
                                @endif
                            </div>

                            {{-- Footer with uploader info --}}
                            <div class="flex items-center justify-between border-t border-slate-100 px-4 py-2 dark:border-slate-700">
                                <p class="text-xs text-slate-400">
                                    Uploaded {{ $active->created_at->diffForHumans() }}
                                    @if ($active->uploadedByAgent)
                                        by {{ $active->uploadedByAgent->name }}
                                    @endif
                                </p>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
