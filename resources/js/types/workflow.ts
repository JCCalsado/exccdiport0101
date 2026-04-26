export interface Workflow {
    id: number;
    name: string;
    type: 'student' | 'accounting' | 'general';
    description: string | null;
    steps: WorkflowStep[];
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface WorkflowStep {
    name: string;
    description?: string;
    requires_approval: boolean;
    approvers?: number[];
}

export interface WorkflowInstance {
    id: number;
    workflow_id: number;
    workflowable_type: string;
    workflowable_id: number;
    current_step: string;
    status: 'pending' | 'in_progress' | 'completed' | 'rejected';
    step_history: StepHistory[];
    metadata: Record<string, any> | null;
    initiated_by: number;
    completed_at: string | null;
    created_at: string;
    updated_at: string;
    workflow?: Workflow;
    workflowable?: any;
}

export interface StepHistory {
    step: string;
    timestamp: string;
    action: string;
    user_id: number;
    comments?: string;
}

export interface WorkflowApproval {
    id: number;
    workflow_instance_id: number;
    step_name: string;
    approver_id: number;
    status: 'pending' | 'approved' | 'rejected';
    comments: string | null;
    approved_at: string | null;
    created_at: string;
    updated_at: string;
    workflow_instance?: WorkflowInstance;
    approver?: any;
}

export interface Student {
    id: number;
    student_number: string;
    first_name: string;
    last_name: string;
    email: string;
    phone: string | null;
    date_of_birth: string | null;
    enrollment_status: 'pending' | 'active' | 'suspended' | 'graduated';
    enrollment_date: string | null;
    metadata: Record<string, any> | null;
    created_at: string;
    updated_at: string;
    full_name?: string;
}

export interface AccountingTransaction {
    id: number;
    transaction_number: string;
    type: 'invoice' | 'payment' | 'refund' | 'adjustment';
    amount: number;
    currency: string;
    status: string;
    transactionable_type: string;
    transactionable_id: number;
    description: string | null;
    transaction_date: string;
    due_date: string | null;
    metadata: Record<string, any> | null;
    created_at: string;
    updated_at: string;
    transactionable?: any;
}
