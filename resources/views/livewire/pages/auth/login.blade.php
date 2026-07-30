<?php

use App\Livewire\Forms\LoginForm;
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

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <h1 class="font-heading text-2xl font-bold text-navy-900">Welcome back</h1>
    <p class="mt-1 text-sm text-navy-500">Log in to track orders and manage your account.</p>

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
