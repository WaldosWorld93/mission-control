<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class TemplateDeployed extends Page
{
    protected static ?string $slug = 'templates/deployed';

    protected static ?string $title = 'Squad Deployed';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.template-deployed';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'tokens' => session('deployed_tokens', []),
            'templateName' => session('deployed_template', 'Squad'),
        ];
    }
}
