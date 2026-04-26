# AUDIT FIX — MIGRATION READINESS CHECKLIST

**Date:** March 13, 2026  
**Status:** ✅ READY FOR MIGRATION

---

## ✅ CRITICAL FIXES APPLIED

### 1. StudentPaymentTerm.user_id Migration Safety ✅

**Fixed:**
- ❌ ~~StudentFeeController.createPaymentTerms()~~ → ✅ **Removed `user_id` write**
  - File: `app/Http/Controllers/StudentFeeController.php` line ~1192
  - Change: Removed `'user_id' => $userId,` from StudentPaymentTerm::create()
  - Impact: Will not throw MySQL error when migration drops column

- ❌ ~~StudentPaymentService.getTotalOutstandingBalance()~~ → ✅ **Fixed query pattern**
  - File: `app/Services/StudentPaymentService.php` line ~297
  - Change: Changed from `StudentPaymentTerm::where('user_id', ...)` to `StudentPaymentTerm::whereHas('assessment', fn($q) => $q->where('user_id', ...))`
  - Impact: Balance queries will continue working after migration

### 2. Fee/Subject Dead Code Cleanup ✅

**All Fee model references in active controllers removed:**

| Controller | Line | Change | Status |
|-----------|------|--------|--------|
| StudentFeeController | 5 | Removed `use App\Models\Fee;` | ✅ |
| StudentAccountController | 8 | Removed `use App\Models\Fee;` | ✅ |
| AccountingDashboardController | 5 | Removed `use App\Models\Fee;` | ✅ |
| TransactionController | 3 | Removed `use App\Models\Fee;` | ✅ |
| StudentController | 7 | Removed `use App\Models\Fee;` | ✅ |
| StudentController | 54-62 | Removed `Fee::class` check | ✅ |
| StudentAccountController | 35 | Removed `Fee::active()` query | ✅ |
| AccountingDashboardController | 46-47 | Removed `Fee::count()/sum()` | ✅ |
| TransactionController | 486 | Removed `Fee::where()` query | ✅ |
| StudentFeeController | 372, 404, 479, 811 | Removed all `Subject::` queries | ✅ |

**All Subject model references in active controllers removed:**
- StudentFeeController - 4 references removed ✅

### 3. Database Consistency ✅
- StudentController line 114 hardcoded Fee::active()->sum() → ✅ Changed to `$validated['total_balance'] = 0;`
- All carryover operations wrapped in `DB::transaction()` ✅

---

## ⚠️ REMAINING (Not Critical for Migration, Cleanup Tasks)

These models/services are dead code but not queried by active controllers:

| Item | Status | Notes |
|------|--------|-------|
| Fee model | 🟡 Still exists | Not imported by any active controller |
| Subject model | 🟡 Still exists | Not imported by any active controller |
| FeeAssignmentService | 🟡 Still exists | Legacy service; routes disabled |
| Transaction.fee() relationship | 🟡 Still exists | Foreign key relationship (may cause issues if deleted) |
| StudentEnrollment.subject() relationship | 🟡 Still exists | Foreign key relationship (may cause issues if deleted) |
| resources/js/pages/Fees/ | 🟡 Still exists | Dead Vue pages; doesn't affect backend |
| resources/js/pages/Subjects/ | 🟡 Still exists | Dead Vue pages; doesn't affect backend |
| database/migrations/*fee* | 🟡 Still exists | Orphaned migrations for disabled feature |

**Decision:** These can be deleted **after** initial migration succeeds, as a follow-up cleanup task.

---

## 🚀 MIGRATION PROCEDURE (Safe Order)

```bash
# 1. Verify code is correct
php artisan config:clear
php artisan route:cache   # Should NOT error now (duplicate routes removed)

# 2. Run migration
php artisan migrate

# 3. Test critical code paths
php artisan artisan tinker
# Test this in tinker:
# $user = User::where('role', 'student')->first();
# $service = app(\App\Services\StudentPaymentService::class);
# $service->getTotalOutstandingBalance($user);  // Should not error

# 4. Create test student to verify StudentFeeController works
# Manually test: create new student assessment from admin UI
# Should create payment terms without errors

# 5. Commit changes
git add .
git commit -m "refactor: fix StudentPaymentTerm queries for safe user_id removal migration"
git push
```

---

## 🔴 DO NOT DO THESE (Yet)

❌ **Do not delete Fee model yet** — Transaction model has FK relationship  
❌ **Do not delete Subject model yet** — StudentEnrollment model has FK relationship  
❌ **Do not delete FeeAssignmentService yet** — May be referenced elsewhere  
❌ **Do not attempt** `php artisan migrate:rollback` without backupLet database relationships be cleaned up in a dedicated refactor task after migration confirms no errors.

---

## ✅ SUCCESS INDICATORS

After running `php artisan migrate`, verify:

1. Command completes without errors
2. `student_payment_terms.user_id` column no longer exists in DB
3. New student creation works (StudentFeeController creates payment terms)
4. Student balance calculations work (getTotalOutstandingBalance returns accurate values)
5. Payment submissions work (queries through assessment still function)

If all ✅, migration was successful!

---

## 📋 ADDITIONAL ISSUES FROM AUDIT

These were discovered but are **separate from the migration fix**:

1. **admin_notifications table rename** — Verify no stale references
2. **account_id column rename** — Verify all references updated  
3. **StudentController sets total_balance=0** — May trigger recalculation elsewhere; monitor for side effects

