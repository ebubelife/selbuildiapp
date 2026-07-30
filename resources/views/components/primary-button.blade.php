<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-gold-500 border border-transparent rounded-lg font-heading font-semibold text-sm text-navy-900 shadow-sm hover:bg-gold-600 hover:-translate-y-0.5 hover:shadow-md active:translate-y-0 focus:outline-none focus:ring-2 focus:ring-gold-500 focus:ring-offset-2 transition-all duration-150 disabled:opacity-60 disabled:pointer-events-none']) }}>
    {{ $slot }}
</button>
