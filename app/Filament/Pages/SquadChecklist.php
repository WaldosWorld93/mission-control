<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class SquadChecklist extends Page
{
    protected static ?string $slug = 'setup/squad';

    protected static ?string $title = 'Squad Setup';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.squad-checklist';
}
