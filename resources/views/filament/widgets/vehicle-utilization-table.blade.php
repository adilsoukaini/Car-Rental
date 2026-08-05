<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Vehicle Utilization (last 30 days)
        </x-slot>
        <x-slot name="description">
            Booked days as a percentage of the last 30 days per vehicle. Denominator is the full window for
            every vehicle, not reduced for time spent in maintenance status.
        </x-slot>

        <table class="fi-ta-table w-full text-sm">
            <thead>
                <tr class="text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
                    <th class="py-2">Vehicle</th>
                    <th class="py-2">Plate</th>
                    <th class="py-2">Booked Days</th>
                    <th class="py-2">Utilization</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($this->getRows() as $row)
                    <tr class="border-t border-gray-100 dark:border-white/5">
                        <td class="py-2">{{ $row['vehicle']->make }} {{ $row['vehicle']->model }}</td>
                        <td class="py-2">{{ $row['vehicle']->license_plate }}</td>
                        <td class="py-2">{{ $row['bookedDays'] }}</td>
                        <td class="py-2">{{ $row['utilizationPercent'] }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-filament::section>
</x-filament-widgets::widget>
