@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-gold-500 text-start text-base font-medium text-navy-900 bg-gold-50 focus:outline-none focus:text-navy-900 focus:bg-gold-100 focus:border-gold-600 transition duration-150 ease-in-out'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-navy-500 hover:text-navy-900 hover:bg-navy-50 hover:border-navy-200 focus:outline-none focus:text-navy-900 focus:bg-navy-50 focus:border-navy-200 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
