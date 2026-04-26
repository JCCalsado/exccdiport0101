// Base User type — mirrors the resolveAuthUser() output in HandleInertiaRequests
export interface User {
    id: number;
    name: string; // "LAST, First MI." — computed accessor
    first_name: string;
    last_name: string;
    middle_initial?: string | null;
    email: string;
    role: string; // 'admin' | 'accounting' | 'student'

    avatar?: string | null; // Full URL built from profile_picture — for display
    profile_picture?: string | null; // Raw storage path — for settings page
    email_verified_at?: string | null;

    is_active?: boolean;
    faculty?: string | null;
    department?: string | null;

    created_at?: string;
    updated_at?: string;
}

// StudentUser extends User with student-specific fields
export interface StudentUser extends User {
    account_id: string;
    course: string;
    year_level: string;
    is_irregular?: boolean;

    address?: string | null;
    phone?: string | null;
    birthday?: string | null;
    status?: 'active' | 'graduated' | 'dropped';
}
