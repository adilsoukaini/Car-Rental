<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Step 1: Upload + template download --}}
        <x-filament::section>
            <x-slot name="heading">
                Upload a CSV
            </x-slot>
            <x-slot name="description">
                Expected columns: make, model, year, category, license_plate, daily_rate, seat_count, transmission_type, fuel_type, mileage, status. Rows that fail validation are skipped, not rejected — you'll see exactly why afterwards.
            </x-slot>

            <div class="flex flex-col gap-4">
                <div>
                    <input
                        type="file"
                        wire:model="csvFile"
                        accept=".csv,.txt"
                        class="block w-full text-sm text-gray-500 file:mr-4 file:cursor-pointer file:rounded-md file:border-0 file:bg-primary-500 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-primary-600 dark:text-gray-400"
                    >
                    @error('csvFile')
                        <p class="mt-2 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                    @enderror

                    <div wire:loading wire:target="csvFile" class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Uploading…
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <x-filament::button
                        href="{{ route('filament.admin.vehicle-import-template') }}"
                        tag="a"
                        icon="heroicon-m-arrow-down-tray"
                        color="gray"
                        size="sm"
                    >
                        Download template
                    </x-filament::button>
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        Includes a filled sample row so you can copy the exact format.
                    </span>
                </div>
            </div>
        </x-filament::section>

        {{-- Parse error --}}
        @if ($previewError)
            <x-filament::section>
                <p class="text-sm text-danger-600 dark:text-danger-400">{{ $previewError }}</p>
            </x-filament::section>
        @endif

        {{-- Preview --}}
        @if (count($preview ?? []) > 0)
            <x-filament::section>
                <x-slot name="heading">
                    Preview
                </x-slot>
                <x-slot name="description">
                    Showing the first {{ count($preview) }} row(s) parsed from the file.
                </x-slot>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                @foreach (array_keys($preview[0]) as $column)
                                    <th class="px-3 py-2">{{ $column }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($preview as $row)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    @foreach ($row as $value)
                                        <td class="px-3 py-2 text-gray-700 dark:text-gray-300">
                                            {{ ($value === null || $value === '') ? '—' : $value }}
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    <x-filament::button
                        wire:click="import"
                        icon="heroicon-m-document-arrow-up"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-50"
                    >
                        <span wire:loading.remove wire:target="import">Import vehicles</span>
                        <span wire:loading wire:target="import">Importing…</span>
                    </x-filament::button>
                </div>
            </x-filament::section>
        @endif

        {{-- Results --}}
        @if ($importedCount !== null)
            <x-filament::section>
                <x-slot name="heading">
                    Import results
                </x-slot>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="rounded-lg border border-success-200 bg-success-50 p-4 text-success-700 dark:border-success-700 dark:bg-success-950 dark:text-success-300">
                        <div class="text-2xl font-bold">{{ $importedCount }}</div>
                        <div class="text-sm">vehicles imported</div>
                    </div>
                    <div class="{{ $failedCount > 0 ? 'border-danger-200 bg-danger-50 text-danger-700 dark:border-danger-700 dark:bg-danger-950 dark:text-danger-300' : 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300' }} rounded-lg border p-4">
                        <div class="text-2xl font-bold">{{ $failedCount }}</div>
                        <div class="text-sm">row(s) skipped</div>
                    </div>
                </div>

                @if (count($failureRows ?? []) > 0)
                    <div class="mt-4">
                        <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Skipped rows</h3>
                        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-gray-600 dark:text-gray-400">
                            @foreach ($failureRows as $failure)
                                <li>
                                    {{ $failure['row'] !== null ? 'Row '.$failure['row'].':' : 'Import error:' }}
                                    {{ implode('; ', $failure['errors']) }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </x-filament::section>
        @endif
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
