<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Booking Calendar
        </x-slot>
        <x-slot name="description">
            Vehicle bookings at a glance — green dots are pickups, red dots are returns, blue dots are active
            rental days. Click a day to see which bookings touch it.
        </x-slot>

        @php $calendar = $this->getCalendarData(); @endphp

        <div class="booking-calendar">
            <div class="bcal-header">
                <span class="bcal-month-name">{{ $calendar['monthName'] }}</span>

                <div class="bcal-nav">
                    <button type="button" wire:click="goToPreviousMonth" class="bcal-nav-btn">
                        &larr; Prev
                    </button>
                    <button type="button" wire:click="goToCurrentMonth" class="bcal-nav-btn bcal-nav-btn-primary">
                        Today
                    </button>
                    <button type="button" wire:click="goToNextMonth" class="bcal-nav-btn">
                        Next &rarr;
                    </button>
                </div>
            </div>

            <div class="bcal-weekdays">
                <div>Sun</div>
                <div>Mon</div>
                <div>Tue</div>
                <div>Wed</div>
                <div>Thu</div>
                <div>Fri</div>
                <div>Sat</div>
            </div>

            <div class="bcal-grid">
                @foreach ($calendar['rows'] as $row)
                    <div class="bcal-row">
                        @foreach ($row as $cell)
                            @if ($cell === null)
                                <div class="bcal-cell bcal-cell-empty"></div>
                            @elseif (count($cell['markers']) === 0)
                                <div class="bcal-cell {{ $cell['isToday'] ? 'bcal-today' : '' }} {{ $cell['isWeekend'] ? 'bcal-weekend' : '' }}">
                                    <span class="bcal-day-num">{{ $cell['day'] }}</span>
                                </div>
                            @else
                                <details class="bcal-cell bcal-has-bookings {{ $cell['isToday'] ? 'bcal-today' : '' }} {{ $cell['isWeekend'] ? 'bcal-weekend' : '' }}">
                                    <summary title="Click for booking details">
                                        <span class="bcal-day-num">{{ $cell['day'] }}</span>
                                        <span class="bcal-dots">
                                            @foreach (array_slice($cell['markers'], 0, 4) as $marker)
                                                <span class="bcal-dot bcal-dot-{{ $marker['color'] }}"></span>
                                            @endforeach
                                            @if (count($cell['markers']) > 4)
                                                <span class="bcal-more">+{{ count($cell['markers']) - 4 }}</span>
                                            @endif
                                        </span>
                                    </summary>

                                    <div class="bcal-popover">
                                        <div class="bcal-popover-title">{{ count($cell['markers']) }} booking event{{ count($cell['markers']) === 1 ? '' : 's' }} on {{ $cell['dateKey'] }}</div>
                                        @foreach ($cell['markers'] as $marker)
                                            <div class="bcal-popover-row">
                                                <span class="bcal-dot bcal-dot-{{ $marker['color'] }}"></span>
                                                <span class="bcal-popover-vehicle">{{ $marker['vehicle'] }}</span>
                                                <span class="bcal-popover-sub">
                                                    {{ $marker['status'] }} &middot; {{ $marker['bookingNumber'] }}
                                                    <br>
                                                    {{ $marker['pickupAt'] }} &rarr; {{ $marker['returnAt'] }}
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                </details>
                            @endif
                        @endforeach
                    </div>
                @endforeach
            </div>

            <div class="bcal-legend">
                <span class="bcal-legend-item"><span class="bcal-dot bcal-dot-pickup"></span> Pickup</span>
                <span class="bcal-legend-item"><span class="bcal-dot bcal-dot-active"></span> Active</span>
                <span class="bcal-legend-item"><span class="bcal-dot bcal-dot-return"></span> Return</span>
            </div>
        </div>

        <style>
            .booking-calendar {
                --bcal-pickup: var(--success-500);
                --bcal-return: var(--danger-500);
                --bcal-active: var(--info-500);
                font-size: 0.875rem;
                line-height: 1.25rem;
                color: var(--gray-700);
            }

            .bcal-header {
                position: relative;
                z-index: 20;
                display: flex;
                align-items: center;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 0.75rem;
                margin-bottom: 0.75rem;
            }

            .bcal-month-name {
                font-size: 1.125rem;
                font-weight: 600;
                color: var(--gray-950);
            }

            .bcal-nav {
                display: flex;
                gap: 0.375rem;
            }

            .bcal-nav-btn {
                appearance: none;
                border: 1px solid var(--gray-300);
                background: var(--gray-50);
                color: var(--gray-700);
                border-radius: 0.375rem;
                padding: 0.375rem 0.625rem;
                font-size: 0.75rem;
                font-weight: 500;
                cursor: pointer;
                transition: background-color 0.15s ease, border-color 0.15s ease;
            }

            .bcal-nav-btn:hover {
                background: var(--gray-100);
                border-color: var(--gray-400);
            }

            .bcal-nav-btn-primary {
                background: var(--primary-600);
                border-color: var(--primary-600);
                color: var(--color-white, #fff);
            }

            .bcal-nav-btn-primary:hover {
                background: var(--primary-700);
                border-color: var(--primary-700);
            }

            .bcal-weekdays {
                display: grid;
                grid-template-columns: repeat(7, minmax(0, 1fr));
                gap: 0.25rem;
                margin-bottom: 0.25rem;
            }

            .bcal-weekdays div {
                text-align: center;
                font-size: 0.6875rem;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: var(--gray-400);
                padding: 0.25rem 0;
            }

            .bcal-grid {
                display: flex;
                flex-direction: column;
                gap: 0.25rem;
            }

            .bcal-row {
                display: grid;
                grid-template-columns: repeat(7, minmax(0, 1fr));
                gap: 0.25rem;
            }

            .bcal-cell {
                position: relative;
                min-height: 3.75rem;
                border: 1px solid var(--gray-200);
                border-radius: 0.375rem;
                background: var(--gray-50);
                padding: 0.375rem;
                display: flex;
                flex-direction: column;
                gap: 0.25rem;
            }

            .bcal-cell-empty {
                background: transparent;
                border-color: transparent;
            }

            .bcal-weekend:not(.bcal-has-bookings) {
                background: var(--gray-100);
            }

            .bcal-today {
                border-color: var(--primary-500);
                box-shadow: inset 0 0 0 1px var(--primary-500);
            }

            .bcal-today .bcal-day-num {
                background: var(--primary-600);
                color: var(--color-white, #fff);
                border-radius: 9999px;
                padding: 0 0.375rem;
            }

            .bcal-day-num {
                align-self: flex-start;
                font-weight: 600;
                font-size: 0.8125rem;
                color: var(--gray-700);
            }

            .bcal-dots {
                display: flex;
                flex-wrap: wrap;
                gap: 0.25rem;
                align-items: center;
            }

            .bcal-dot {
                width: 0.625rem;
                height: 0.625rem;
                border-radius: 9999px;
                display: inline-block;
                flex-shrink: 0;
            }

            .bcal-dot-pickup {
                background: var(--bcal-pickup);
            }

            .bcal-dot-return {
                background: var(--bcal-return);
            }

            .bcal-dot-active {
                background: var(--bcal-active);
            }

            .bcal-more {
                font-size: 0.625rem;
                font-weight: 600;
                color: var(--gray-500);
            }

            .bcal-has-bookings summary {
                list-style: none;
                cursor: pointer;
                display: flex;
                flex-direction: column;
                gap: 0.25rem;
            }

            .bcal-has-bookings summary::-webkit-details-marker {
                display: none;
            }

            .bcal-has-bookings[open] summary .bcal-dots {
                opacity: 0.4;
            }

            .bcal-popover {
                position: absolute;
                bottom: calc(100% + 0.375rem);
                left: 0;
                min-width: 14rem;
                max-width: 18rem;
                /* Below the header/nav (z-index 20) so an open popover can
                   never block the Prev/Today/Next buttons. */
                z-index: 10;
                background: var(--gray-50);
                border: 1px solid var(--gray-300);
                border-radius: 0.5rem;
                box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
                padding: 0.75rem;
                color: var(--gray-700);
            }

            .bcal-row:first-child .bcal-popover {
                bottom: auto;
                top: calc(100% + 0.375rem);
            }

            .bcal-popover-title {
                font-size: 0.6875rem;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: var(--gray-500);
                margin-bottom: 0.5rem;
            }

            .bcal-popover-row {
                display: grid;
                grid-template-columns: auto 1fr;
                column-gap: 0.5rem;
                align-items: start;
                padding: 0.375rem 0;
                border-top: 1px solid var(--gray-200);
            }

            .bcal-popover-row .bcal-dot {
                margin-top: 0.25rem;
            }

            .bcal-popover-vehicle {
                font-weight: 600;
                color: var(--gray-800);
            }

            .bcal-popover-sub {
                grid-column: 2;
                font-size: 0.75rem;
                color: var(--gray-500);
                margin-top: 0.125rem;
            }

            .bcal-legend {
                display: flex;
                flex-wrap: wrap;
                gap: 1rem;
                margin-top: 0.75rem;
                font-size: 0.75rem;
                color: var(--gray-500);
            }

            .bcal-legend-item {
                display: inline-flex;
                align-items: center;
                gap: 0.375rem;
            }

            @media (max-width: 640px) {
                .bcal-cell {
                    min-height: 3rem;
                    padding: 0.25rem;
                }

                .bcal-popover {
                    min-width: 12rem;
                }
            }
        </style>
    </x-filament::section>
</x-filament-widgets::widget>
