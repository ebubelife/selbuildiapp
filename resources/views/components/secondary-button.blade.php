<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-transparent border-2 border-navy-700 rounded-lg font-heading font-semibold text-sm text-navy-700 hover:bg-navy-700 hover:text-white focus:outline-none focus:ring-2 focus:ring-navy-700 focus:ring-offset-2 disabled:opacity-40 transition-all duration-150']) }}>
    {{ $slot }}
</button>
