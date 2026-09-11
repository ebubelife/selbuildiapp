@props(['xaf', 'showXafHint' => false])

@php
    $currency = app(\App\Services\CurrencyContext::class)->current();
@endphp

<span {{ $attributes }}>{{ $currency->format((int) $xaf) }}</span>
@if ($showXafHint && $currency->code !== 'XAF')
    <span class="text-xs text-navy-400">(≈ {{ number_format($xaf) }} XAF)</span>
@endif
