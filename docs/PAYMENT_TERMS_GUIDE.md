# Payment Terms Conversion to 5-Term Structure

## Overview
This guide explains how to convert your student payment terms to a 5-term structure with specific percentages:

- **Upon Registration**: 42.15% (Due at enrollment)
- **Prelim**: 17.86% (Due at week 6)
- **Midterm**: 17.86% (Due at week 12)
- **Semi-Final**: 14.88% (Due at week 15)
- **Final**: 7.26% (Due at week 18)

---

## Available Commands

### 1. Convert to 5-Term Structure

#### Preview Changes (Dry Run)
Before making any changes, preview what will be converted:

```bash
php artisan payment-terms:convert-to-five --dry-run
```

**Output Example:**
```
📋 Converting payment terms to 5-term structure...
Percentages: Upon Registration (42.15%), Prelim (17.86%), Midterm (17.86%), Semi-Final (14.88%), Final (7.26%)

⚠️  DRY RUN MODE - No changes will be made

Found 45 assessments to process

[============================] 100% (45/45)

✅ DRY RUN COMPLETE - Would convert 45 assessments
📊 Would create 225 payment terms

Run without --dry-run flag to apply changes:
php artisan payment-terms:convert-to-five
```

#### Apply Changes
Convert all payment terms to the 5-term structure:

```bash
php artisan payment-terms:convert-to-five
```

**Output Example:**
```
📋 Converting payment terms to 5-term structure...
Percentages: Upon Registration (42.15%), Prelim (17.86%), Midterm (17.86%), Semi-Final (14.88%), Final (7.26%)

Found 45 assessments to process

[============================] 100% (45/45)

✅ Successfully converted 45 assessments to 5-term payment structure
📊 Total payment terms created: 225
```

---

### 2. Apply Payment Carryover Logic

#### Preview Carryover (Dry Run)
Preview how balances will carry from term to term:

```bash
php artisan payment-terms:apply-carryover --dry-run
```

**Output Example:**
```
📋 Applying payment carryover logic...
Unpaid balances will carry forward to the next term automatically.

⚠️  DRY RUN MODE - No changes will be made

Found 45 assessments to process

[============================] 100% (45/45)

✅ DRY RUN COMPLETE
📊 Would process 225 terms
💰 Would carry over: ₱125,450.75
```

#### Apply Carryover
Enable automatic balance carryover across terms:

```bash
php artisan payment-terms:apply-carryover
```

**Output Example:**
```
📋 Applying payment carryover logic...
Unpaid balances will carry forward to the next term automatically.

Found 45 assessments to process

[============================] 100% (45/45)

✅ Successfully applied carryover logic to 45 assessments
📊 Terms processed: 225
💰 Total balance carried over: ₱125,450.75
```

---

## Payment Carryover System

### What is Payment Carryover?

Payment carryover is an automatic system where **unpaid balances from one term are carried forward to the next term** until fully settled.

### How It Works

**Example Scenario:**
A student's total assessment is ₱1,000.00 distributed across 5 terms:

| Term | Original Amount | Paid | Balance | Status |
|------|-----------------|------|---------|--------|
| Upon Registration | ₱421.50 | ₱300.00 | **₱121.50** | Partial |
| Prelim | ₱178.60 | ₱121.50* | ₱178.60 | Pending |
| Midterm | ₱178.60 | ₱0.00 | ₱178.60 | Pending |
| Semi-Final | ₱148.80 | ₱0.00 | ₱148.80 | Pending |
| Final | ₱72.50 | ₱0.00 | ₱72.50 | Pending |

**Carryover Flow:**
```
Upon Registration Balance (₱121.50)
        ↓
    Carries to Prelim
        ↓
Prelim now has: ₱178.60 + ₱121.50 = ₱300.10
        ↓
If still not paid, continues to next terms
```

### Key Features

✓ **Automatic**: Balances carry automatically across terms
✓ **Transparent**: Visual indicators show carryover amounts
✓ **Flexible**: Students can pay any remaining balance at any time
✓ **Priority-Based**: Earlier unpaid terms are settled first
✓ **Until Settled**: Carryover continues until total assessment is fully paid

### Carryover Payment Priority

When a student makes a payment, the system applies it in this order:

1. **First**: Settle balances carried from earlier terms
2. **Then**: Settle the current term's original amount
3. **Finally**: Carry any remaining unpaid balance to next term

**Payment Example:**
- Student has ₱300 balance in "Upon Registration" and ₱178.60 in "Prelim"
- Student pays ₱400
```
Applied: ₱300 → Upon Registration (now paid) ✓
Applied: ₱100 → Prelim (out of ₱178.60)
Remaining: ₱78.60 in Prelim carries to Midterm
```

---

## What The Command Does

1. **Finds all student assessments** in the database
2. **Deletes old payment terms** for each assessment (preserving financial data)
3. **Creates new 5-term payment structure** with calculated amounts based on percentages
4. **Calculates due dates** based on school year:
   - Upon Registration: Week 0 (at enrollment)
   - Prelim: Week 6
   - Midterm: Week 12
   - Semi-Final: Week 15
   - Final: Week 18
5. **Handles rounding** automatically (last term gets remainder to ensure accuracy)

---

## Implementation Steps

### Step 1: Test 5-Term Conversion with Dry Run
```bash
php artisan payment-terms:convert-to-five --dry-run
```
Review the output to ensure everything looks correct.

### Step 2: Backup Your Database (Optional but Recommended)
```bash
php artisan backup:run
# or manually backup your database
```

### Step 3: Run the Conversion
```bash
php artisan payment-terms:convert-to-five
```

### Step 4: Test Carryover with Dry Run
```bash
php artisan payment-terms:apply-carryover --dry-run
```
This shows how balances will be carried across terms.

### Step 5: Apply Carryover Logic
```bash
php artisan payment-terms:apply-carryover
```
This enables automatic balance carryover across all payment terms.

### Step 6: Verify the Changes
Check the database to confirm payment terms were created correctly:
```bash
php artisan tinker
>>> $term = \App\Models\StudentPaymentTerm::first();
>>> $term->toArray();
>>> // Check carryover remarks
>>> \App\Models\StudentPaymentTerm::whereNotNull('remarks')->get();
```

### Step 7: Update Vue Components (Optional)
If you want to display the payment breakdown in the student dashboard, add the component:

```vue
<script setup>
import PaymentTermsBreakdown from '@/components/PaymentTermsBreakdown.vue'
import { ref } from 'vue'

// Your data here
const terms = ref(paymentTerms) // from your API/props
const totalAssessment = ref(1000) // total amount
</script>

<template>
  <PaymentTermsBreakdown 
    :terms="terms" 
    :total-assessment="totalAssessment" 
  />
</template>
```

---

## Command Reference

### 5-Term Conversion

**Signature:**
```
payment-terms:convert-to-five {--dry-run : Show what would be changed without making changes}
```

**Dry run (safe, no changes):**
```bash
php artisan payment-terms:convert-to-five --dry-run
```

**Apply changes:**
```bash
php artisan payment-terms:convert-to-five
```

---

### Payment Carryover

**Signature:**
```
payment-terms:apply-carryover {--dry-run : Show what would be changed without making changes}
```

**Dry run (safe, no changes):**
```bash
php artisan payment-terms:apply-carryover --dry-run
```

**Apply changes:**
```bash
php artisan payment-terms:apply-carryover
```

---

## Database Changes Made

### Original Structure (Example - 3 terms)
```
- Prelim: ₱333.33 (30 days)
- Midterm: ₱333.33 (60 days)
- Final: ₱333.34 (90 days)
```

### New Structure (5 terms with percentages and carryover)
```
- Upon Registration: ₱421.50 (42.15%) - Due at enrollment
  Balance: ₱121.50 (not paid) → CARRIES TO Prelim
  
- Prelim: ₱178.60 (17.86%) + ₱121.50 (carryover) = ₱300.10 effective balance
  Due at week 6
  
- Midterm: ₱178.60 (17.86%) (+ carryover if Prelim not paid)
  Due at week 12
  
- Semi-Final: ₱148.80 (14.88%) (+ carryover if earlier terms not paid)
  Due at week 15
  
- Final: ₱72.50 (7.26%) (+ carryover if earlier terms not paid)
  Due at week 18
```

### What Changes in Database

**Added Fields:**
- `term_order`: Order of the payment term (1-5)
- `remarks`: Tracks carryover information (e.g., "Balance carries to next term")

**Updated Behavior:**
- `balance` column now includes carried-over amounts from previous terms
- `status` tracks payment state (pending, partial, paid, overdue)
- Payment carryover is tracked in the `remarks` field

### Example After Carryover Applied

**Student: John Doe, Assessment Total: ₱1,000**

| ID | Term | Order | Original Amount | Remarks | Balance |
|----|------|-------|-----------------|---------|---------|
| 1 | Upon Registration | 1 | ₱421.50 | Paid | ₱0.00 |
| 2 | Prelim | 2 | ₱178.60 | Balance carries to next | ₱178.60 |
| 3 | Midterm | 3 | ₱178.60 | ₱178.60 carried from Prelim | ₱357.20 |
| 4 | Semi-Final | 4 | ₱148.80 | ₱357.20 carried from Midterm | ₱506.00 |
| 5 | Final | 5 | ₱72.50 | ₱506.00 carried from Semi-Final | ₱578.50 |

---

## Troubleshooting

### Command Not Found
If you get "Command not found", ensure the file exists:
```bash
ls app/Console/Commands/ConvertPaymentTermsToFive.php
ls app/Console/Commands/ApplyPaymentCarryover.php
```

### Carryover Not Applied
Make sure to run the carryover command after converting to 5-term structure:
```bash
# First: Convert to 5-term structure
php artisan payment-terms:convert-to-five

# Then: Apply carryover logic
php artisan payment-terms:apply-carryover
```

### Database Errors
If you encounter foreign key errors:
1. Check that all assessments have valid `user_id`
2. Verify the `student_assessments` table exists and has data
3. Run dry-run first to diagnose issues:
   ```bash
   php artisan payment-terms:convert-to-five --dry-run
   php artisan payment-terms:apply-carryover --dry-run
   ```

### Verify Carryover Applied
Check if carryover marks were applied:
```bash
php artisan tinker
>>> \App\Models\StudentPaymentTerm::whereNotNull('remarks')->count();
>>> // Should return number of terms with carryover
>>> \App\Models\StudentPaymentTerm::where('remarks', 'LIKE', '%carries%')->get();
```

### Rollback Changes
To undo changes, restore from backup or manually delete the new terms:
```bash
php artisan tinker
>>> \App\Models\StudentPaymentTerm::truncate(); // WARNING: Deletes all terms
>>> // OR delete by date
>>> \App\Models\StudentPaymentTerm::where('created_at', '>', now()->subHours(1))->delete();
```

---

## Files Created/Modified

1. **Model**: `app/Models/StudentPaymentTerm.php`
   - Represents a payment term for an assessment
   - Includes status tracking and carryover helpers
   
2. **Migration**: `database/migrations/2026_02_14_000001_create_student_payment_terms_table.php`
   - Creates the `student_payment_terms` table with all necessary columns
   
3. **Command - Convert to 5-Terms**: `app/Console/Commands/ConvertPaymentTermsToFive.php`
   - Main conversion command with dry-run capability
   - Converts all payment terms to 5-term structure with percentages
   
4. **Command - Apply Carryover**: `app/Console/Commands/ApplyPaymentCarryover.php`
   - Enables automatic balance carryover across terms
   - Shows how unpaid balances flow to next terms
   
5. **Service**: `app/Services/PaymentCarryoverService.php`
   - Handles all carryover logic and payment distribution
   - Provides methods for applying payments, checking balances, etc.
   
6. **Vue Component**: `resources/js/components/PaymentTermsBreakdown.vue`
   - Display component showing 5-term breakdown with percentages
   - Shows carryover flow with visual indicators
   - Displays payment carryover policy information

---

## Payment Structure Visualization

The Vue component displays:
```
NEW PAYMENT BREAKDOWN STRUCTURE:
Total Assessment: ₱00.00

Upon Registration: ₱00.00 (42.15%)
Prelim:           ₱00.00 (17.86%)
Midterm:          ₱00.00 (17.86%)
Semi-Final:       ₱00.00 (14.88%)
Final:            ₱00.00 ( 7.26%)
─────────────────────────────────
TOTAL:            ₱00.00 (100%)
```

Each term shows:
- Term name and due date
- Amount in PHP currency
- Percentage of total
- Payment status (Pending, Paid, Partial, Overdue)
- Remaining balance
- Overall payment progress bar

---

## Important Notes

⚠️ **Before Running the Commands:**
- Backup your database
- Test with `--dry-run` first for both commands
- Ensure all assessments have required fields (`user_id`, `school_year`)
- Understand the carryover impact on student payment schedules

✅ **After Running the Commands:**
- Verify payment terms were created correctly
- Check that carryover marks were applied to terms
- Verify student dashboards for updated payment schedules
- Notify students of the new payment structure and carryover policy
- Update student communication materials with new due dates and carryover information

📋 **Carryover Notes:**
- Balances automatically carry regardless of term status
- Carryover doesn't require manual intervention
- Students can see carried-over amounts in the payment breakdown component
- Payment carryover is tracked in the `remarks` field of `student_payment_terms`
- The `PaymentCarryoverService` handles payment distribution across terms

---

## Verification Checklist

After running both commands, verify:

- [ ] `php artisan payment-terms:convert-to-five` completed successfully
- [ ] `php artisan payment-terms:apply-carryover` completed successfully
- [ ] Payment terms count is correct (should be: number of assessments × 5)
- [ ] All terms have `term_order` values from 1-5
- [ ] Terms with unpaid balances have carryover remarks
- [ ] Student dashboard displays 5-term structure correctly
- [ ] PaymentTermsBreakdown component shows carryover flow
- [ ] Payment processing prioritizes earlier unpaid terms first

---

## Support

For questions or issues:
1. Check the application logs: `storage/logs/`
2. Review command output for error messages
3. Use `--dry-run` to diagnose issues without making changes
4. Check the verification checklist above
5. Review the PaymentCarryoverService in `app/Services/PaymentCarryoverService.php`
6. Verify carryover marks in the database using the verification queries
