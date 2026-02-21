<?php

namespace App\Livewire;

use App\Enums\ArtifactType;
use App\Models\Task;
use App\Models\TaskArtifact;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ArtifactViewer extends Component
{
    public Task $task;

    public ?string $expandedArtifactId = null;

    public ?int $selectedVersion = null;

    public bool $showDiff = false;

    public ?int $diffFromVersion = null;

    public function mount(Task $task): void
    {
        $this->task = $task;
    }

    #[Computed]
    public function artifacts(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->task->artifacts()
            ->orderBy('filename')
            ->orderByDesc('version')
            ->get()
            ->groupBy('filename')
            ->map(fn ($versions) => $versions->first())
            ->values();
    }

    public function expandArtifact(string $artifactId): void
    {
        if ($this->expandedArtifactId === $artifactId) {
            $this->expandedArtifactId = null;
            $this->selectedVersion = null;
            $this->showDiff = false;

            return;
        }

        $this->expandedArtifactId = $artifactId;
        $artifact = TaskArtifact::find($artifactId);
        $this->selectedVersion = $artifact?->version;
        $this->showDiff = false;
        $this->diffFromVersion = null;
    }

    public function selectVersion(int $version): void
    {
        $this->selectedVersion = $version;
        $this->showDiff = false;
    }

    public function toggleDiff(int $fromVersion): void
    {
        $this->diffFromVersion = $fromVersion;
        $this->showDiff = ! $this->showDiff;
    }

    #[Computed]
    public function expandedArtifact(): ?TaskArtifact
    {
        if (! $this->expandedArtifactId) {
            return null;
        }

        $artifact = TaskArtifact::find($this->expandedArtifactId);

        if (! $artifact) {
            return null;
        }

        if ($this->selectedVersion && $this->selectedVersion !== $artifact->version) {
            return TaskArtifact::where('task_id', $artifact->task_id)
                ->where('filename', $artifact->filename)
                ->where('version', $this->selectedVersion)
                ->first() ?? $artifact;
        }

        return $artifact;
    }

    #[Computed]
    public function versionHistory(): \Illuminate\Support\Collection
    {
        if (! $this->expandedArtifactId) {
            return collect();
        }

        $artifact = TaskArtifact::find($this->expandedArtifactId);

        if (! $artifact) {
            return collect();
        }

        return TaskArtifact::where('task_id', $artifact->task_id)
            ->where('filename', $artifact->filename)
            ->orderByDesc('version')
            ->get();
    }

    #[Computed]
    public function diffData(): ?array
    {
        if (! $this->showDiff || ! $this->expandedArtifact || ! $this->diffFromVersion) {
            return null;
        }

        $current = $this->expandedArtifact;
        $old = TaskArtifact::where('task_id', $current->task_id)
            ->where('filename', $current->filename)
            ->where('version', $this->diffFromVersion)
            ->first();

        if (! $old) {
            return null;
        }

        return [
            'old' => $old,
            'new' => $current,
            'oldLines' => explode("\n", $old->content_text ?? ''),
            'newLines' => explode("\n", $current->content_text ?? ''),
        ];
    }

    public static function getLanguage(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($ext) {
            'php' => 'php',
            'js', 'jsx' => 'javascript',
            'ts', 'tsx' => 'typescript',
            'py' => 'python',
            'rb' => 'ruby',
            'go' => 'go',
            'rs' => 'rust',
            'java' => 'java',
            'css' => 'css',
            'html', 'htm' => 'html',
            'json' => 'json',
            'yaml', 'yml' => 'yaml',
            'xml' => 'xml',
            'sql' => 'sql',
            'sh', 'bash' => 'bash',
            'md', 'markdown' => 'markdown',
            default => 'plaintext',
        };
    }

    public static function isTextBased(TaskArtifact $artifact): bool
    {
        return in_array($artifact->artifact_type, [ArtifactType::Document, ArtifactType::Code, ArtifactType::Data])
            || Str::startsWith($artifact->mime_type ?? '', 'text/');
    }

    public static function isImage(TaskArtifact $artifact): bool
    {
        return $artifact->artifact_type === ArtifactType::Image
            || Str::startsWith($artifact->mime_type ?? '', 'image/');
    }

    public static function isMarkdown(TaskArtifact $artifact): bool
    {
        $ext = strtolower(pathinfo($artifact->filename, PATHINFO_EXTENSION));

        return in_array($ext, ['md', 'markdown']) || $artifact->mime_type === 'text/markdown';
    }

    public static function isCode(TaskArtifact $artifact): bool
    {
        $ext = strtolower(pathinfo($artifact->filename, PATHINFO_EXTENSION));
        $codeExts = ['php', 'js', 'jsx', 'ts', 'tsx', 'py', 'rb', 'go', 'rs', 'java', 'css', 'html', 'json', 'yaml', 'yml', 'xml', 'sql', 'sh', 'bash'];

        return in_array($ext, $codeExts) || $artifact->artifact_type === ArtifactType::Code;
    }

    public static function typeIcon(ArtifactType $type): string
    {
        return match ($type) {
            ArtifactType::Document => 'heroicon-o-document-text',
            ArtifactType::Code => 'heroicon-o-code-bracket',
            ArtifactType::Image => 'heroicon-o-photo',
            ArtifactType::Data => 'heroicon-o-table-cells',
            ArtifactType::Other => 'heroicon-o-paper-clip',
        };
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.artifact-viewer');
    }
}
