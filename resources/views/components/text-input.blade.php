@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-navy-200 focus:border-gold-500 focus:ring-gold-500 rounded-lg shadow-sm transition-colors duration-150']) }}>
