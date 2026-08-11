<?php

use App\Livewire\Forms\LoginForm;
use App\Services\CartService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        // Auth::attempt() below regenerates the session ID internally
        // (SessionGuard::updateSession()) as soon as it succeeds, so the
        // guest session ID has to be captured before it runs, not after.
        $guestSessionId = Session::getId();

        $this->form->authenticate();

        app(CartService::class)->mergeSessionCartInto(Auth::user(), $guestSessionId);

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div x-data="{ tab: 'customer' }">
    <h1 class="font-heading text-2xl font-bold text-navy-900" x-text="tab === 'supplier' ? 'Supplier login' : 'Welcome back'"></h1>
    <p class="mt-1 text-sm text-navy-500" x-text="tab === 'supplier' ? 'Log in to manage your listings and orders.' : 'Log in to track orders and manage your account.'"></p>

    <!-- Account type tabs (cosmetic - same login form either way) -->
    <div class="mt-6 relative grid grid-cols-2 gap-1 rounded-xl bg-navy-50 p-1">
        <div
            class="absolute inset-y-1 w-[calc(50%-0.125rem)] rounded-lg bg-white shadow-sm transition-transform duration-300 ease-out"
            :class="tab === 'supplier' ? 'translate-x-[calc(100%+0.25rem)]' : 'translate-x-0'"
        ></div>

        <button
            type="button"
            @click="tab = 'customer'"
            class="relative z-10 flex items-center justify-center gap-2 rounded-lg py-2.5 text-sm font-semibold transition-colors duration-200"
            :class="tab === 'customer' ? 'text-navy-900' : 'text-navy-400 hover:text-navy-600'"
        >
            <x-icon name="cart" class="w-4 h-4" />
            Customer
        </button>
        <button
            type="button"
            @click="tab = 'supplier'"
            class="relative z-10 flex items-center justify-center gap-2 rounded-lg py-2.5 text-sm font-semibold transition-colors duration-200"
            :class="tab === 'supplier' ? 'text-navy-900' : 'text-navy-400 hover:text-navy-600'"
        >
            <x-icon name="shield" class="w-4 h-4" />
            Supplier
        </button>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mt-6" :status="session('status')" />

    <form wire:submit="login" class="mt-8 {{ $errors->any() ? 'animate-shake' : '' }}">
        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="form.email" id="email" class="block mt-1 w-full" type="email" name="email" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('form.email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input wire:model="form.password" id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember" class="inline-flex items-center">
                <input wire:model="form.remember" id="remember" type="checkbox" class="rounded border-navy-200 text-gold-500 shadow-sm focus:ring-gold-500" name="remember">
                <span class="ms-2 text-sm text-navy-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-between mt-6 gap-4">
            @if (Route::has('password.request'))
                <a class="text-sm text-navy-500 hover:text-gold-600 transition-colors" href="{{ route('password.request') }}" wire:navigate>
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-primary-button wire:loading.attr="disabled" wire:target="login">
                <span wire:loading.remove wire:target="login">{{ __('Log in') }}</span>
                <span wire:loading wire:target="login">{{ __('Logging in...') }}</span>
            </x-primary-button>
        </div>
    </form>

    <p class="mt-8 text-center text-sm text-navy-500">
        Don't have an account?
        <a href="{{ route('register') }}" wire:navigate class="font-semibold text-navy-700 hover:text-gold-600 transition-colors">Sign up</a>
    </p>
</div>
