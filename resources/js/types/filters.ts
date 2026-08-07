export interface FilterOption {
    value: string | number;
    label: string;
}

/**
 * A filter as reported by the server's VehicleFilterRegistry. The fleet page
 * renders controls generically from these — adding a new filter provider
 * server-side makes it appear here with zero frontend changes.
 */
export interface AvailableFilter {
    id: string;
    label: string;
    uiType: 'select' | 'checkbox' | 'range';
    options: FilterOption[] | null;
    min?: number;
    max?: number;
}

/** A sort option as reported by the server's VehicleSortRegistry. */
export interface AvailableSort {
    id: string;
    label: string;
}
