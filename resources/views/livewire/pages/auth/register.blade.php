<?php

use App\Models\User;
use App\Services\CartService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $role = 'customer';
    public string $name = '';
    public string $email = '';
    public string $business_name = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $rules = [
            'role' => ['required', 'in:customer,supplier'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ];

        if ($this->role === 'supplier') {
            $rules['business_name'] = ['required', 'string', 'max:255'];
        }

        $validated = $this->validate($rules);

        // Auth::login() below regenerates the session ID internally
        // (SessionGuard::updateSession()) as soon as it runs, so the guest
        // session ID has to be captured before it runs, not after.
        $guestSessionId = Session::getId();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
        ]);

        if ($this->role === 'supplier') {
            $user->supplierProfile()->create([
                'business_name' => $validated['business_name'],
                'slug' => Str::slug($validated['business_name']).'-'.Str::lower(Str::random(5)),
            ]);
        }

        event(new Registered($user));

        Auth::login($user);

        app(CartService::class)->mergeSessionCartInto($user, $guestSessionId);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div x-data="{ role: @entangle('role') }">
    <h1 class="font-heading text-2xl font-bold text-navy-900">Create your account</h1>
    <p class="mt-1 text-sm text-navy-500">Start sourcing building materials from verified suppliers.</p>

    <!-- Account type tabs -->
    <div class="mt-6 relative grid grid-cols-2 gap-1 rounded-xl bg-navy-50 p-1">
        <div
            class="absolute inset-y-1 w-[calc(50%-0.125rem)] rounded-lg bg-white shadow-sm transition-transform duration-300 ease-out"
            :class="role === 'supplier' ? 'translate-x-[calc(100%+0.25rem)]' : 'translate-x-0'"
        ></div>

        <button
            type="button"
            @click="role = 'customer'"
            class="relative z-10 flex items-center justify-center gap-2 rounded-lg py-2.5 text-sm font-semibold transition-colors duration-200"
            :class="role === 'customer' ? 'text-navy-900' : 'text-navy-400 hover:text-navy-600'"
        >
            <x-icon name="cart" class="w-4 h-4" />
            Customer
        </button>
        <button
            type="button"
            @click="role = 'supplier'"
            class="relative z-10 flex items-center justify-center gap-2 rounded-lg py-2.5 text-sm font-semibold transition-colors duration-200"
            :class="role === 'supplier' ? 'text-navy-900' : 'text-navy-400 hover:text-navy-600'"
        >
            <x-icon name="shield" class="w-4 h-4" />
            Supplier
        </button>
    </div>

    <p
        x-show="role === 'supplier'"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        class="mt-3 text-xs text-navy-500 bg-gold-50 border border-gold-100 rounded-lg px-3 py-2"
        x-cloak
    >
        Supplier accounts go through a short verification step before you can list materials.
    </p>

    <form wire:submit="register" class="mt-6 {{ $errors->any() ? 'animate-shake' : '' }}">
        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Your Name')" />
            <x-text-input wire:model="name" id="name" class="block mt-1 w-full" type="text" name="name" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Business Name (supplier only) -->
        <div
            x-show="role === 'supplier'"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 -translate-y-2 max-h-0"
            x-transition:enter-end="opacity-100 translate-y-0 max-h-24"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 max-h-24"
            x-transition:leave-end="opacity-0 max-h-0"
            class="overflow-hidden mt-4"
            x-cloak
        >
            <x-input-label for="business_name" :value="__('Business Name')" />
            <x-text-input wire:model="business_name" id="business_name" class="block mt-1 w-full" type="text" name="business_name" autocomplete="organization" />
            <x-input-error :messages="$errors->get('business_name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" name="email" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input wire:model="password" id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input wire:model="password_confirmation" id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="mt-6">
            <x-primary-button class="w-full justify-center py-3" wire:loading.attr="disabled" wire:target="register">
                <span wire:loading.remove wire:target="register" x-text="role === 'supplier' ? 'Create Supplier Account' : 'Create Account'"></span>
                <span wire:loading wire:target="register">{{ __('Creating account...') }}</span>
            </x-primary-button>
        </div>
    </form>

    <p class="mt-8 text-center text-sm text-navy-500">
        Already have an account?
        <a href="{{ route('login') }}" wire:navigate class="font-semibold text-navy-700 hover:text-gold-600 transition-colors">Log in</a>
    </p>
</div>
