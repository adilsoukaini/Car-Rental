<x-filament-panels::page>
    <div class="space-y-6">
        @forelse ($this->getSlots() as $slotName => $variants)
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ \Illuminate\Support\Str::title(\Illuminate\Support\Str::replace(['-', '_'], ' ', $slotName)) }}
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Slot: <code class="rounded bg-gray-100 px-1 py-0.5 text-xs dark:bg-gray-700">{{ $slotName }}</code>
                </p>

                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @php $activeId = $this->getActiveVariant($slotName); @endphp

                    @foreach ($variants as $variant)
                        <button
                            wire:click="setActiveVariant('{{ $slotName }}', '{{ $variant['variantId'] }}')"
                            @class([
                                'rounded-lg border-2 p-4 text-left transition-colors',
                                'border-primary-500 bg-primary-50 ring-2 ring-primary-500 dark:border-primary-400 dark:bg-primary-900/20 dark:ring-primary-400' => ($activeId === $variant['variantId'] || ($activeId === '' && $loop->first)),
                                'border-gray-200 bg-white hover:border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:hover:border-gray-500' => ($activeId !== $variant['variantId'] && !($activeId === '' && $loop->first)),
                            ])
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $variant['label'] }}
                                </span>

                                @if ($activeId === $variant['variantId'] || ($activeId === '' && $loop->first))
                                    <x-filament::icon
                                        icon="heroicon-m-check-circle"
                                        class="h-5 w-5 text-primary-500 dark:text-primary-400"
                                    />
                                @endif
                            </div>

                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Component: <code class="rounded bg-gray-100 px-1 py-0.5 dark:bg-gray-700">{{ $variant['componentName'] }}</code>
                            </p>

                            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                Plugin: {{ $variant['pluginSlug'] }}
                            </p>
                        </button>
                    @endforeach
                </div>

                @if (empty($variants))
                    <p class="text-sm text-gray-400 dark:text-gray-500">No variants registered for this slot yet.</p>
                @endif
            </div>
        @empty
            <div class="rounded-xl border border-gray-200 bg-white p-12 text-center dark:border-gray-700 dark:bg-gray-800">
                <x-filament::icon
                    icon="heroicon-o-swatch"
                    class="mx-auto h-12 w-12 text-gray-400"
                />
                <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">No layout slots registered</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Plugins register layout variants via <code class="rounded bg-gray-100 px-1 py-0.5 text-xs dark:bg-gray-700">LayoutVariantRegistry::register()</code>.
                    Once a plugin provides variants, they will appear here.
                </p>
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
