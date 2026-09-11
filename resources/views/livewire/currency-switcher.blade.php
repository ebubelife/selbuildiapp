<?php

use App\Models\Currency;
use App\Services\CurrencyContext;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public bool $open = false;

    public function switchCurrency(string $code, CurrencyContext $currencyContext): void
    {
        $currencyContext->switchTo($code, Auth::user());
        $this->open = false;

        // A full reload rather than a Livewire re-render - dozens of
        // independent components across the page (product cards, cart,
        // etc.) each resolve the current currency themselves, and there's
        // no clean way to tell every one of them to re-render from here.
        $this->js('window.location.reload()');
    }

    public function with(): array
    {
        return [
            'currencies' => Currency::where('is_supported', true)->orderBy('sort_order')->orderBy('code')->get(),
            'current' => app(CurrencyContext::class)->current(),
        ];
    }
}; ?>

<div x-data="{ open: @entangle('open') }" class="relative" @click.outside="open = false">
    <button
        type="button"
        @click="open = !open"
        x-bind:class="(typeof scrolled === 'undefined' || scrolled) ? 'text-navy-700 hover:bg-navy-50' : 'text-white/90 hover:bg-white/10'"
        class="flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-sm font-semibold transition-colors"
    >
        {{ $current->code }}
        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-lg border border-navy-100 py-1.5 z-50"
    >
        @foreach ($currencies as $currency)
            <button
                type="button"
                wire:click="switchCurrency('{{ $currency->code }}')"
                @class([
                    'w-full text-left px-4 py-2 text-sm hover:bg-navy-50 transition-colors flex items-center justify-between',
                    'font-semibold text-navy-900' => $currency->code === $current->code,
                    'text-navy-600' => $currency->code !== $current->code,
                ])
            >
                <span>{{ $currency->code }} <span class="text-navy-400 font-normal">{{ $currency->country_label }}</span></span>
                @if ($currency->code === $current->code)
                    <x-icon name="check" class="w-3.5 h-3.5 text-gold-600" />
                @endif
            </button>
        @endforeach
    </div>
</div>
