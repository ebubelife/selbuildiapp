<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head-meta')

        <title>{{ $title ?? 'Selbuildi — Building the Infrastructure of Trust' }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="font-sans antialiased text-navy-900 bg-white">
        @include('partials.site-nav')

        <main>
            {{ $slot }}
        </main>

        @include('partials.site-footer')

        @livewireScripts
    </body>
</html>
