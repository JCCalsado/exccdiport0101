# Personal Payment Notifications System

## Overview
Payment notifications are now **personalized to each student**. When StudentA makes a payment or receives a payment notification, StudentB will **NOT** see StudentA's payment notifications.

## How It Works

### Two Types of Notifications

#### 1. **Personal Notifications** (with specific student assigned)
- **Created for:** Payment confirmations, payment approvals, payment rejections, payment due reminders
- **Visibility:** Only visible to the specific student the notification was created for
- **Example:** 
  - StudentA made a payment → Notification created with `user_id = StudentA.id` → Only StudentA sees it
  - StudentB made a payment → Notification created with `user_id = StudentB.id` → Only StudentB sees it

#### 2. **Broadcast Notifications** (no specific student assigned)
- **Created for:** General announcements, policy updates, school-wide messages
- **Visibility:** All students see it (or all users of a specific role)
- **Field:** `user_id` is NULL

---

## Database Fields Controlling Visibility

```
user_id              | target_role  | Visibility
==================== | ============ | ==============================
StudentA.id          | student      | Only StudentA sees it
StudentB.id          | student      | Only StudentB sees it  
NULL                 | student      | All students see it
NULL                 | all          | Everyone sees it
NULL                 | accounting   | Only accounting staff see it
NULL                 | admin        | Only admin see it
```

---

## Payment Notifications (Auto-Created)

### When Created
1. **Payment Approved** - When accounting staff approves a payment
   - File: `app/Services/WorkflowService.php`
   - User: Automatically set to the student who made the payment
   - Visible: Only that student

2. **Payment Rejected** - When accounting staff rejects a payment
   - File: `app/Services/WorkflowService.php`
   - User: Automatically set to the student who made the payment
   - Visible: Only that student

3. **Payment Due Date Set** - When admin sets a due date for a payment term
   - File: `app/Http/Controllers/PaymentTermsController.php`
   - User: Automatically set to the student for that payment term
   - Visible: Only that student

### Code Reference

**WorkflowService.php (line 253)**
```php
Notification::create([
    'title'       => 'Payment Approved',
    'message'     => "Your payment of ₱...has been verified",
    'target_role' => 'student',
    'user_id'     => $student->id,  // ← Personal to this student
    'is_active'   => true,
    'start_date'  => now()->toDateString(),
    'end_date'    => now()->addDays(7)->toDateString(),
]);
```

---

## How Students See Only Their Notifications

### StudentAccountController.php (Updated)

When a student loads their Account Overview page, the system queries notifications with this logic:

```php
// Show notifications if:
// 1. user_id matches the student's ID (personal notifications), OR
// 2. user_id is NULL AND (target_role matches student's role OR target_role is 'all')

$query->where('user_id', $student->id)  // ← Personal notifications
    ->orWhere(function ($q2) use ($student) {
        $q2->whereNull('user_id')
          ->where(function ($q3) use ($student) {
              $q3->where('target_role', $student->role)
                 ->orWhere('target_role', 'all');
          });
    });
```

**Result:**
- StudentA sees: Their own payment notifications + broadcast notifications
- StudentB sees: Their own payment notifications + broadcast notifications
- StudentA does NOT see StudentB's notifications

---

## Creating Personal Notifications (Admin UI)

### Steps

1. Go to **Admin → Notifications → Create**
2. Fill in title and message
3. Set **Target: All Students** (or a specific role)
4. Under "Send to Specific Student", **search and select a student**
   - If you select a student, the notification becomes personal
   - Only that student will see it
5. Optional: Set term-based scheduling
6. Click **Create Notification**

### When to Use Personal Notifications

✅ **DO use specific student selection for:**
- Payment confirmations: "Your payment of ₱5,000 was approved"
- Payment reminders: "Payment due in 3 days for [StudentName]"
- Enrollment approvals: "Your enrollment has been approved"
- Account-specific notices: "Your account balance is ₱..."

❌ **DON'T use specific student selection for:**
- School closures/holidays
- Maintenance announcements
- Policy updates
- General class information

---

## Testing Verification

To verify the system is working:

### Test Case 1: Payment-Specific Notification

1. As Admin:
   - Go to Payments → Approve a payment for StudentA
   - StudentA receives a "Payment Approved" notification

2. As StudentA:
   - Login and check Account Overview
   - You see the "Payment Approved" notification ✓

3. As StudentB:
   - Login and check Account Overview
   - You do NOT see StudentA's "Payment Approved" notification ✓

### Test Case 2: Broadcast Notification

1. As Admin:
   - Go to Notifications → Create
   - Title: "School Holiday"
   - Message: "School is closed tomorrow"
   - Target: All Students
   - Leave "Specific Student" empty
   - Create

2. As StudentA:
   - Login and check Account Overview
   - You see "School Holiday" notification ✓

3. As StudentB:
   - Login and check Account Overview
   - You also see "School Holiday" notification ✓

---

## Database Queries

### View all payment notifications for a student

```sql
SELECT * FROM notifications 
WHERE user_id = {student_id} 
AND type IN ('payment_due', 'payment_approved', 'payment_rejected')
AND is_active = 1
ORDER BY created_at DESC;
```

### View all broadcast notifications

```sql
SELECT * FROM notifications 
WHERE user_id IS NULL 
AND is_active = 1
ORDER BY created_at DESC;
```

### View notifications visible to StudentA

```sql
SELECT * FROM notifications 
WHERE is_active = 1
AND (
  user_id = {StudentA_id}  -- Personal notifications
  OR (
    user_id IS NULL  -- Broadcast notifications
    AND (target_role = 'student' OR target_role = 'all')
  )
)
AND (
  start_date IS NULL OR start_date <= NOW()
)
AND (
  end_date IS NULL OR end_date >= NOW()
)
ORDER BY created_at DESC;
```

---

## Files Modified

- [StudentAccountController.php](app/Http/Controllers/StudentAccountController.php) - Updated query logic for personal notifications
- [Admin/Notifications/Form.vue](resources/js/pages/Admin/Notifications/Form.vue) - Added help text explaining personal vs. broadcast

## Files Already Supporting This

- [Notification.php](app/Models/Notification.php) - `forUser()` scope already handles this
- [PaymentTermsController.php](app/Http/Controllers/PaymentTermsController.php) - Already sets user_id when creating due date notifications
- [WorkflowService.php](app/Services/WorkflowService.php) - Already sets user_id for payment notices

---

## Summary

✅ **Payment notifications are now personal** - Only the student involved sees their payment notifications  
✅ **StudentA and StudentB won't see each other's notifications** - Each student only sees their own payment alerts  
✅ **Automatic notifications** - Payment approvals, rejections, and due dates are auto-created with the student's user_id  
✅ **Clear admin UI** - Form explains personal vs. broadcast notifications with example use cases
