@if ($images->isNotEmpty())
    {{--
        Deliberately plain, self-contained CSS here rather than Tailwind
        utility classes - this view is rendered inside the Filament admin
        panel, which ships its own separately pre-built CSS bundle that
        never scans this project's app-level Blade views. Utility classes
        here would silently do nothing (which is exactly what happened:
        the grid/aspect-ratio/object-fit classes were never applied, so
        the photo rendered at its natural full size).
    --}}
    <style>
        .sb-photo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(84px, 1fr)); gap: 0.625rem; margin-top: 0.5rem; max-width: 32rem; }
        .sb-photo-tile { position: relative; aspect-ratio: 1 / 1; border-radius: 0.5rem; overflow: hidden; border: 1px solid rgba(0, 0, 0, 0.1); background: #f3f4f6; }
        .sb-photo-tile img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .sb-photo-remove { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(15, 23, 42, 0.65); color: #fff; font-size: 0.75rem; font-weight: 600; opacity: 0; transition: opacity 0.15s ease; border: 0; margin: 0; padding: 0; cursor: pointer; }
        .sb-photo-tile:hover .sb-photo-remove,
        .sb-photo-remove:focus-visible { opacity: 1; }
    </style>

    <div style="font-size: 0.875rem; font-weight: 500;">Current Photos</div>
    <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.125rem;">Hover a photo and click Remove to delete it - this happens right away, no need to save.</div>

    <div class="sb-photo-grid">
        @foreach ($images as $image)
            <div wire:key="product-image-{{ $image->id }}" class="sb-photo-tile">
                <img src="{{ asset('storage/'.$image->path) }}" alt="">
                <button
                    type="button"
                    class="sb-photo-remove"
                    wire:click="removeExistingImage({{ $image->id }})"
                    wire:confirm="Remove this photo?"
                    wire:loading.attr="disabled"
                    wire:target="removeExistingImage({{ $image->id }})"
                >
                    <span wire:loading.remove wire:target="removeExistingImage({{ $image->id }})">Remove</span>
                    <span wire:loading wire:target="removeExistingImage({{ $image->id }})">&hellip;</span>
                </button>
            </div>
        @endforeach
    </div>
@endif
