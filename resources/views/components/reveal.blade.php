@props(['delay' => 0])

<div
    x-data="{ shown: false }"
    x-intersect.once="setTimeout(() => shown = true, {{ $delay }})"
    :class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'"
    {{ $attributes->merge(['class' => 'transition-all duration-700 ease-out']) }}
>
    {{ $slot }}
</div>
