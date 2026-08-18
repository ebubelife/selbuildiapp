<?php

use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.site', ['noindex' => true])] class extends Component
{
    public Project $project;

    public function mount(Project $project): void
    {
        abort_unless($project->user_id === Auth::id(), 403);

        $this->project = $project->load('orders');
    }

    public function with(): array
    {
        $spent = $this->project->orders->sum('total');

        return [
            'spent' => $spent,
            'remaining' => $this->project->budget ? max(0, $this->project->budget - $spent) : null,
        ];
    }
}; ?>

<div>
    <section class="pt-32 pb-10 bg-gradient-to-br from-navy-900 via-navy-800 to-navy-700">
        <div class="max-w-4xl mx-auto px-6 lg:px-8">
            <a href="{{ route('projects.index') }}" wire:navigate class="text-navy-300 hover:text-white text-sm flex items-center gap-1 mb-4 transition-colors">
                &larr; My Projects
            </a>
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div>
                    <h1 class="font-heading text-2xl sm:text-3xl font-bold text-white">{{ $project->name }}</h1>
                    @if ($project->start_date)
                        <p class="mt-1 text-navy-200 text-sm">Started {{ $project->start_date->format('M j, Y') }}</p>
                    @endif
                </div>
                <span @class([
                    'text-xs font-semibold px-3 py-1 rounded-full',
                    'bg-green-100 text-green-700' => $project->status === 'active',
                    'bg-navy-100 text-navy-600' => $project->status !== 'active',
                ])>
                    {{ ucfirst($project->status) }}
                </span>
            </div>
        </div>
    </section>

    <section class="py-12 bg-neutral-50 min-h-[50vh]">
        <div class="max-w-4xl mx-auto px-6 lg:px-8">
            <div class="grid sm:grid-cols-3 gap-4 mb-8">
                <div class="bg-white rounded-2xl border border-navy-100 p-5">
                    <p class="text-xs font-semibold text-navy-400 uppercase tracking-wide">Total Spent</p>
                    <p class="mt-1 font-heading font-bold text-xl text-navy-900">{{ number_format($spent) }} XAF</p>
                </div>
                <div class="bg-white rounded-2xl border border-navy-100 p-5">
                    <p class="text-xs font-semibold text-navy-400 uppercase tracking-wide">Budget</p>
                    <p class="mt-1 font-heading font-bold text-xl text-navy-900">{{ $project->budget ? number_format($project->budget).' XAF' : 'Not set' }}</p>
                </div>
                <div class="bg-white rounded-2xl border border-navy-100 p-5">
                    <p class="text-xs font-semibold text-navy-400 uppercase tracking-wide">Orders</p>
                    <p class="mt-1 font-heading font-bold text-xl text-navy-900">{{ $project->orders->count() }}</p>
                </div>
            </div>

            @if ($project->budget)
                <div class="bg-white rounded-2xl border border-navy-100 p-6 mb-8">
                    <div class="flex justify-between text-sm text-navy-600 mb-2">
                        <span>{{ number_format($spent) }} XAF spent</span>
                        <span>{{ $remaining !== null ? number_format($remaining).' XAF remaining' : '' }}</span>
                    </div>
                    <div class="h-2 bg-navy-100 rounded-full overflow-hidden">
                        <div
                            @class([
                                'h-full rounded-full transition-all duration-300',
                                'bg-red-500' => $spent > $project->budget,
                                'bg-gold-500' => $spent <= $project->budget,
                            ])
                            style="width: {{ min(100, $project->budget > 0 ? ($spent / $project->budget) * 100 : 0) }}%"
                        ></div>
                    </div>
                    @if ($spent > $project->budget)
                        <p class="mt-2 text-xs text-red-600">{{ number_format($spent - $project->budget) }} XAF over budget.</p>
                    @endif
                </div>
            @endif

            <div class="bg-white rounded-2xl border border-navy-100 p-6">
                <h2 class="font-heading font-bold text-navy-900">Orders on this project</h2>

                @if ($project->orders->isEmpty())
                    <p class="mt-4 text-sm text-navy-500">No orders linked to this project yet. Choose it at checkout when you order materials for this build.</p>
                @else
                    <ul class="mt-4 divide-y divide-navy-100">
                        @foreach ($project->orders->sortByDesc('placed_at') as $order)
                            <li>
                                <a href="{{ route('orders.show', $order) }}" wire:navigate class="flex items-center justify-between gap-4 py-4 hover:bg-navy-50 -mx-2 px-2 rounded-lg transition-colors">
                                    <div>
                                        <p class="font-semibold text-navy-900 text-sm">{{ $order->order_number }}</p>
                                        <p class="text-xs text-navy-400 mt-0.5">{{ $order->placed_at->format('M j, Y') }}</p>
                                    </div>
                                    <div class="flex items-center gap-4">
                                        <span class="text-xs font-semibold bg-gold-100 text-gold-700 px-3 py-1 rounded-full">{{ $order->statusLabel() }}</span>
                                        <span class="font-semibold text-navy-900 text-sm">{{ $order->formattedTotal() }}</span>
                                    </div>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </section>
</div>
