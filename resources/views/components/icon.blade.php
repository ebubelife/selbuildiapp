@props(['name'])

@php
    $paths = [
        'cement' => '<path d="M7 8h10l1.5 11.2a1 1 0 0 1-1 1.3H6.5a1 1 0 0 1-1-1.3L7 8Z" /><path d="M8.2 8V6.2a3.8 3.8 0 0 1 7.6 0V8" /><line x1="6.3" y1="13" x2="17.7" y2="13" />',
        'steel' => '<line x1="4" y1="21" x2="21" y2="4" /><line x1="4" y1="17" x2="17" y2="4" /><line x1="4" y1="13" x2="13" y2="4" /><line x1="8" y1="18.5" x2="10.5" y2="16" /><line x1="12" y1="14.5" x2="14.5" y2="12" />',
        'roofing' => '<path d="M3 12 12 4l9 8" /><path d="M5.5 10.5V20h13v-9.5" /><line x1="9.5" y1="20" x2="9.5" y2="14" /><line x1="14.5" y1="20" x2="14.5" y2="14" />',
        'tiles' => '<rect x="3.5" y="3.5" width="7.2" height="7.2" rx="1" /><rect x="13.3" y="3.5" width="7.2" height="7.2" rx="1" /><rect x="3.5" y="13.3" width="7.2" height="7.2" rx="1" /><rect x="13.3" y="13.3" width="7.2" height="7.2" rx="1" />',
        'blocks' => '<rect x="2.5" y="13.5" width="7" height="6" /><rect x="10.5" y="13.5" width="7" height="6" /><rect x="18.5" y="13.5" width="3" height="6" /><rect x="2.5" y="7.5" width="3" height="6" /><rect x="6.5" y="7.5" width="7" height="6" /><rect x="14.5" y="7.5" width="7" height="6" />',
        'tools' => '<path d="M14.5 6.2a3.8 3.8 0 0 0-5 5L4 16.7 7.3 20l5.5-5.5a3.8 3.8 0 0 0 5-5l-2.4 2.4-1.9-1.9 2.4-2.4Z" />',
        'truck' => '<path d="M3 7.5h11v8H3z" /><path d="M14 11h3.5l3 3v1.5H14Z" /><circle cx="7.5" cy="18.3" r="1.6" /><circle cx="17" cy="18.3" r="1.6" />',
        'shield' => '<path d="M12 3 5.5 5.8v5.1c0 4.6 3 7.9 6.5 9.1 3.5-1.2 6.5-4.5 6.5-9.1V5.8L12 3Z" /><path d="M9 12.3 11 14.3 15.2 9.8" />',
        'wallet' => '<rect x="3" y="6.5" width="18" height="12.5" rx="2" /><path d="M3 10.5h18" /><circle cx="17" cy="14.7" r="1.2" />',
        'star' => '<polygon points="12,3 14.5,9 21,9.5 16,13.8 17.5,20 12,16.5 6.5,20 8,13.8 3,9.5 9.5,9" />',
        'chevron-right' => '<path d="M9 6l6 6-6 6" />',
        'arrow-right' => '<path d="M4 12h16M14 6l6 6-6 6" />',
        'check' => '<path d="M5 13l4 4L19 7" />',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16" />',
        'close' => '<path d="M6 6l12 12M18 6 6 18" />',
        'globe' => '<circle cx="12" cy="12" r="9" /><path d="M3 12h18" /><path d="M12 3c2.5 2.7 2.5 15.3 0 18" /><path d="M12 3c-2.5 2.7-2.5 15.3 0 18" />',
        'map-pin' => '<path d="M12 21s7-6.5 7-11a7 7 0 1 0-14 0c0 4.5 7 11 7 11Z" /><circle cx="12" cy="10" r="2.4" />',
        'clock' => '<circle cx="12" cy="12" r="9" /><path d="M12 7.5v5l3 2.5" />',
        'cart' => '<circle cx="9" cy="20" r="1.3" /><circle cx="18" cy="20" r="1.3" /><path d="M3 4h2l2.4 11.4a2 2 0 0 0 2 1.6h7.4a2 2 0 0 0 2-1.6L21 8H6.2" />',
    ];

    $inner = $paths[$name] ?? '';
@endphp

<svg {{ $attributes->merge(['class' => 'w-6 h-6', 'viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.6', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
    {!! $inner !!}
</svg>
