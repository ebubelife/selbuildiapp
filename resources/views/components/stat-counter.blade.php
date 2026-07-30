@props(['target', 'suffix' => '', 'label'])

<div
    x-data="{
        value: 0,
        target: {{ (int) $target }},
        animate() {
            const duration = 1400;
            const start = performance.now();
            const step = (now) => {
                const progress = Math.min((now - start) / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                this.value = Math.floor(eased * this.target);
                if (progress < 1) requestAnimationFrame(step);
            };
            requestAnimationFrame(step);
        }
    }"
    x-intersect.once="animate()"
    {{ $attributes->merge(['class' => 'text-center']) }}
>
    <div class="font-heading text-4xl md:text-5xl font-bold text-white">
        <span x-text="value.toLocaleString()"></span>{{ $suffix }}
    </div>
    <div class="mt-2 text-sm text-navy-200 tracking-wide uppercase">{{ $label }}</div>
</div>
