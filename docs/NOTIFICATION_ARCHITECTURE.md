# Notification Architecture Reference

## Overview

The CCDI Account Portal uses **two distinct notification systems** that serve different purposes:

1. **Laravel Database Notifications** (`notifications` table)
   - For transactional user notifications
   - Tied to individual users
   - Sent via `$user->notify()`
   - Used for: Approval requests, payment due reminders, payment confirmations

2. **Custom Admin Notifications** (`admin_notifications` table)
   - For system-wide announcements and broadcast notifications
   - Support role-based targeting and time-based scheduling
   - Created via `Notification::create()`
   - Used for: Progression alerts, payment status updates, admin announcements

## Decision Criteria

Use **Laravel $user->notify()** when:
- ✅ Notification is tied to a specific user
- ✅ Need to send via multiple channels (email, database, SMS)
- ✅ Event triggers automatically (payment due, approval needed)
- ✅ User should receive immediately upon event
- Examples: `ApprovalRequired`, `PaymentDueNotification`, `PaymentConfirmed`

Use **Custom Notification::create()** when:
- ✅ Notification targets multiple users by role (admin, accounting, student)
- ✅ Notification has a time window (start_date, end_date)
- ✅ Notification provides context for follow-up actions
- ✅ Need CCDI-specific fields (term_ids, due_date, payment_term_id)
- ✅ Notification should appear in admin Dashboard
- Examples: "Assessment Required", "Progression Ready", "Payment Approved"

## Key Differences

| Aspect | Laravel Notifications | Admin Notifications |
|--------|----------------------|---------------------|
| **Table** | `notifications` | `admin_notifications` |
| **Primary Key** | UUID | BigInt ID |
| **Audience** | Individual User | Role(s) + Time Window |
| **Channels** | Mail, Database, SMS | In-app Display |
| **Lifecycle** | Persistent until read | Start/End dates |
| **Data Structure** | JSON `data` column | Dedicated columns |
| **Query Pattern** | `$user->notifications` | `Notification::where('target_role', ...)` |

## Migration History

- **2025_10_07**: Created initial `notifications` table for custom announcements
- **2026_03_04**: Attempted to merge Laravel's database channel into same table (abandoned)
- **2026_03_11**: Split into two tables - `notifications` (Laravel) and `admin_notifications` (custom)
- **2026_03_12**: Added `due_date` and `payment_term_id` to `admin_notifications`

## Current Usage Map

### Laravel Notifications ($user->notify())

**Location:** `app/Services/WorkflowService.php:274`
```php
$approver->notify(new \App\Notifications\ApprovalRequired($approval));
```
- **Purpose:** Notify approvers when approval step is ready
- **Channels:** Mail + Database
- **Evidence:** ApprovalRequired sends email and writes to notifications table

**Location:** `app/Listeners/SendPaymentDueNotification.php:52`
```php
$event->user->notify(new PaymentDueNotification(...));
```
- **Purpose:** Remind students of upcoming payment due date
- **Channels:** Mail + Database
- **Evidence:** Sends email 7 days before due date

**Location:** `app/Listeners/SendPaymentConfirmationNotification.php:16`
```php
$event->user->notify(new PaymentConfirmed(...));
```
- **Purpose:** Confirm payment has been processed
- **Channels:** Mail + Database

### Custom Notifications (Notification::create())

**Location:** `app/Services/StudentPaymentService.php:475+`
```php
Notification::create([
    'title'       => "📋 Assessment Required: {$studentName}",
    'target_role' => 'admin',
    'user_id'     => null,
    ...
]);
```
- **Purpose:** Alert admins when student has fully paid and next assessment is needed
- **Audience:** All admin users
- **Evidence:** Creates broadcast notification with start/end dates

**Location:** `app/Services/WorkflowService.php:340+`
```php
\App\Models\Notification::create([
    'title'       => 'Payment Approved',
    'target_role' => 'student',
    'user_id'     => $student->id,
    ...
]);
```
- **Purpose:** Notify student that their payment workflow was approved
- **Audience:** Specific student
- **Evidence:** Uses user_id + target_role combination

**Location:** `app/Http/Controllers/NotificationController.php:81`
```php
$notification = Notification::create($validated);
```
- **Purpose:** Admin creates manual announcements for students/staff
- **Audience:** By role + time window

## Code Review Checklist

When reviewing notification code, verify:

- [ ] If notification is from an **event** → likely should use `$user->notify()`
- [ ] If notification is a **workflow action** → likely should use `Notification::create()`
- [ ] If notification has **role targeting** → must use `Notification::create()`
- [ ] If notification has **time window** → must use `Notification::create()`
- [ ] If notification is **immediate** → consider `$user->notify()`
- [ ] Comments explain why one system was chosen

## Migration Path for New Notifications

1. **Transactional Event?** (e.g., "payment received", "enrollment confirmed")
   - Create Laravel Notification class in `app/Notifications/`
   - Dispatch via `$user->notify()` in the event listener
   - Set channels: `['mail', 'database']`

2. **System Announcement?** (e.g., "assessment ready", "payment overdue in 3 days")
   - Call `Notification::create()` directly
   - Include `target_role`, `user_id`, `start_date`, `end_date`
   - Add CCDI-specific context (term IDs, amounts, etc.)

3. **Unsure?**
   - If user needs to act → likely `$user->notify()`
   - If system is announcing → likely `Notification::create()`
   - When in doubt, check similar existing notifications in this doc

## Testing Notifications

### Laravel Notifications
```php
// In tests, Laravel provides notification fakes
Notification::fake();
$user->notify(new PaymentDueNotification(...));
Notification::assertSentTo($user, PaymentDueNotification::class);
```

### Admin Notifications
```php
// Query the admin_notifications table
$this->assertDatabaseHas('admin_notifications', [
    'target_role' => 'admin',
    'type'        => 'progression_ready',
]);
```

## Debugging Tips

**Q: Why isn't my notification showing up?**

1. Check which table it wrote to:
   ```sql
   SELECT COUNT(*) FROM notifications WHERE created_at > NOW() - INTERVAL 1 HOUR;
   SELECT COUNT(*) FROM admin_notifications WHERE created_at > NOW() - INTERVAL 1 HOUR;
   ```

2. If it's in `admin_notifications`:
   - Confirm `is_active = true`
   - Confirm `start_date <= NOW()` and `end_date >= NOW()`
   - Confirm `target_role` matches user's role

3. If it's in `notifications` (Laravel):
   - Check if user has notification preference enabled
   - Verify `read_at IS NULL` if checking unread
   - Look for `notifiable_id` and `notifiable_type` match

**Q: Am I creating notifications correctly?**

Run this query to see both systems at once:
```sql
SELECT 'laravel' as source, type, COUNT(*) as count FROM notifications GROUP BY type
UNION ALL
SELECT 'admin' as source, type, COUNT(*) as count FROM admin_notifications GROUP BY type;
```

## Future Improvements

- [ ] Create `StudentNotificationPreference` to let users opt-in/out of each notification type
- [ ] Add notification templating system for consistency
- [ ] Monitor notification delivery failure rates
- [ ] Implement notification throttling (e.g., max 1 payment-due per day)
