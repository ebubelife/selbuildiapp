@props(['variant' => 'mark', 'dark' => false])

@php
    $file = match ($variant) {
        'full' => $dark ? 'logo-full-dark.png' : 'logo-full.png',
        default => $dark ? 'logo-mark-dark.png' : 'logo-mark.png',
    };
@endphp

<img
    src="{{ asset('images/' . $file) }}"
    alt="Selbuildi"
    {{ $attributes }}
>
