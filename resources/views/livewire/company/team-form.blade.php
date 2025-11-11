<?php

use App\Models\Team;
use App\Helpers\CompanyAuth;
use Livewire\Volt\Component;

new class extends Component {
    public string $team_name = '';
    public string $captain_email = '';
    public string $captain_phone = '';

    public function save(): void
    {
        $user = CompanyAuth::user();

        if (!$user) {
            $this->redirect(route('company.login'), navigate: true);
            return;
        }

        // Check if team registrations are enabled
        if (!config('registration.team_enabled')) {
            $this->addError('team_name', 'Team registrations are currently closed.');
            return;
        }

        // Check team limit based on company's max_team_count
        $company = $user->company;
        $currentTeamCount = $user->teams()->count();
        $maxTeamCount = $company->max_team_count ?? 2;

        if ($currentTeamCount >= $maxTeamCount) {
            $this->addError('team_name', "You can only register up to {$maxTeamCount} teams.");
            return;
        }

        $validated = $this->validate([
            'team_name' => ['required', 'string', 'max:255'],
            'captain_email' => ['required', 'email', 'max:255'],
            'captain_phone' => ['required', 'string', 'max:20'],
        ]);

        Team::create([
            'user_id' => $user->id,
            'company_id' => $user->company_id,
            'team_name' => $validated['team_name'],
            'captain_email' => $validated['captain_email'],
            'captain_phone' => $validated['captain_phone'],
        ]);

        $this->redirect(route('company.teams'), navigate: true);
    }
}; ?>

<div>
    <x-mary-card>
        <x-mary-form wire:submit="save">
            <x-mary-input
                label="Team Name"
                wire:model="team_name"
                placeholder="Enter team name"
                required
            />

            <x-mary-input
                label="Team Captain Email"
                wire:model="captain_email"
                type="email"
                placeholder="captain@example.com"
                required
            />

            <x-mary-input
                label="Team Captain Phone"
                wire:model="captain_phone"
                type="tel"
                placeholder="+1 (555) 123-4567"
                required
            />

            <x-slot:actions>
                <x-mary-button label="Cancel" link="{{ route('company.teams') }}" />
                <x-mary-button label="Create Team" type="submit" class="btn-primary" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-card>
</div>
