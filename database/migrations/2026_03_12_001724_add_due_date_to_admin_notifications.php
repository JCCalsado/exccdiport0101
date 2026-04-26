<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add structured due_date and payment_term_id to admin_notifications.
 *
 * WHY:
 *   Previously the due date was only embedded in the notification message as
 *   prose text ("...is due on February 5, 2026...").  The Vue components had
 *   no way to display the due date as a formatted, colour-coded chip separate
 *   from the message body — they could only show end_date (due_date + 14 days),
 *   which is the wrong field and semantically confusing to the student.
 *
 *   payment_term_id links the notification back to its originating payment term
 *   so the "Pay Now" button can navigate directly to the correct term on the
 *   Account Overview page, and so MarkNotificationCompleteOnPayment can close
 *   the exact banner when a specific term is paid rather than all banners.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('admin_notifications', 'due_date')) {
                $table->date('due_date')
                      ->nullable()
                      ->after('end_date')
                      ->comment('Actual payment due date — stored separately so the UI can display and colour-code it without parsing the message text');
            }

            if (! Schema::hasColumn('admin_notifications', 'payment_term_id')) {
                $table->unsignedBigInteger('payment_term_id')
                      ->nullable()
                      ->after('due_date')
                      ->comment('The student_payment_terms row this notification was generated for');

                $table->foreign('payment_term_id')
                      ->references('id')
                      ->on('student_payment_terms')
                      ->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('admin_notifications', function (Blueprint $table) {
            if (Schema::hasColumn('admin_notifications', 'payment_term_id')) {
                $table->dropForeign(['payment_term_id']);
                $table->dropColumn('payment_term_id');
            }

            if (Schema::hasColumn('admin_notifications', 'due_date')) {
                $table->dropColumn('due_date');
            }
        });
    }
};