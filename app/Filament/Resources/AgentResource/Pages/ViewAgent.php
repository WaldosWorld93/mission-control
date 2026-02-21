<?php

namespace App\Filament\Resources\AgentResource\Pages;

use App\Filament\Resources\AgentResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewAgent extends ViewRecord
{
    protected static string $resource = AgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('unpause')
                ->label('Un-pause Agent')
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn (): bool => $this->record->is_paused)
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record->update([
                        'is_paused' => false,
                        'consecutive_errors' => 0,
                        'paused_reason' => null,
                        'paused_at' => null,
                    ]);

                    Notification::make()
                        ->title('Agent un-paused')
                        ->success()
                        ->send();
                }),
            Actions\EditAction::make(),
        ];
    }
}
