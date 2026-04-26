# Payment Carryover Quick Reference

## What is Payment Carryover?

**Balance Carryover** automatically transfers unpaid amounts from one payment term to the next, ensuring students can pay in their preferred order without losing track of what they owe.

---

## Quick Commands

### Preview Carryover (Safe - No Changes)
```bash
php artisan payment-terms:apply-carryover --dry-run
```

### Apply Carryover to All Terms
```bash
php artisan payment-terms:apply-carryover
```

---

## How It Works - Visual Example

### Scenario: Student owes ₱1,000

| Step | Event | Balance Flow |
|------|-------|--------------|
| 1 | Upon Registration (₱421.50) - Only ₱300 paid | ₱121.50 unpaid → Carries to Prelim |
| 2 | Prelim (₱178.60) - Combined with carryover = ₱300.10 due | ₱300.10 total balance |
| 3 | Student pays ₱200 for Prelim | ₱100.10 balance → Carries to Midterm |
| 4 | Midterm (₱178.60) - Plus ₱100.10 carryover = ₱278.70 due | ₱278.70 total balance |
| 5 | Continues until fully paid | ↓ Until ₱0 balance |

---

## Payment Carryover Policy (Text for Communications)

> **Flexible Payment Plan with Automatic Carryover**
>
> Our 5-term payment structure allows students flexibility in managing their fees. If you don't fully pay one term's amount, the remaining balance automatically carries to the next term. This means:
>
> ✓ You can pay partially and continue to the next term
> ✓ Unpaid balances are carried forward automatically
> ✓ You only need to track your total remaining balance
> ✓ Pay whenever convenient - earlier or later terms
> ✓ Final balance must be settled by end of semester
>
> **Payment Priority**: When you make a payment, we apply it to your earliest unpaid amounts first, then carry any remainder to later terms.

---

## Database Tracking

### Where Carryover Info is Stored

The `student_payment_terms` table tracks carryover:

```sql
-- Check which terms have carryover
SELECT id, term_name, amount, balance, remarks 
FROM student_payment_terms 
WHERE remarks LIKE '%carries%' 
ORDER BY term_order;
```

### Sample Carryover Remarks

- `"Balance carries to next term"` - This term has unpaid balance
- `"₱121.50 carried from Upon Registration"` - Balance received from previous term

---

## Component Display

The `PaymentTermsBreakdown.vue` component shows carryover visually:

### Visual Elements

```
Upon Registration (₱421.50 @ 42.15%)
Status: Paid

↓ Carries to Prelim: ₱121.50

Prelim (₱178.60 @ 17.86%)
Status: Pending
Effective Balance: ₱300.10 (including carryover)
```

---

## Integration Notes

### For Controllers/Services

Use `PaymentCarryoverService` to handle payments:

```php
$service = new \App\Services\PaymentCarryoverService();

$result = $service->applyPayment($assessment, $amount);

if ($result['remaining_amount'] == 0) {
    // Payment fully applied
    $applicd = $result['applied_payments'];
}
```

### For Frontend Display

Import and use the component:

```vue
<PaymentTermsBreakdown 
  :terms="paymentTerms" 
  :total-assessment="totalAssessment" 
/>
```

---

## Carryover Rules

1. **Automatic**: No manual action needed
2. **Transparent**: Students see balances and carryover amounts
3. **Flexible**: Students can pay any time in any order
4. **Priority-Based**: Early terms are prioritized when paying
5. **Tracked**: All carryover is recorded in remarks field
6. **Until Settled**: Continues until all balance is ₱0.00

---

## FAQs

### Q: Will my unpaid balance disappear?
**A:** No. Unpaid balances are never lost. They automatically carry to the next term.

### Q: Can I pay later terms first?
**A:** The system prioritizes earlier unpaid amounts, but through the carryover system, paying any amount reduces your total balance.

### Q: What if I can only pay ₱500 this month?
**A:** Your ₱500 is applied to the earliest unpaid terms. Any remainder carries to the next terms.

### Q: Does carryover affect my GPA?
**A:** Carryover is purely for financial tracking. Academic records are separate.

### Q: When does the final balance need to be paid?
**A:** All balances must be settled by the end of the semester. Unpaid balances may prevent graduation.

---

## Implementation Checklist

- [ ] Run `php artisan payment-terms:apply-carryover --dry-run` to preview
- [ ] Run `php artisan payment-terms:apply-carryover` to apply
- [ ] Verify terms have carryover remarks in database
- [ ] Test PaymentTermsBreakdown component in student dashboard
- [ ] Communicate carryover policy to students
- [ ] Train accounting staff on carryover system
- [ ] Update student handbook/portal documentation

---

## Key Commands Recap

```bash
# Preview both conversions (safe)
php artisan payment-terms:convert-to-five --dry-run
php artisan payment-terms:apply-carryover --dry-run

# Apply both (makes changes)
php artisan payment-terms:convert-to-five
php artisan payment-terms:apply-carryover

# Check carryover in database
php artisan tinker
>>> \App\Models\StudentPaymentTerm::where('remarks', 'LIKE', '%carries%')->count();

# View sample carryover
>>> \App\Models\StudentPaymentTerm::where('remarks', 'LIKE', '%carries%')->first()->toArray();
```

---

## Support Files

- **Main Guide**: `PAYMENT_TERMS_GUIDE.md`
- **Command 1**: `app/Console/Commands/ConvertPaymentTermsToFive.php`
- **Command 2**: `app/Console/Commands/ApplyPaymentCarryover.php`
- **Service**: `app/Services/PaymentCarryoverService.php`
- **Component**: `resources/js/components/PaymentTermsBreakdown.vue`
- **Model**: `app/Models/StudentPaymentTerm.php`
- **Migration**: `database/migrations/2026_02_14_000001_create_student_payment_terms_table.php`

For detailed information, see the main `PAYMENT_TERMS_GUIDE.md` file.
