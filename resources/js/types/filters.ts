export interface FilterOption {
    value: string;
    label: string;
}

export interface AvailableFilter {
    name: string;
    label: string;
    type: 'select' | 'checkbox' | 'range';
    options?: FilterOption[];
    min?: number;
    max?: number;
}

export interface AvailableSort {
    value: string;
    label: string;
}
