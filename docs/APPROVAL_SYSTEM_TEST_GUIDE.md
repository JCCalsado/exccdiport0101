# Quick Approval System Test Guide

## Test Scenario: Student Payment → Accounting Approval → Finalization

### Setup (One-time)
```bash
# Apply the fixes and reseed database
php artisan migrate:fresh --seed

# Verify workflow is created
php artisan tinker
>>> Workflow::where('type', 'payment_approval')->first()
# Should return Workflow with ID, type='payment_approval', is_active=1
```

### Test 1: Student Submits Payment

**Steps:**
1. Go to http://localhost:8000/student/payment
2. Select a payment term (e.g., "First Term") from dropdown
3. Enter amount: 1000
4. Select payment method: "Cash" or "Check"
5. Click "Submit for Approval"

**Expected Result:**
- ✅ Success message: "Payment submitted successfully. Please wait for accounting approval."
- ✅ Page shows payment in "Pending Approval" section

**Verify in Database:**
```sql
SELECT * FROM transactions ORDER BY id DESC LIMIT 1;
-- Expected: status = 'awaiting_approval'

SELECT * FROM workflow_instances ORDER BY id DESC LIMIT 1;
-- Expected: current_step = 'Accounting Verification', status = 'in_progress'

SELECT * FROM workflow_approvals ORDER BY id DESC LIMIT 1;
-- Expected: status = 'pending', step_name = 'Accounting Verification'
```

---

### Test 2: Accounting Sees Pending Payment

**Steps:**
1. Logout (if logged in as student)
2. Login with accounting user (email: accounting@ccdi.edu or second admin user)
3. Go to http://localhost:8000/approvals

**Expected Result:**
- ✅ Page displays
- ✅ Shows at least one pending approval
- ✅ Shows:
  - Student name (from transaction metadata)
  - Amount: 1000
  - Payment Method: Cash/Check
  - Payment Term: First Term
  - Submitted: [timestamp]

**If Payment Not Visible:**
- Check: Is accounting user's is_active = 1?
- Check: Does payment_approval workflow exist?
  ```php
  php artisan tinker
  >>> Workflow::where('type', 'payment_approval')->first()
  ```
- If missing: `php artisan db:seed --class=PaymentApprovalWorkflowSeeder`

---

### Test 3: Accounting Approves Payment

**Steps:**
1. On /approvals page, click the pending payment row
2. Review payment details on the approval detail page
3. Click "Approve Payment" button
4. (Optional) Add comments
5. Click "Approve"

**Expected Result - Immediate:**
- ✅ Page redirects to /approvals
- ✅ Approval status changes to "approved"
- ✅ No longer in pending list

**Expected Result - Backend:**
```sql
-- Check approval was marked approved
SELECT * FROM workflow_approvals WHERE id = 1;
-- Expected: status = 'approved', approved_at = NOW()

-- Check transaction is now paid
SELECT * FROM transactions WHERE status = 'paid';
-- Expected: reference = 'PAY-XXXXXXXX', status = 'paid'

-- Check payment term balance was updated
SELECT * FROM student_payment_terms ORDER BY id DESC LIMIT 1;
-- Expected: balance decreased by 1000
```

---

### Test 4: Student Sees Payment Processed

**Steps:**
1. Logout
2. Login as student (who submitted payment)
3. Go to http://localhost:8000/student/account

**Expected Result:**
- ✅ Payment appears in "Payment History" section
- ✅ Status shows "paid" or "completed"
- ✅ Amount and date are correct
- ✅ Outstanding balance decreased accordingly

---

## Troubleshooting

### Payment Submission Fails

**Error: "No active payment_approval workflow found"**
```bash
# Fix: Create the workflow
php artisan db:seed --class=PaymentApprovalWorkflowSeeder
```

**Error: "No approvers found for workflow step"**
```bash
# There are no active accounting or admin users
# Create one:
php artisan tinker
>>> User::create(['email' => 'accounting@ccdi.edu', 'role' => 'accounting', 'is_active' => true, ...])
```

### Accounting Can't See Pending Approvals

**Cause 1: User not in accounting role**
```bash
php artisan tinker
>>> $user = User::find(USER_ID);
>>> $user->role  # Should be 'accounting' or 'admin'
```

**Cause 2: Payment not in workflow yet**
```bash
php artisan tinker
>>> Transaction::where('status', 'awaiting_approval')->whereDoesntHave('workflowInstances')->count()
# If > 0, run: php artisan backfill:payment-approvals
```

### Approval Doesn't Appear on Detail View

**Check:**
- Does WorkflowApproval exist?
  ```php
  WorkflowApproval::where('status', 'pending')->get()
  ```
- Is the accounting user the assigned approver_id?
  ```php
  WorkflowApproval::where('approver_id', $accountingUserId)->first()
  ```

---

## Database Queries for Verification

```php
// Test in tinker (php artisan tinker)

// 1. Workflow exists and is active
Workflow::where('type', 'payment_approval')->first()

// 2. Pending payments exist
Transaction::where('status', 'awaiting_approval')->count()

// 3. Workflow instances created
WorkflowInstance::where('current_step', 'Accounting Verification')->count()

// 4. Pending approvals visible
WorkflowApproval::where('status', 'pending')->with('workflowInstance.workflow')->get()

// 5. Accounting users exist
User::accounting()->where('is_active', true)->get(['email', 'id'])

// 6. Approver assignment
WorkflowApproval::where('status', 'pending')
    ->with('workflowInstance.workflow')
    ->first()
    ->approver_id  // Should match an active accounting/admin user
```

---

## Success Checklist

After running tests, verify:

- [ ] Student can submit payment without errors
- [ ] Transaction created with status='awaiting_approval'
- [ ] WorkflowApproval record created with status='pending'
- [ ] Accounting user sees payment on /approvals page
- [ ] Accounting can click to view details
- [ ] Accounting can approve payment
- [ ] After approval: Transaction status → 'paid'
- [ ] After approval: StudentPaymentTerm.balance decreased
- [ ] Student sees payment in payment history as completed

**All checks passing?** ✅ Approval system is working correctly.
