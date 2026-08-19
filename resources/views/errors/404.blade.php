<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>Page Not Found — Selbuildi</title>

        @vite(['resources/css/app.css'])
    </head>
    <body class="font-sans antialiased bg-navy-900">
        <div class="min-h-screen flex flex-col">
            <div class="absolute inset-0 opacity-[0.06] pointer-events-none" style="background-image: radial-gradient(circle at 2px 2px, white 1px, transparent 0); background-size: 28px 28px;"></div>

            <header class="relative z-10 px-6 lg:px-8 pt-8">
                <a href="{{ url('/') }}">
                    <x-application-logo variant="full" dark class="h-9 w-auto" />
                </a>
            </header>

            <main class="relative z-10 flex-1 flex items-center justify-center px-6 py-16">
                <div class="max-w-lg w-full text-center">
                    <div class="mx-auto flex items-center justify-center w-20 h-20 rounded-2xl bg-white/5 border border-white/10 text-gold-500">
                        <x-icon name="map-pin" class="w-9 h-9" stroke-width="1.4" />
                    </div>

                    <p class="mt-8 font-heading text-sm font-semibold tracking-widest text-gold-500 uppercase">Error 404</p>
                    <h1 class="mt-3 font-heading text-3xl sm:text-4xl font-bold text-white">This site couldn't be found</h1>
                    <p class="mt-4 text-navy-200 leading-relaxed">
                        The page you're looking for may have been moved, renamed, or never existed. Let's get you back to sourcing materials.
                    </p>

                    <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-3">
                        <a
                            href="{{ url('/') }}"
                            class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-gold-500 border border-transparent rounded-lg font-heading font-semibold text-sm text-navy-900 shadow-sm hover:bg-gold-600 hover:-translate-y-0.5 hover:shadow-md active:translate-y-0 transition-all duration-150 w-full sm:w-auto"
                        >
                            <x-icon name="arrow-right" class="w-4 h-4 rotate-180" />
                            Back to home
                        </a>
                        <a
                            href="{{ url('/shop') }}"
                            class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-transparent border border-white/20 rounded-lg font-heading font-semibold text-sm text-white hover:bg-white/5 hover:-translate-y-0.5 transition-all duration-150 w-full sm:w-auto"
                        >
                            <x-icon name="cart" class="w-4 h-4" />
                            Browse the shop
                        </a>
                    </div>
                </div>
            </main>

            <footer class="relative z-10 pb-8 text-center">
                <p class="text-xs text-navy-400">&copy; {{ date('Y') }} Selbuildi. Building the infrastructure of trust.</p>
            </footer>
        </div>
    </body>
</html>
