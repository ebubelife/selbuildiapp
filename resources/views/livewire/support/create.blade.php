<?php

use App\Models\Product;
use App\Models\SupportTicket;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.site', ['noindex' => true])] class extends Component
{
    use WithFileUploads;

    public ?int $productId = null;

    public string $type = 'general';
    public string $subject = '';
    public string $body = '';
    public $attachment;

    public bool $submitted = false;

    public function mount(): void
    {
        // 'product' arrives as a query string (?product=123) from the
        // "Request a Quote" button on a product page, not a route
        // parameter - Livewire's mount() only auto-binds the latter.
        $productModel = Product::find(request()->query('product'));

        if ($productModel) {
            $this->productId = $productModel->id;
            $this->type = 'quote_request';
            $this->subject = "Quote request: {$productModel->name}";
        }
    }

    public function submit(): void
    {
        $validated = $this->validate([
            'type' => ['required', 'in:general,procurement_request,quote_request,complaint,other'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $attachmentPath = $this->attachment?->store('support-attachments', 'local');

        SupportTicket::create([
            'user_id' => Auth::id(),
            'type' => $validated['type'],
            'product_id' => $this->productId,
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'attachment_path' => $attachmentPath,
        ]);

        $this->submitted = true;
        $this->reset(['subject', 'body', 'attachment']);
    }

    public function with(): array
    {
        return [
            'product' => $this->productId ? Product::find($this->productId) : null,
        ];
    }
}; ?>

<div>
    <section class="pt-32 pb-16 bg-gradient-to-br from-navy-900 via-navy-800 to-navy-700">
        <div class="max-w-2xl mx-auto px-6 lg:px-8 text-center">
            <span class="text-sm font-semibold text-gold-500 uppercase tracking-wide">Get in Touch</span>
            <h1 class="mt-3 font-heading text-3xl sm:text-4xl font-bold text-white">
                {{ $product ? 'Request a Quote' : "Can't find what you need?" }}
            </h1>
            <p class="mt-3 text-navy-200">
                {{ $product ? "Tell us your quantity and requirements for {$product->name}, and we'll get back to you with a quote." : "Tell us what you're looking for and we'll help you source it — or reach out with any other question." }}
            </p>
        </div>
    </section>

    <section class="py-12 bg-neutral-50">
        <div class="max-w-2xl mx-auto px-6 lg:px-8">
            <div class="bg-white rounded-2xl border border-navy-100 p-6 sm:p-8">
                @if ($submitted)
                    <div class="text-center py-8">
                        <span class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-green-100 text-green-600 mx-auto">
                            <x-icon name="check" class="w-6 h-6" stroke-width="2.5" />
                        </span>
                        <h2 class="mt-4 font-heading text-lg font-semibold text-navy-900">Message sent</h2>
                        <p class="mt-2 text-sm text-navy-500">Our team will review this and get back to you by email.</p>
                        <a href="{{ route('shop.index') }}" wire:navigate>
                            <x-primary-button class="mt-6">Back to Shop</x-primary-button>
                        </a>
                    </div>
                @else
                    <form wire:submit="submit" class="space-y-5">
                        @if ($product)
                            <div class="p-4 rounded-xl bg-navy-50 border border-navy-100 flex items-center gap-3">
                                <x-icon :name="$product->category->icon ?? 'cart'" class="w-8 h-8 text-navy-400 shrink-0" />
                                <div>
                                    <p class="text-xs font-semibold text-navy-500 uppercase tracking-wide">Requesting a quote for</p>
                                    <p class="font-semibold text-navy-900 text-sm">{{ $product->name }}</p>
                                </div>
                            </div>
                        @else
                            <div>
                                <x-input-label for="type" value="What's this about?" />
                                <select wire:model="type" id="type" class="mt-1 block w-full rounded-lg border-navy-200 focus:border-gold-500 focus:ring-gold-500 text-sm">
                                    <option value="general">General enquiry</option>
                                    <option value="procurement_request">Can't find what I need — help me source it</option>
                                    <option value="complaint">Complaint</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        @endif

                        <div>
                            <x-input-label for="subject" value="Subject" />
                            <x-text-input wire:model="subject" id="subject" class="block mt-1 w-full" />
                            <x-input-error :messages="$errors->get('subject')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label for="body" :value="$type === 'procurement_request' ? 'Tell us the material, quantity, and delivery location' : 'Message'" />
                            <textarea
                                wire:model="body"
                                id="body"
                                rows="6"
                                class="mt-1 block w-full rounded-lg border-navy-200 focus:border-gold-500 focus:ring-gold-500 text-sm"
                                placeholder="{{ $type === 'procurement_request' || $product ? 'E.g. 200 bags of 42.5R cement, needed in Douala by next Friday...' : '' }}"
                            ></textarea>
                            <x-input-error :messages="$errors->get('body')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label for="attachment" value="Attach an image or spec sheet (optional)" />
                            <input wire:model="attachment" id="attachment" type="file" accept="image/*,.pdf" class="mt-1 block w-full text-sm text-navy-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-navy-50 file:text-navy-700 file:text-sm file:font-semibold hover:file:bg-navy-100">
                            <p class="mt-1 text-xs text-navy-400" wire:loading wire:target="attachment">Uploading&hellip;</p>
                            <x-input-error :messages="$errors->get('attachment')" class="mt-1" />
                        </div>

                        <x-primary-button type="submit" class="w-full justify-center py-3" wire:loading.attr="disabled" wire:target="submit">
                            <span wire:loading.remove wire:target="submit">Send</span>
                            <span wire:loading wire:target="submit">Sending...</span>
                        </x-primary-button>
                    </form>
                @endif
            </div>
        </div>
    </section>
</div>
