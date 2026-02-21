<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Onboarding extends Page
{
    protected static ?string $slug = 'onboarding';

    protected static ?string $title = 'Welcome to Mission Control';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.onboarding';
}
