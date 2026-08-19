<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        {{-- Every page using this layout (dashboard, profile) sits behind
             auth middleware, so it's never indexable - no per-page toggle
             needed. --}}
        @php($noindex = true)
        @include('partials.head-meta')

        <title>{{ config('app.name', 'Selbuildi') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-neutral-50">
            @if (session('impersonator_id'))
                <div class="bg-gold-500 text-navy-900 text-sm font-medium">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2 flex items-center justify-between gap-4">
                        <span>You're viewing as {{ auth()->user()->name }} ({{ auth()->user()->email }}).</span>
                        <form method="POST" action="{{ route('impersonation.stop') }}">
                            @csrf
                            <button type="submit" class="underline hover:no-underline font-semibold">Return to admin</button>
                        </form>
                    </div>
                </div>
            @endif

            <livewire:layout.navigation />

            <!-- Page Heading -->
            @if (isset($header))
                <header class="bg-white border-b border-navy-100">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endif

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>

        @livewireScripts
    </body>
</html>
