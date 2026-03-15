<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\StudentDashboardController;
use App\Http\Controllers\StudentAccountController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentFeeController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\AccountingDashboardController;
// REMOVED: FeeController (Fee Management disabled)
// REMOVED: SubjectController (Subject Management disabled)
use App\Http\Controllers\UserController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentTermsController;
use App\Http\Controllers\PaymentReminderController;
// NEW: Workflow Controllers
use App\Http\Controllers\WorkflowController;
use App\Http\Controllers\WorkflowApprovalController;
use App\Http\Controllers\AccountingTransactionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// ============================================
// PUBLIC ROUTES
// ============================================
Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// ============================================
// AUTHENTICATED ROUTES
// ============================================
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('notifications/{notification}/dismiss', [NotificationController::class, 'dismiss'])->name('notifications.dismiss');
});

// ============================================
// STUDENT-SPECIFIC ROUTES
// ============================================
Route::middleware(['auth', 'verified', 'role:student'])->prefix('student')->group(function () {
    Route::get('/dashboard', [StudentDashboardController::class, 'index'])->name('student.dashboard');
    Route::get('/account', [StudentAccountController::class, 'index'])->name('student.account');
    Route::get('/payment', [PaymentController::class, 'create'])->name('payment.create');
    Route::get('/my-profile', [StudentController::class, 'studentProfile'])->name('my-profile');
    Route::post('reminders/{reminder}/read', [PaymentReminderController::class, 'markRead'])
        ->name('reminders.read');
    Route::post('reminders/{reminder}/dismiss', [PaymentReminderController::class, 'dismiss'])
        ->name('reminders.dismiss');
});

// ============================================
// STUDENT ARCHIVE ROUTES (Admin Only)
// ============================================
Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::resource('students', StudentController::class);
    Route::post('students/{student}/payments', [StudentController::class, 'storePayment'])->name('students.payments.store');
    Route::post('students/{student}/advance-workflow', [StudentController::class, 'advanceWorkflow'])
        ->name('students.advance-workflow');
    Route::get('students/{student}/workflow-history', [StudentController::class, 'workflowHistory'])
        ->name('students.workflow-history');
    // Student Archives — graduated, dropped, and inactive students
    Route::get('students-archive', [StudentController::class, 'archive'])->name('students.archive');
});

// ============================================
// STUDENT FEE MANAGEMENT ROUTES
// ============================================
Route::middleware(['auth', 'verified', 'role:admin,accounting'])->prefix('student-fees')->group(function () {
    Route::get('/', [StudentFeeController::class, 'index'])->name('student-fees.index');
    Route::get('/create-student', [StudentFeeController::class, 'createStudent'])->name('student-fees.create-student');
    Route::post('/store-student', [StudentFeeController::class, 'storeStudent'])->name('student-fees.store-student');
    Route::get('/create', [StudentFeeController::class, 'create'])->name('student-fees.create');
    Route::post('/', [StudentFeeController::class, 'store'])->name('student-fees.store');
    Route::get('/{userId}', [StudentFeeController::class, 'show'])->name('student-fees.show');
    Route::get('/{userId}/edit', [StudentFeeController::class, 'edit'])->name('student-fees.edit');
    Route::put('/{userId}', [StudentFeeController::class, 'update'])->name('student-fees.update');
    Route::post('/{userId}/payments', [StudentFeeController::class, 'storePayment'])->name('student-fees.payments.store');
    Route::get('/{userId}/export-pdf', [StudentFeeController::class, 'exportPdf'])->name('student-fees.export-pdf');
});

// ============================================
// TRANSACTION ROUTES
// ============================================
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/download', [TransactionController::class, 'download'])->name('transactions.download');
    // Single-transaction receipt PDF — any authenticated user, but controller enforces ownership
    Route::get('/transactions/{transaction}/receipt', [TransactionController::class, 'receipt'])->name('transactions.receipt');
    Route::post('/account/pay-now', [TransactionController::class, 'payNow'])->name('account.pay-now');
});

Route::middleware(['auth', 'verified', 'role:admin,accounting'])->group(function () {
    Route::get('/transactions/create', [TransactionController::class, 'create'])->name('transactions.create');
    Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store');
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
    Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');
});

// ============================================
// ADMIN ROUTES
// ============================================
Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::resource('users', AdminController::class)->names([
        'index'   => 'users.index',
        'create'  => 'users.create',
        'store'   => 'users.store',
        'show'    => 'users.show',
        'edit'    => 'users.edit',
        'update'  => 'users.update',
        'destroy' => 'users.destroy',
    ]);
    // NOTE: These two routes MUST use the admin.users.* name — no aliases.
    Route::post('users/{user}/deactivate', [AdminController::class, 'deactivate'])
        ->name('admin.users.deactivate');
    Route::post('users/{user}/reactivate', [AdminController::class, 'reactivate'])
        ->name('admin.users.reactivate');
    Route::resource('notifications', NotificationController::class);
    Route::get('/payment-terms', [PaymentTermsController::class, 'index'])->name('admin.payment-terms.index');
    Route::post('/payment-terms/{paymentTerm}/due-date', [PaymentTermsController::class, 'updateDueDate'])->name('admin.payment-terms.update-due-date');
    Route::post('/payment-terms/bulk-due-date', [PaymentTermsController::class, 'bulkUpdateDueDate'])->name('admin.payment-terms.bulk-due-date');
});

// ============================================
// ACCOUNTING ROUTES
// ============================================
Route::middleware(['auth', 'verified', 'role:accounting,admin'])->prefix('accounting')->group(function () {
    Route::get('/dashboard', [AccountingDashboardController::class, 'index'])->name('accounting.dashboard');
    Route::get('/transactions', [TransactionController::class, 'index'])->name('accounting.transactions.index');
});

// ============================================
// ACCOUNTING TRANSACTION WORKFLOW ROUTES
// ============================================
Route::middleware(['auth', 'verified', 'role:admin,accounting'])->prefix('accounting-workflows')->group(function () {
    Route::get('/', [AccountingTransactionController::class, 'index'])->name('accounting-workflows.index');
    Route::get('/create', [AccountingTransactionController::class, 'create'])->name('accounting-workflows.create');
    Route::post('/', [AccountingTransactionController::class, 'store'])->name('accounting-workflows.store');
    Route::get('/{transaction}', [AccountingTransactionController::class, 'show'])->name('accounting-workflows.show');
    Route::put('/{transaction}', [AccountingTransactionController::class, 'update'])->name('accounting-workflows.update');
    Route::delete('/{transaction}', [AccountingTransactionController::class, 'destroy'])->name('accounting-workflows.destroy');
    Route::post('/{transaction}/submit', [AccountingTransactionController::class, 'submitForApproval'])
        ->name('accounting-workflows.submit');
});

// ============================================
// FEE MANAGEMENT ROUTES — DISABLED
// ============================================
// Route::middleware(['auth', 'verified', 'role:admin,accounting'])->group(function () {
//     Route::resource('fees', FeeController::class);
//     Route::post('fees/{fee}/toggle-status', [FeeController::class, 'toggleStatus'])->name('fees.toggleStatus');
//     Route::post('fees/assign-to-students', [FeeController::class, 'assignToStudents'])->name('fees.assignToStudents');
// });

// ============================================
// SUBJECT MANAGEMENT ROUTES — DISABLED
// ============================================
// Route::middleware(['auth', 'verified', 'role:admin,accounting'])->group(function () {
//     Route::resource('subjects', SubjectController::class);
//     Route::post('subjects/{subject}/enroll-students', [SubjectController::class, 'enrollStudents'])->name('subjects.enrollStudents');
// });

// ============================================
// WORKFLOW MANAGEMENT ROUTES
// ============================================
Route::middleware(['auth', 'verified', 'role:admin,accounting'])->group(function () {
    Route::resource('workflows', WorkflowController::class);
    Route::get('/approvals', [WorkflowApprovalController::class, 'index'])->name('approvals.index');
    Route::get('/approvals/{approval}', [WorkflowApprovalController::class, 'show'])->name('approvals.show');
    Route::post('/approvals/{approval}/approve', [WorkflowApprovalController::class, 'approve'])->name('approvals.approve');
    Route::post('/approvals/{approval}/reject', [WorkflowApprovalController::class, 'reject'])->name('approvals.reject');
});

// ============================================
// SETTINGS ROUTES
// ============================================
Route::middleware('auth')->prefix('settings')->name('profile.')->group(function () {
    Route::delete('profile', [\App\Http\Controllers\Settings\ProfileController::class, 'destroy'])->name('destroy');
    Route::get('profile', [\App\Http\Controllers\Settings\ProfileController::class, 'edit'])->name('edit');
    Route::patch('profile', [\App\Http\Controllers\Settings\ProfileController::class, 'update'])->name('update');
    Route::post('profile-picture', [\App\Http\Controllers\Settings\ProfileController::class, 'updatePicture'])->name('update-picture');
    Route::delete('profile-picture', [\App\Http\Controllers\Settings\ProfileController::class, 'removePicture'])->name('remove-picture');
});

Route::middleware('auth')->prefix('settings')->name('password.')->group(function () {
    Route::get('password', [\App\Http\Controllers\Settings\PasswordController::class, 'edit'])->name('edit');
    Route::put('password', [\App\Http\Controllers\Settings\PasswordController::class, 'update'])->name('update');
});

Route::middleware('auth')->prefix('settings')->group(function () {
    Route::get('appearance', fn () => Inertia::render('settings/Appearance'))->name('appearance');
});

require __DIR__ . '/settings.php';

if (app()->environment('local')) {
    Route::middleware('auth')->get('/debug/csrf-token', [\App\Http\Controllers\Debug\DebugController::class, 'csrfToken']);
}

require __DIR__ . '/auth.php';