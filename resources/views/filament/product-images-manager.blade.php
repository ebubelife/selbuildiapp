@if ($images->isNotEmpty())
    <div>
        <p class="text-sm font-medium text-gray-950 dark:text-white">Current Photos</p>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Hover a photo and click Remove to delete it - this happens right away, no need to save.</p>

        <div class="mt-2 grid grid-cols-3 sm:grid-cols-5 gap-3">
            @foreach ($images as $image)
                <div wire:key="product-image-{{ $image->id }}" class="group relative aspect-square rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                    <img src="{{ asset('storage/'.$image->path) }}" alt="" class="w-full h-full object-cover">

                    <button
                        type="button"
                        wire:click="removeExistingImage({{ $image->id }})"
                        wire:confirm="Remove this photo?"
                        wire:loading.attr="disabled"
                        wire:target="removeExistingImage({{ $image->id }})"
                        class="absolute inset-0 flex items-center justify-center gap-1.5 bg-black/60 text-white text-xs font-semibold opacity-0 group-hover:opacity-100 focus:opacity-100 transition-opacity"
                    >
                        <svg wire:loading wire:target="removeExistingImage({{ $image->id }})" class="animate-spin w-3.5 h-3.5" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span wire:loading.remove wire:target="removeExistingImage({{ $image->id }})">Remove</span>
                    </button>
                </div>
            @endforeach
        </div>
    </div>
@endif
