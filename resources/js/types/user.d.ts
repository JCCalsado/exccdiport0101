// Base User type
export interface User {
    id: number;
    name: string;
    email: string;
    role: string;

    avatar?: string | null;
    profile_picture?: string;
    email_verified_at?: string | null;

    created_at?: string;
    updated_at?: string;
}

// StudentUser extends User
export interface StudentUser extends User {
    student_id: string;
    course: string;
    year_level: string;

    address?: string;
    phone?: string;
    status?: 'active' | 'graduated' | 'dropped';
}
