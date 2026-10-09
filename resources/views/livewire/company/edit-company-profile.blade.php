<?php

use App\Models\Company;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public Company $company;
    public string $name = '';
    public string $phone = '';
    public $logo = null;
    public ?string $existingLogo = null;
    public string $description = '';
    public bool $showDescriptionModal = false;

    public function mount(Company $company): void
    {
        $this->company = $company;
        $this->name = $company->name ?? '';
        $this->phone = $company->phone ?? '';
        $this->existingLogo = $company->logo;
        $this->description = $company->description ?? '';
    }

    public function updateCompany(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        $data = [
            'name' => $validated['name'],
            'phone' => $validated['phone'],
        ];

        if ($this->logo) {
            // Delete old logo if exists
            if ($this->existingLogo) {
                \Storage::disk('public')->delete($this->existingLogo);
            }
            $data['logo'] = $this->logo->store('company-logos', 'public');
            $this->existingLogo = $data['logo'];
            $this->logo = null;
        }

        $this->company->update($data);
        $this->dispatch('company-updated');
    }

    public function openDescriptionModal(): void
    {
        $this->showDescriptionModal = true;
    }

    public function closeDescriptionModal(): void
    {
        $this->showDescriptionModal = false;
    }

    public function updateDescription(): void
    {
        $validated = $this->validate([
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->company->update([
            'description' => $validated['description'],
        ]);

        $this->closeDescriptionModal();
        $this->dispatch('company-updated');
    }
}; ?>

<div>
    <div class="card bg-base-100 border-base-300 border-2">
        <div class="card-body">
            <h2 class="card-title">Edit Company Details</h2>

            <form wire:submit="updateCompany" class="space-y-4">
                <!-- Company Name -->
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-medium">Company Name</span>
                    </label>
                    <input
                        type="text"
                        wire:model="name"
                        class="input input-bordered w-full @error('name') input-error @enderror"
                        placeholder="Enter company name"
                    />
                    @error('name')
                        <label class="label">
                            <span class="label-text-alt text-error">{{ $message }}</span>
                        </label>
                    @enderror
                </div>

                <!-- Phone -->
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-medium">Phone</span>
                    </label>
                    <input
                        type="text"
                        wire:model="phone"
                        class="input input-bordered w-full @error('phone') input-error @enderror"
                        placeholder="Enter phone number"
                    />
                    @error('phone')
                        <label class="label">
                            <span class="label-text-alt text-error">{{ $message }}</span>
                        </label>
                    @enderror
                </div>

                <!-- Logo -->
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-medium">Company Logo</span>
                    </label>
                    <x-mary-file wire:model="logo" accept="image/png, image/jpeg">
                        <img src="{{ $logo ? $logo->temporaryUrl() : ($existingLogo ? Storage::url($existingLogo) : '/placeholder/logo.avif') }}" class="h-32 w-32 rounded-lg object-cover" />
                    </x-mary-file>
                    @error('logo')
                        <label class="label">
                            <span class="label-text-alt text-error">{{ $message }}</span>
                        </label>
                    @enderror
                </div>

                <!-- Description Button -->
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-medium">Company Description</span>
                    </label>
                    <button type="button" wire:click="openDescriptionModal" class="btn btn-outline btn-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                        </svg>
                        Edit Description
                    </button>
                    @if($description)
                        <p class="text-xs text-base-content/70 mt-2">Description added ({{ strlen($description) }} characters)</p>
                    @endif
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn btn-primary w-full">
                    Save Changes
                </button>
            </form>
        </div>
    </div>

    <!-- Description Modal -->
    <x-mary-modal wire:model="showDescriptionModal" title="Edit Company Description" class="backdrop-blur">
        <form wire:submit="updateDescription">
            <div class="form-control">
                <label class="label">
                    <span class="label-text font-medium">Description (Markdown supported)</span>
                </label>
                <x-mary-markdown wire:model="description" label="" />
                @error('description')
                    <label class="label">
                        <span class="label-text-alt text-error">{{ $message }}</span>
                    </label>
                @enderror
            </div>

            <div class="modal-action">
                <button type="button" wire:click="closeDescriptionModal" class="btn btn-ghost">
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    Save Description
                </button>
            </div>
        </form>
    </x-mary-modal>
</div>
