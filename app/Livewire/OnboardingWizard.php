<?php

namespace App\Livewire;

use App\Filament\Resources\AgentResource;
use Livewire\Component;

class OnboardingWizard extends Component
{
    public string $step = 'welcome';

    public function chooseTemplate(): void
    {
        $this->completeOnboarding();
        $this->redirect(url('templates'), navigate: true);
    }

    public function chooseManual(): void
    {
        $this->completeOnboarding();
        $this->redirect(AgentResource::getUrl('create'), navigate: true);
    }

    public function chooseExisting(): void
    {
        $this->step = 'existing';
    }

    public function finishExisting(): void
    {
        $this->completeOnboarding();
        $this->redirect(AgentResource::getUrl('create'), navigate: true);
    }

    public function skip(): void
    {
        $this->completeOnboarding();
        $this->redirect(url('home'), navigate: true);
    }

    private function completeOnboarding(): void
    {
        $team = auth()->user()->currentTeam;

        if ($team) {
            $team->update(['onboarding_completed_at' => now()]);
        }
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.onboarding-wizard');
    }
}
