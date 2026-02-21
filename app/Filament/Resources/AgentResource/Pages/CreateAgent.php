<?php

namespace App\Filament\Resources\AgentResource\Pages;

use App\Filament\Resources\AgentResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateAgent extends CreateRecord
{
    protected static string $resource = AgentResource::class;

    protected ?string $generatedToken = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->generatedToken = Str::random(64);
        $data['api_token'] = hash('sha256', $this->generatedToken);

        return $data;
    }

    protected function afterCreate(): void
    {
        Notification::make()
            ->title('API Token Generated')
            ->body("Copy this token now — it won't be shown again:\n\n`{$this->generatedToken}`")
            ->persistent()
            ->success()
            ->send();
    }
}
