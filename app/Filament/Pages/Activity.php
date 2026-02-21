<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Activity extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $title = 'Activity';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.activity';

    protected static ?string $slug = 'activity';
}
