export interface StudentAssessment {
    id: number;
    user_id: number;
    assessment_number: string;
    year_level: string;
    semester: string;
    school_year: string;
    tuition_fee: number;
    other_fees: number;
    total_assessment: number;
    subjects: AssessmentSubject[];
    fee_breakdown: FeeBreakdownItem[];
    status: 'draft' | 'active' | 'completed' | 'cancelled';
    created_by: number;
    created_at: string;
    updated_at: string;
}

export interface AssessmentSubject {
    id: number;
    units: number;
    amount: number;
}

export interface FeeBreakdownItem {
    id: number;
    amount: number;
}

export interface PaymentRecord {
    id: number;
    student_id: number;
    amount: number;
    description: string;
    payment_method: 'cash' | 'gcash' | 'bank_transfer' | 'credit_card' | 'debit_card';
    reference_number: string;
    status: string;
    paid_at: string;
    created_at: string;
    updated_at: string;
}

export interface Subject {
    id: number;
    code: string;
    name: string;
    units: number;
    price_per_unit: number;
    has_lab: boolean;
    lab_fee: number;
    total_cost: number;
}

export interface Fee {
    id: number;
    name: string;
    category: string;
    amount: number;
}

export interface SelectedSubject {
    id: number;
    units: number;
    amount: number;
}

export interface SelectedFee {
    id: number;
    amount: number;
}

export interface StudentFeeFormData {
    user_id: number | null;
    year_level: string;
    semester: string;
    school_year: string;
    subjects: SelectedSubject[];
    other_fees: SelectedFee[];
}
