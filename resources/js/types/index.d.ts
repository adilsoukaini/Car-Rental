export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
}

export interface PageProps {
    auth: {
        user: User | null;
    };
    driverVerificationStatus: 'none' | 'pending' | 'approved' | 'rejected' | null;
    activeLayoutVariants: Record<string, string>;
    [key: string]: unknown;
}

export interface Location {
    id: number;
    name: string;
    address_line: string;
    city: string;
    country: string;
}

export interface Vehicle {
    id: number;
    make: string;
    model: string;
    year: number;
    category: string;
    license_plate: string;
    daily_rate: string;
    seat_count: number;
    transmission_type: string;
    fuel_type: string;
    mileage: number;
    status: string;
    location: Location | null;
    /** Batch-loaded via vehicle.listQuery's EagerLoadPrimaryImagePipe — never null-checked per-card (rule 8: one query for the whole page). */
    primary_image?: VehicleImage | null;
}

export interface VehicleImage {
    id: number;
    url: string;
    alt_text: string | null;
    is_primary: boolean;
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginated<T> {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
}

export interface Booking {
    id: number;
    status: string;
    pickup_at: string;
    return_at: string;
    total_price: string;
    security_deposit_amount: string | null;
    created_at: string;
    vehicle: Pick<Vehicle, 'id' | 'make' | 'model' | 'year'>;
    pickup_location: Pick<Location, 'id' | 'name' | 'city'>;
    return_location: Pick<Location, 'id' | 'name' | 'city'>;
}

export interface Review {
    id: number;
    authorName: string;
    rating: number;
    title: string | null;
    body: string;
    isVerifiedRental: boolean;
    createdAt: string;
}

export interface DriverVerification {
    id: number;
    license_number: string;
    license_country: string;
    date_of_birth: string;
    status: 'pending' | 'approved' | 'rejected';
    rejection_reason: string | null;
    created_at: string;
}
