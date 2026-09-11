<?php

use App\Models\ActivityLog;
use App\Models\Shipment;
use App\Models\ShipmentUpdateRequest;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Layout('components.layouts.site', ['noindex' => true])] class extends Component
{
    use WithFileUploads, WithPagination;

    public ?int $deliveringShipmentId = null;

    public $proofPhoto;

    public string $proofNote = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->isDeliveryAgent(), 403);
    }

    /**
     * Neither this nor confirmDelivered() ever touches the shipment
     * itself - a delivery agent's status update is only a *request*.
     * An admin has to review and approve it before it becomes what the
     * customer actually sees on their order (see the "Delivery Updates"
     * tab in the admin panel).
     */
    public function requestOutForDelivery(int $shipmentId): void
    {
        $shipment = $this->agentShipment($shipmentId);

        if ($shipment->pendingUpdateRequest()) {
            return;
        }

        $agent = Auth::user()->deliveryAgentProfile;

        ShipmentUpdateRequest::create([
            'shipment_id' => $shipment->id,
            'delivery_agent_id' => $agent->id,
            'requested_status' => 'out_for_delivery',
        ]);

        ActivityLog::log(
            'delivery_update_requested',
            "{$agent->name} requested to mark order {$shipment->order->order_number} as out for delivery.",
            $shipment,
            Auth::user(),
        );
    }

    public function startDelivered(int $shipmentId): void
    {
        $this->deliveringShipmentId = $shipmentId;
        $this->reset(['proofPhoto', 'proofNote']);
    }

    public function cancelDelivered(): void
    {
        $this->deliveringShipmentId = null;
        $this->reset(['proofPhoto', 'proofNote']);
    }

    public function confirmDelivered(): void
    {
        $shipment = $this->agentShipment($this->deliveringShipmentId);

        $this->validate([
            'proofPhoto' => ['required', 'image', 'max:5120'],
            'proofNote' => ['nullable', 'string', 'max:1000'],
        ]);

        $agent = Auth::user()->deliveryAgentProfile;
        $photoPath = $this->proofPhoto->store('delivery-proofs', 'public');

        ShipmentUpdateRequest::create([
            'shipment_id' => $shipment->id,
            'delivery_agent_id' => $agent->id,
            'requested_status' => 'delivered',
            'note' => $this->proofNote ?: null,
            'photo_path' => $photoPath,
        ]);

        ActivityLog::log(
            'delivery_update_requested',
            "{$agent->name} requested to mark order {$shipment->order->order_number} as delivered, with photo proof.",
            $shipment,
            Auth::user(),
        );

        $this->deliveringShipmentId = null;
        $this->reset(['proofPhoto', 'proofNote']);
    }

    private function agentShipment(int $shipmentId): Shipment
    {
        $agent = Auth::user()->deliveryAgentProfile;

        return Shipment::where('delivery_agent_id', $agent->id)->findOrFail($shipmentId);
    }

    public function with(): array
    {
        $agent = Auth::user()->deliveryAgentProfile;

        $shipments = $agent
            ? Shipment::where('delivery_agent_id', $agent->id)
                ->with(['order.user', 'supplierProfile'])
                ->latest('created_at')
                ->paginate(10)
            : null;

        return ['agent' => $agent, 'shipments' => $shipments];
    }
}; ?>

<div>
    <section class="pt-32 pb-10 bg-gradient-to-br from-navy-900 via-navy-800 to-navy-700">
        <div class="max-w-5xl mx-auto px-6 lg:px-8">
            <h1 class="font-heading text-2xl sm:text-3xl font-bold text-white">My Deliveries</h1>
            <p class="mt-1 text-navy-200 text-sm">Orders you've been matched to, and their delivery status.</p>
        </div>
    </section>

    <section class="py-12 bg-neutral-50 min-h-[50vh]">
        <div class="max-w-5xl mx-auto px-6 lg:px-8">
            @if (! $agent?->is_active)
                <div class="bg-gold-50 border border-gold-100 rounded-2xl p-6 mb-6 flex items-start gap-4">
                    <span class="flex items-center justify-center w-10 h-10 rounded-full bg-gold-500 text-navy-900 shrink-0">
                        <x-icon name="shield" class="w-5 h-5" />
                    </span>
                    <div>
                        <h3 class="font-heading font-semibold text-navy-900">Approval pending</h3>
                        <p class="mt-1 text-sm text-navy-600 leading-relaxed max-w-2xl">
                            Your delivery agent account is under review. You'll be able to see assigned deliveries here once our team approves you.
                        </p>
                    </div>
                </div>
            @endif

            @if (! $shipments || $shipments->isEmpty())
                <div class="bg-white rounded-2xl border border-navy-100 p-12 text-center">
                    <span class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-navy-50 text-navy-300 mb-4">
                        <x-icon name="truck" class="w-7 h-7" />
                    </span>
                    <h3 class="font-heading text-lg font-semibold text-navy-900">No deliveries yet</h3>
                    <p class="mt-2 text-sm text-navy-500 max-w-sm mx-auto">Orders assigned to you by admin will show up here.</p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach ($shipments as $shipment)
                        @php $pending = $shipment->pendingUpdateRequest(); @endphp
                        <div wire:key="shipment-{{ $shipment->id }}" class="bg-white rounded-2xl border border-navy-100 p-5">
                            <div class="flex items-center justify-between flex-wrap gap-3 pb-4 border-b border-navy-100">
                                <div>
                                    <p class="font-heading font-bold text-navy-900 text-sm">{{ $shipment->order->order_number }}</p>
                                    <p class="text-xs text-navy-400 mt-0.5">{{ $shipment->order->user->name }} &middot; {{ $shipment->supplierProfile?->business_name }}</p>
                                </div>
                                <span @class([
                                    'text-xs font-semibold px-3 py-1 rounded-full',
                                    'bg-gold-100 text-gold-700' => in_array($shipment->status, ['pending', 'confirmed', 'processing']),
                                    'bg-blue-100 text-blue-700' => in_array($shipment->status, ['shipped', 'out_for_delivery']),
                                    'bg-green-100 text-green-700' => $shipment->status === 'delivered',
                                    'bg-red-100 text-red-700' => in_array($shipment->status, ['cancelled', 'refunded']),
                                ])>
                                    {{ $shipment->statusLabel() }}
                                </span>
                            </div>

                            <div class="mt-4 flex items-center justify-between flex-wrap gap-3">
                                @if ($pending)
                                    <span class="text-xs font-semibold bg-navy-100 text-navy-600 px-3 py-1.5 rounded-full flex items-center gap-1.5">
                                        <x-icon name="clock" class="w-3.5 h-3.5" />
                                        {{ $pending->requestedStatusLabel() }} update awaiting admin review
                                    </span>
                                @elseif ($deliveringShipmentId === $shipment->id)
                                    <form wire:submit="confirmDelivered" class="w-full space-y-3">
                                        <div>
                                            <x-input-label for="proofPhoto-{{ $shipment->id }}" value="Proof photo" />
                                            <input wire:model="proofPhoto" id="proofPhoto-{{ $shipment->id }}" type="file" accept="image/*" class="mt-1 block w-full text-sm text-navy-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-navy-50 file:text-navy-700 file:text-sm file:font-semibold hover:file:bg-navy-100" />
                                            <p class="mt-1 text-xs text-navy-400" wire:loading wire:target="proofPhoto">Uploading&hellip;</p>
                                            <x-input-error :messages="$errors->get('proofPhoto')" class="mt-1" />
                                        </div>
                                        <div>
                                            <x-input-label for="proofNote-{{ $shipment->id }}" value="Note (optional)" />
                                            <textarea wire:model="proofNote" id="proofNote-{{ $shipment->id }}" rows="2" class="mt-1 block w-full rounded-lg border-navy-200 focus:border-gold-500 focus:ring-gold-500 text-sm" placeholder="E.g. Delivered to the site foreman."></textarea>
                                        </div>
                                        <div class="flex gap-2">
                                            <x-primary-button type="submit" wire:loading.attr="disabled" wire:target="confirmDelivered,proofPhoto">Submit for Review</x-primary-button>
                                            <x-secondary-button type="button" wire:click="cancelDelivered">Cancel</x-secondary-button>
                                        </div>
                                    </form>
                                @elseif (! in_array($shipment->status, ['delivered', 'cancelled', 'refunded'], true) && $agent?->is_active)
                                    <div class="flex gap-2">
                                        @if ($shipment->status !== 'out_for_delivery')
                                            <x-secondary-button wire:click="requestOutForDelivery({{ $shipment->id }})" wire:loading.attr="disabled">
                                                Mark Out for Delivery
                                            </x-secondary-button>
                                        @endif
                                        <x-primary-button wire:click="startDelivered({{ $shipment->id }})">
                                            Mark Delivered
                                        </x-primary-button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-8">
                    {{ $shipments->links() }}
                </div>
            @endif
        </div>
    </section>
</div>
