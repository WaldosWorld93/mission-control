<?php

namespace App\Filament\Resources\AgentResource\Pages;

use App\Filament\Resources\AgentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAgents extends ListRecords
{
    protected static string $resource = AgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('addFromTemplate')
                ->label('Add from Template')
                ->icon('heroicon-o-square-3-stack-3d')
                ->color('gray')
                ->url(url('templates')),
            Actions\CreateAction::make()
                ->label('Add Custom Agent'),
        ];
    }
}
