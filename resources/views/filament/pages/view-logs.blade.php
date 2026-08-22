<x-filament-panels::page>
    <div class="flex items-center justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-3">
            <label for="selectedFile" class="text-sm font-medium text-gray-700 dark:text-gray-200">Log file</label>
            <select
                wire:model.live="selectedFile"
                id="selectedFile"
                class="fi-select-input rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500"
            >
                @foreach ($this->availableFiles() as $file => $label)
                    <option value="{{ $file }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <button
            type="button"
            wire:click="$refresh"
            class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-primary-600 transition-colors"
        >
            <x-filament::icon icon="heroicon-o-arrow-path" class="w-4 h-4" />
            Refresh
        </button>
    </div>

    @php $entries = $this->entries(); @endphp

    <div class="mt-6 space-y-2">
        @forelse ($entries as $entry)
            <div
                x-data="{ open: false }"
                class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden"
            >
                <button
                    type="button"
                    @click="open = ! open"
                    class="w-full flex items-center gap-3 p-3 text-left hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                >
                    <span @class([
                        'shrink-0 text-xs font-semibold px-2 py-1 rounded-full',
                        'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-400' => in_array($entry['level'], ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY']),
                        'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400' => $entry['level'] === 'WARNING',
                        'bg-blue-100 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400' => in_array($entry['level'], ['INFO', 'NOTICE']),
                        'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' => in_array($entry['level'], ['DEBUG']),
                    ])>
                        {{ $entry['level'] }}
                    </span>
                    <span class="shrink-0 text-xs text-gray-400 font-mono">{{ $entry['timestamp'] }}</span>
                    <span class="text-sm text-gray-700 dark:text-gray-200 truncate flex-1">{{ $entry['summary'] }}</span>
                    <x-filament::icon icon="heroicon-o-chevron-down" class="w-4 h-4 text-gray-400 shrink-0 transition-transform" x-bind:class="open && 'rotate-180'" />
                </button>

                <div x-show="open" x-collapse class="border-t border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 p-3">
                    <pre class="text-xs text-gray-700 dark:text-gray-300 whitespace-pre-wrap break-words font-mono max-h-96 overflow-y-auto">{{ $entry['full'] }}</pre>
                </div>
            </div>
        @empty
            <div class="text-center py-12 text-sm text-gray-400">
                No log entries found in this file.
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
