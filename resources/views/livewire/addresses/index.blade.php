<?php

use App\Models\Address;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $label = '';
    public string $recipient_name = '';
    public string $phone = '';
    public string $country = 'Cameroon';
    public string $region = '';
    public string $city = '';
    public string $street = '';
    public string $landmark = '';
    public bool $is_default = false;

    public function addNew(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $addressId): void
    {
        $address = Auth::user()->addresses()->findOrFail($addressId);

        $this->editingId = $address->id;
        $this->label = (string) $address->label;
        $this->recipient_name = (string) $address->recipient_name;
        $this->phone = (string) $address->phone;
        $this->country = $address->country;
        $this->region = (string) $address->region;
        $this->city = $address->city;
        $this->street = $address->street;
        $this->landmark = (string) $address->landmark;
        $this->is_default = $address->is_default;
        $this->showForm = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'country' => ['required', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'street' => ['required', 'string', 'max:255'],
            'landmark' => ['nullable', 'string', 'max:255'],
        ]);

        $user = Auth::user();
        $isFirstAddress = $user->addresses()->count() === 0;

        if ($this->editingId) {
            $address = $user->addresses()->findOrFail($this->editingId);
            $address->update($validated);
        } else {
            $address = $user->addresses()->create($validated);
        }

        if ($this->is_default || $isFirstAddress) {
            $user->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
            $address->update(['is_default' => true]);
        }

        $this->resetForm();
        $this->showForm = false;
    }

    public function makeDefault(int $addressId): void
    {
        $user = Auth::user();
        $user->addresses()->update(['is_default' => false]);
        $user->addresses()->where('id', $addressId)->update(['is_default' => true]);
    }

    public function delete(int $addressId): void
    {
        $user = Auth::user();
        $address = $user->addresses()->findOrFail($addressId);
        $wasDefault = $address->is_default;
        $address->delete();

        if ($wasDefault) {
            $user->addresses()->first()?->update(['is_default' => true]);
        }
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'label', 'recipient_name', 'phone', 'region', 'city', 'street', 'landmark', 'is_default']);
        $this->country = 'Cameroon';
        $this->resetErrorBag();
    }

    public function with(): array
    {
        return [
            'addresses' => Auth::user()->addresses()->orderByDesc('is_default')->latest()->get(),
        ];
    }
}; ?>

<div>
    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="flex items-center justify-between flex-wrap gap-3 mb-6">
                <div>
                    <h1 class="font-heading text-2xl font-bold text-navy-900">My Addresses</h1>
                    <p class="mt-1 text-sm text-navy-500">Manage the delivery addresses used at checkout.</p>
                </div>
                @unless ($showForm)
                    <x-primary-button wire:click="addNew">
                        <x-icon name="map-pin" class="w-4 h-4" />
                        Add Address
                    </x-primary-button>
                @endunless
            </div>

            @if ($showForm)
                <div class="bg-white rounded-2xl border border-navy-100 p-6 mb-8">
                    <h2 class="font-heading font-semibold text-navy-900">{{ $editingId ? 'Edit Address' : 'New Address' }}</h2>

                    <form wire:submit="save" class="mt-4 space-y-4">
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="label" value="Label (optional)" />
                                <x-text-input wire:model="label" id="label" class="block mt-1 w-full" placeholder="Home, Site Office..." />
                            </div>
                            <div>
                                <x-input-label for="recipient_name" value="Recipient Name" />
                                <x-text-input wire:model="recipient_name" id="recipient_name" class="block mt-1 w-full" />
                                <x-input-error :messages="$errors->get('recipient_name')" class="mt-1" />
                            </div>
                        </div>

                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="phone" value="Phone" />
                                <x-text-input wire:model="phone" id="phone" class="block mt-1 w-full" />
                                <x-input-error :messages="$errors->get('phone')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="country" value="Country" />
                                <x-text-input wire:model="country" id="country" class="block mt-1 w-full" />
                                <x-input-error :messages="$errors->get('country')" class="mt-1" />
                            </div>
                        </div>

                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="region" value="Region (optional)" />
                                <x-text-input wire:model="region" id="region" class="block mt-1 w-full" />
                            </div>
                            <div>
                                <x-input-label for="city" value="City" />
                                <x-text-input wire:model="city" id="city" class="block mt-1 w-full" />
                                <x-input-error :messages="$errors->get('city')" class="mt-1" />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="street" value="Street / Address" />
                            <x-text-input wire:model="street" id="street" class="block mt-1 w-full" />
                            <x-input-error :messages="$errors->get('street')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label for="landmark" value="Landmark (optional)" />
                            <x-text-input wire:model="landmark" id="landmark" class="block mt-1 w-full" />
                        </div>

                        <label class="flex items-center gap-2 text-sm text-navy-700">
                            <input type="checkbox" wire:model="is_default" class="rounded border-navy-300 text-gold-600 focus:ring-gold-500">
                            Set as default address
                        </label>

                        <div class="flex items-center gap-3 pt-2">
                            <x-primary-button type="submit">Save Address</x-primary-button>
                            <button type="button" wire:click="cancel" class="text-sm text-navy-500 hover:text-navy-700 transition-colors">Cancel</button>
                        </div>
                    </form>
                </div>
            @endif

            @if ($addresses->isEmpty() && ! $showForm)
                <div class="bg-white rounded-2xl border border-navy-100 p-10 text-center">
                    <span class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-navy-50 text-navy-400 mx-auto">
                        <x-icon name="map-pin" class="w-6 h-6" />
                    </span>
                    <p class="mt-4 text-sm text-navy-500">You haven't added a delivery address yet.</p>
                </div>
            @else
                <div class="grid sm:grid-cols-2 gap-4">
                    @foreach ($addresses as $address)
                        <div class="bg-white rounded-2xl border border-navy-100 p-5 relative">
                            @if ($address->is_default)
                                <span class="absolute top-4 right-4 text-xs font-semibold text-gold-800 bg-gold-100 rounded-full px-2.5 py-1">Default</span>
                            @endif

                            @if ($address->label)
                                <p class="text-xs font-semibold text-gold-800 uppercase tracking-wide">{{ $address->label }}</p>
                            @endif
                            <p class="font-semibold text-navy-900 text-sm mt-1">{{ $address->recipient_name }} &middot; {{ $address->phone }}</p>
                            <p class="text-sm text-navy-500 mt-1">
                                {{ $address->street }}, {{ $address->city }}@if($address->region), {{ $address->region }}@endif, {{ $address->country }}
                            </p>
                            @if ($address->landmark)
                                <p class="text-xs text-navy-400 mt-1">Near {{ $address->landmark }}</p>
                            @endif

                            <div class="mt-4 flex items-center gap-4 text-sm">
                                <button type="button" wire:click="edit({{ $address->id }})" class="font-semibold text-navy-700 hover:text-gold-600 transition-colors">Edit</button>
                                @unless ($address->is_default)
                                    <button type="button" wire:click="makeDefault({{ $address->id }})" class="font-semibold text-navy-700 hover:text-gold-600 transition-colors">Make Default</button>
                                @endunless
                                <button
                                    type="button"
                                    wire:click="delete({{ $address->id }})"
                                    wire:confirm="Delete this address?"
                                    class="font-semibold text-red-600 hover:text-red-700 transition-colors ml-auto"
                                >
                                    Delete
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
