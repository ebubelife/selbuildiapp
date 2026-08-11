<button
    type="button"
    wire:click="add"
    wire:loading.attr="disabled"
    wire:target="add"
    @class([
        'flex items-center justify-center w-9 h-9 rounded-full transition-all duration-150 shrink-0',
        'bg-green-500 text-white' => $justAdded,
        'bg-gold-500 text-navy-900 hover:bg-gold-600 hover:scale-110' => ! $justAdded,
    ])
    aria-label="Add {{ $product->name }} to cart"
>
    <x-icon :name="$justAdded ? 'check' : 'cart'" class="w-4 h-4" />
</button>
