<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>Down for Maintenance — Selbuildi</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-navy-900">
        <div class="min-h-screen flex flex-col">
            <div class="absolute inset-0 opacity-[0.06] pointer-events-none" style="background-image: radial-gradient(circle at 2px 2px, white 1px, transparent 0); background-size: 28px 28px;"></div>

            <header class="relative z-10 px-6 lg:px-8 pt-8">
                <x-application-logo variant="full" dark class="h-9 w-auto" />
            </header>

            <main class="relative z-10 flex-1 flex items-center justify-center px-6 py-16">
                <div class="max-w-lg w-full text-center">
                    <div class="mx-auto flex items-center justify-center w-20 h-20 rounded-2xl bg-white/5 border border-white/10 text-gold-500">
                        <x-icon name="tools" class="w-9 h-9" stroke-width="1.4" />
                    </div>

                    <p class="mt-8 font-heading text-sm font-semibold tracking-widest text-gold-500 uppercase">Scheduled Maintenance</p>
                    <h1 class="mt-3 font-heading text-3xl sm:text-4xl font-bold text-white">We'll be right back</h1>
                    <p class="mt-4 text-navy-200 leading-relaxed">
                        Selbuildi is briefly offline while we make some improvements. Your orders, credit, and account data are safe — this won't take long.
                    </p>

                    <div
                        x-data
                        x-init="setTimeout(() => window.location.reload(), 30000)"
                        class="mt-10 flex items-center justify-center gap-2 text-sm text-navy-300"
                    >
                        <svg class="w-4 h-4 animate-spin text-gold-500" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5"></circle>
                            <path class="opacity-90" d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"></path>
                        </svg>
                        This page will refresh automatically
                    </div>
                </div>
            </main>

            <footer class="relative z-10 pb-8 text-center">
                <p class="text-xs text-navy-400">&copy; {{ date('Y') }} Selbuildi. Building the infrastructure of trust.</p>
            </footer>
        </div>
    </body>
</html>
