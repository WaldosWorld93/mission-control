<?php

namespace App\Filament\Resources\AgentResource\Pages;

use App\Filament\Resources\AgentResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditAgent extends EditRecord
{
    protected static string $resource = AgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('regenerateToken')
                ->label('Regenerate Token')
                ->icon('heroicon-o-key')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Regenerate API Token')
                ->modalDescription('This will invalidate the current token. The agent will need to be updated with the new token.')
                ->action(function (): void {
                    $token = $this->record->generateApiToken();
                    $this->record->save();

                    // Store token in session and redirect to setup page
                    session(["agent_token_{$this->record->id}" => $token]);

                    $this->redirect(url("agents/{$this->record->id}/setup"));
                }),
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
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
