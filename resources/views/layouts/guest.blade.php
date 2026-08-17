<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head-meta')

        <title>{{ config('app.name', 'Selbuildi') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen flex">
            {{-- Brand panel --}}
            <div class="hidden lg:flex lg:w-1/2 relative overflow-hidden bg-gradient-to-br from-navy-900 via-navy-800 to-navy-700">
                <div class="absolute inset-0 opacity-[0.07]" style="background-image: radial-gradient(circle at 2px 2px, white 1px, transparent 0); background-size: 28px 28px;"></div>

                <div class="relative z-10 flex flex-col justify-between w-full p-12 xl:p-16">
                    <a href="{{ route('home') }}" wire:navigate>
                        <x-application-logo variant="full" dark class="h-11 w-auto" />
                    </a>

                    <div class="max-w-md">
                        <h1 class="font-heading text-3xl xl:text-4xl font-bold text-white leading-tight">
                            Building the infrastructure of trust.
                        </h1>
                        <p class="mt-4 text-navy-200 leading-relaxed">
                            Buy building materials from verified suppliers, track every delivery, and unlock procurement credit as your history grows.
                        </p>
                    </div>

                    <div
                        x-data="{
                            quotes: [
                                'Ordered cement from Paris, delivered to Douala in 4 days — I watched every step.',
                                'My Procurement Trust Score got me materials on credit for my second site.',
                                'No more sending money home and hoping it was spent right.',
                            ],
                            i: 0
                        }"
                        x-init="setInterval(() => i = (i + 1) % quotes.length, 5000)"
                        class="relative h-20"
                    >
                        <template x-for="(quote, index) in quotes" :key="index">
                            <p
                                x-show="i === index"
                                x-transition:enter="transition ease-out duration-500"
                                x-transition:enter-start="opacity-0 translate-y-2"
                                x-transition:enter-end="opacity-100 translate-y-0"
                                class="absolute inset-0 text-navy-100 italic leading-relaxed"
                                x-text="'“' + quote + '”'"
                            ></p>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Form panel --}}
            <div class="flex-1 flex flex-col items-center justify-center px-6 py-12 bg-neutral-50">
                <a href="{{ route('home') }}" wire:navigate class="lg:hidden mb-8">
                    <x-application-logo variant="full" class="h-10 w-auto" />
                </a>

                <div class="w-full {{ $maxWidth ?? 'sm:max-w-md' }}">
                    <div class="bg-white px-6 py-8 sm:px-10 sm:py-10 shadow-brand rounded-2xl border border-navy-100">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>

        @livewireScripts
    </body>
</html>
