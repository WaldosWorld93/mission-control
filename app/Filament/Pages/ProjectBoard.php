<?php

namespace App\Filament\Pages;

use App\Models\Project;
use Filament\Pages\Page;

class ProjectBoard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-view-columns';

    protected static string $view = 'filament.pages.project-board';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'projects/{project}/board';

    public Project $project;

    public function mount(Project $project): void
    {
        $this->project = $project;
    }

    public function getTitle(): string
    {
        return $this->project->name.' — Board';
    }

    public function getBreadcrumbs(): array
    {
        return [
            url('projects') => 'Projects',
            url("projects/{$this->project->id}/edit") => $this->project->name,
            '' => 'Board',
        ];
    }
}
