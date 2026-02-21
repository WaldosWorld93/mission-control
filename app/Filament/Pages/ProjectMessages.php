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
            route('filament.app.resources.projects.index') => 'Projects',
            route('filament.app.resources.projects.edit', $this->project) => $this->project->name,
            '' => 'Messages',
        ];
    }

    public static function getRouteName(?string $panel = null): string
    {
        return 'filament.app.pages.project-messages';
    }
}
