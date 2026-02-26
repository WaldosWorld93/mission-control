<?php

namespace App\Filament\Resources\AgentResource\Pages;

use App\Filament\Resources\AgentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAgent extends CreateRecord
{
    protected static string $resource = AgentResource::class;

    protected ?string $generatedToken = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tempAgent = new \App\Models\Agent;
        $this->generatedToken = $tempAgent->generateApiToken();
        $data['api_token'] = $tempAgent->api_token;

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;

        if (empty($record->soul_md)) {
            $record->update([
                'soul_md' => $record->generateDefaultSoulMd(),
            ]);
        }

        // Store token in session for the setup page (persists across navigation)
        session(["agent_token_{$record->id}" => $this->generatedToken]);

        // Also store in deployed_tokens for template deployment compatibility
        $deployedTokens = session('deployed_tokens', []);
        $deployedTokens[] = [
            'name' => $record->name,
            'token' => $this->generatedToken,
        ];
        session(['deployed_tokens' => $deployedTokens]);
        session()->flash('agent_created', true);
    }

    protected function getRedirectUrl(): string
    {
        return url("agents/{$this->record->id}/setup");
    }
}
