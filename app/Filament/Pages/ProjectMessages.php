<?php

namespace App\Filament\Pages;

use App\Models\Project;
use Filament\Pages\Page;

class ProjectMessages extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string $view = 'filament.pages.project-messages';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'projects/{project}/messages';

    public Project $project;

    public function mount(Project $project): void
    {
        $this->project = $project;
    }

    public function getTitle(): string
    {
        return $this->project->name.' — Messages';
    }

    public function getBreadcrumbs(): array
    {
        return [
            url('projects') => 'Projects',
            url("projects/{$this->project->id}/edit") => $this->project->name,
            '' => 'Messages',
        ];
    }
}
