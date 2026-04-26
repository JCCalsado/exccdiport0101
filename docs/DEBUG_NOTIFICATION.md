# Debug Guide: Early Payment Notification Not Showing

## Quick Diagnosis Steps

### Step 1: Check PHP Logs
Run this command to see if the notification is being filtered:
```bash
tail -50 storage/logs/laravel.log
```

Look for lines starting with `[Checking notification: Early Payment]` to see what's happening.

### Step 2: Verify Notification Exists in Database
```bash
php artisan tinker
> $n = App\Models\Notification::where('title', 'like', '%Early%')->first()
> echo json_encode($n->toArray(), JSON_PRETTY_PRINT)
```

Check these values:
- `is_active`: should be `true` or `1`
- `start_date`: should be NULL, or <= today (2026-02-22)
- `end_date`: should be NULL, or >= today (2026-02-22)
- `target_role`: should be "student" or "all"
- `term_ids`: should be null or empty array []
- `target_term_name`: should be null or empty string
- `trigger_days_before_due`: should be null

### Step 3: Manually Test the Filtering Logic
```bash
php artisan tinker
> $user = App\Models\User::where('email', 'jcdc742713@gmail.com')->first()
> $notifs = App\Models\Notification::where('is_active', true)->get()
> $notifs->each(function($n) { echo "[{$n->id}] {$n->title}\n"; })
```

### Step 4: Check Student Account Page
- Login as a student user (e.g., jcdc742713@gmail.com)
- Go to your Account Overview page
- Open browser DevTools Console (F12)
- Check for any JavaScript errors
- Look at the Network tab to see if notifications data is coming through in the response

## Possible Issues & Solutions

### Issue 1: is_active = false or 0
**Fix:** Go to Admin > Notifications > Early Payment > Click the toggle to "Activate"

### Issue 2: Date Range is Wrong
**Fix:** Ensure:
- start_date is NULL or is TODAY or earlier
- end_date is NULL or is TODAY or later

### Issue 3: target_role doesn't match
**Fix:** Ensure target_role is "student" or "all" (not a specific user ID unless intentional)

### Issue 4: Term-Based Filtering Issue
If term_ids or target_term_name is set, the notification will:
- ONLY show if the student HAS payment terms matching those criteria
- If student has NO payment terms, won't show even with term filtering

**Fix:** Either:
- Clear term_ids and target_term_name (set to null/empty)
- OR ensure the student has payment terms assigned inStudentAssessment

### Issue 5: Form Validation Error During Creation
**Fix:** Check if there were validation errors when creating. Look for error messages displayed:
- Form might have silently failed if validation failed
- Common issues: invalid term_ids (referencing non-existent terms), or invalid target_term_name

## What the New Logging Shows

I've added detailed logging to `StudentAccountController::index()` which logs each notification being checked. View these logs with:

```bash
tail -f storage/logs/laravel.log | grep "Checking notification"
```

Each log line shows:
- Notification title  
- term_ids (will be empty array [] or null)
- target_term_name (will be null/empty if none selected)
- trigger_days_before_due (will be null if not set)
- whether student has payment terms

## Test Case: Create New "Test Payment" Notification

To verify the system is working:

1. Go to Admin > Notifications > Create
2. Enter:
   - Title: "Test Payment"
   - Message: "This is a test notification"
   - Type: General Notification
   - Start Date: today (2026-02-22)
   - No end date
   - Target: All Students
   - Leave "Term-Based Scheduling" on "No specific terms"
   - CHECK the Activation Status toggle
3. Click Create

Then login as a student and check Account Overview - you should see "Test Payment" notification immediately.

If "Test Payment" shows but "Early Payment" doesn't, then the issue is with the "Early Payment" notification's specific configuration.

If neither shows, there may be a system-wide issue.

## Still Stuck?

Please provide:
1. Output from `$ php artisan tinker` showing Early Payment notification data
2. Content of `storage/logs/laravel.log` (last 100 lines)
3. Whether "Early Payment" was created before or after term scheduling feature was added
