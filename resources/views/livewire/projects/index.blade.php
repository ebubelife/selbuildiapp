<?php

use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.site')] class extends Component
{
    public bool $showCreateForm = false;

    public string $name = '';
    public ?int $budget = null;
    public ?string $start_date = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->isContractor(), 403);
    }

    public function createProject(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'budget' => ['nullable', 'integer', 'min:0'],
            'start_date' => ['nullable', 'date'],
        ]);

        Auth::user()->projects()->create($validated);

        $this->reset(['name', 'budget', 'start_date', 'showCreateForm']);
    }

    public function with(): array
    {
        return [
            'projects' => Auth::user()->projects()->withCount('orders')->withSum('orders', 'total')->latest()->get(),
        ];
    }
}; ?>

<div>
    <section class="pt-32 pb-10 bg-gradient-to-br from-navy-900 via-navy-800 to-navy-700">
        <div class="max-w-4xl mx-auto px-6 lg:px-8 flex items-center justify-between flex-wrap gap-4">
            <div>
                <h1 class="font-heading text-2xl sm:text-3xl font-bold text-white">My Projects</h1>
                <p class="mt-1 text-navy-200 text-sm">Group your orders by build so you can track spend against budget.</p>
            </div>
            <x-primary-button wire:click="$toggle('showCreateForm')">
                {{ $showCreateForm ? 'Cancel' : '+ New Project' }}
            </x-primary-button>
        </div>
    </section>

    <section class="py-12 bg-neutral-50 min-h-[50vh]">
        <div class="max-w-4xl mx-auto px-6 lg:px-8">
            <div
                x-show="$wire.showCreateForm"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 -translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-cloak
                class="bg-white rounded-2xl border border-navy-100 p-6 mb-6"
            >
                <h2 class="font-heading font-bold text-lg text-navy-900">New Project</h2>
                <form wire:submit="createProject" class="mt-4 space-y-4">
                    <div>
                        <x-input-label for="name" value="Project Name" />
                        <x-text-input wire:model="name" id="name" class="block mt-1 w-full" placeholder="e.g. Bafoussam Family House" />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="budget" value="Budget (XAF, optional)" />
                            <x-text-input wire:model="budget" id="budget" type="number" min="0" class="block mt-1 w-full" />
                            <x-input-error :messages="$errors->get('budget')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="start_date" value="Start Date (optional)" />
                            <x-text-input wire:model="start_date" id="start_date" type="date" class="block mt-1 w-full" />
                            <x-input-error :messages="$errors->get('start_date')" class="mt-1" />
                        </div>
                    </div>

                    <x-primary-button type="submit">Create Project</x-primary-button>
                </form>
            </div>

            @if ($projects->isEmpty())
                <div class="bg-white rounded-2xl border border-navy-100 p-12 text-center">
                    <span class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-navy-50 text-navy-300 mb-4">
                        <x-icon name="tools" class="w-7 h-7" />
                    </span>
                    <h3 class="font-heading text-lg font-semibold text-navy-900">No projects yet</h3>
                    <p class="mt-2 text-sm text-navy-500 max-w-sm mx-auto">Create a project to start grouping orders and tracking spend for a specific build.</p>
                </div>
            @else
                <div class="grid sm:grid-cols-2 gap-4">
                    @foreach ($projects as $project)
                        <a
                            href="{{ route('projects.show', $project) }}"
                            wire:navigate
                            wire:key="project-{{ $project->id }}"
                            class="block bg-white rounded-2xl border border-navy-100 p-5 hover:border-gold-300 hover:shadow-brand transition-all duration-200"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-heading font-bold text-navy-900">{{ $project->name }}</p>
                                    <p class="text-xs text-navy-400 mt-0.5">{{ $project->orders_count }} {{ Str::plural('order', $project->orders_count) }}</p>
                                </div>
                                <span @class([
                                    'text-xs font-semibold px-3 py-1 rounded-full shrink-0',
                                    'bg-green-100 text-green-700' => $project->status === 'active',
                                    'bg-navy-100 text-navy-600' => $project->status !== 'active',
                                ])>
                                    {{ ucfirst($project->status) }}
                                </span>
                            </div>

                            @if ($project->budget)
                                @php $spent = $project->orders_sum_total ?? 0; @endphp
                                <div class="mt-4">
                                    <div class="flex justify-between text-xs text-navy-500 mb-1">
                                        <span>{{ number_format($spent) }} spent</span>
                                        <span>{{ number_format($project->budget) }} XAF budget</span>
                                    </div>
                                    <div class="h-1.5 bg-navy-100 rounded-full overflow-hidden">
                                        <div
                                            @class([
                                                'h-full rounded-full transition-all duration-300',
                                                'bg-red-500' => $spent > $project->budget,
                                                'bg-gold-500' => $spent <= $project->budget,
                                            ])
                                            style="width: {{ min(100, $project->budget > 0 ? ($spent / $project->budget) * 100 : 0) }}%"
                                        ></div>
                                    </div>
                                </div>
                            @else
                                <p class="mt-4 text-xs text-navy-400">{{ number_format($project->orders_sum_total ?? 0) }} XAF spent so far</p>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</div>
