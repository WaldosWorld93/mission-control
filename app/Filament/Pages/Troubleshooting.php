<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Troubleshooting extends Page
{
    protected static ?string $slug = 'troubleshooting';

    protected static ?string $title = 'Troubleshooting';

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 90;

    protected static string $view = 'filament.pages.troubleshooting';
}
