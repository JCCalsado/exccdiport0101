# Fee Breakdown Quick Reference Guide
## For CCDI Administrators & Staff

**Last Updated:** March 26, 2026  
**Quick Links:** [Full Documentation](FEE_BREAKDOWN_TRANSPARENCY.md) | [Before/After](FEE_BREAKDOWN_BEFORE_AFTER.md)

---

## 🎯 5-Minute Overview

The **Fee Breakdown** section in Student Fee Details now shows exactly how a student's assessment is calculated.

**What's new:**
- ✅ Calculation formula displayed at top
- ✅ Expandable sections for each fee component
- ✅ Subject-level detail visible
- ✅ Verification checkmark confirms accuracy

---

## 📍 How to Access

1. **Navigate:** Students Archive → Select Student
2. **View:** Fee Details page → "Fee Breakdown" card (top section)
3. **Interact:** Click section headers (▼) to expand/collapse

---

## 📊 What You'll See

### Section 1: Tuition Fees
**Collapsed:** Shows count of subjects, total units, subtotal  
**Expanded:** Each subject with:
- Course code and name
- Unit count
- Calculation: units × ₱364.00 = amount
- Individual subject total

**Use Case:** Answer "How much is tuition?"
```
Student: "Why ₱10,374 for tuition?"
You: [Expand tuition section] "You're enrolled in 10 subjects 
     totaling 28.5 units. At ₱364 per unit, that's ₱10,374."
```

### Section 2: Laboratory Fees
**Collapsed:** Shows lab subject count, subtotal  
**Expanded:** Each lab subject with:
- Subject code and name
- Fixed lab fee: ₱1,656.00
- Total for that subject's lab

**Use Case:** Explain lab costs
```
Student: "Why such a high lab fee?"
You: [Expand lab section] "Five of your subjects have lab components.
     Each is ₱1,656, so 5 × ₱1,656 = ₱8,280."
```

### Section 3: Miscellaneous Fees
**Collapsed:** Says "Institutional & support services", shows ₱6,956.00  
**Expanded:** Three subcategories with all 15 items:
1. **Academic Services** (₱1,500)
   - Registration, LMS, Library
2. **Student Life & Activities** (₱2,325)
   - Athletic, PRISAA, ID, Publications, etc.
3. **Support Services** (₱975)
   - Medical, Insurance, Cultural Events, Maintenance

**Use Case:** Justify mandatory fees
```
Student: "What's this ₱6,956 miscellaneous?"
You: [Expand misc section] "These are mandatory institutional fees:
     • Academic: Registration, online learning platform, library = ₱1,500
     • Student life: Sports, clubs, publications, activities = ₱2,325
     • Support: Health services, insurance, cultural programs = ₱975
     Total = ₱6,956 per semester"
```

### Verification Indicator
**Green ✓:** "Breakdown Verified — All components sum to total assessment" = Everything checks out  
**Red ⚠:** "Discrepancy Detected" = Something wrong, contact IT

**Use Case:** Audit confidence
```
"This student's fees have been verified to match the official schedule.
No discrepancies detected."
```

---

## 🧮 The Formula

### For Students Taking Standard Subjects
```
Price = (Total Units × ₱364) + (Lab Subjects × ₱1,656) + ₱6,956

Example: 1st Year with 10 subjects, 5 labs
       = (28.5 × ₱364) + (5 × ₱1,656) + ₱6,956
       = ₱10,374 + ₱8,280 + ₱6,956
       = ₱25,610
```

### Key Rates (2025-2026)
- **Tuition:** ₱364 per unit (up from ₱317 in AY 2024-25 — 15% increase)
- **Lab Fee:** ₱1,656 per subject (fixed, doesn't vary by number of lab units)
- **Institutional Fees:** ₱6,956 (fixed every semester for all students)

---

## ❓ Frequently Asked Questions

**Q: Why does one student have ₱25,610 and another ₱28,900?**
A: Different number of enrolled subjects/units. Use the formula shown in the expansion.

**Q: Can we give one student a lower fee?**
A: No. The system uses official rates only. Scholarships/waivers are handled at payment level.

**Q: What if a student drops a subject?**
A: The assessment needs to be recreated. Contact IT. New assessment will have fewer units.

**Q: Is the ₱6,956 the same for all students?**
A: Yes. Those are mandatory institutional fees, fixed per semester.

**Q: Why does the verification show ⚠ (red)?**
A: The breakdown doesn't match official rates. This shouldn't happen with new assessments.  
Contact IT with student details.

**Q: How do lab subjects get identified?**
A: The Subjects database marks which courses have labs. Admins create assessments marking labs.

**Q: Can students see this expanded breakdown?**
A: Yes! They can log into their Student Dashboard and view it themselves.

---

## 💼 Common Staff Tasks

### Task 1: Answer "Why is my fee X amount?"
1. Open student's Fee Details
2. Expand Tuition section → show subjects and calculation
3. Expand Lab section (if applicable) → show lab subjects
4. Expand Misc section → show where the fixed fees go
5. Show green ✓ to confirm accuracy

**Time:** 2 minutes

### Task 2: Verify Assessment Accuracy
1. Open student's Fee Details
2. Check for green ✓ indicator
3. If green: Fee is accurate, no further action needed
4. If red ⚠: Something wrong, contact IT with student name/ID

**Time:** 30 seconds per student

### Task 3: Explain Semester Costs to Parent
1. Open student Fee Details (can share link)
2. Point to calculation formula: "28.5 units × ₱364 + labs + fees"
3. Expand each section to justify
4. Use numbers to show official rates are being applied

**Time:** 5 minutes

### Task 4: Generate Audit Report
1. For random sample of students:
2. Check each Fee Details page
3. Verify green ✓ shows on all
4. If any red ⚠, note for follow-up
5. Document: "Sampled 30 students, all verified correct"

**Time:** 15 minutes for 30 students

### Task 5: Field Parent Objection "This is too expensive!"
1. Open Fee Details, show breakdown
2. Explain each section is MANDATORY
3. Show calculation is OFFICIAL RATE (not arbitrary)
4. Show verification ✓ proves accuracy
5. "Every student in 1st Year pays exactly this"

**Reference:** Line items prove fees are legitimately required

---

## 🔧 Troubleshooting

### Problem: Student says breakdown doesn't match what they understand
**Solution:**
1. Expand each section
2. Verify calculation shown matches their enrolled subjects
3. Confirmation often resolves confusion

### Problem: Two students with same subjects have different fee totals
**Possible Causes:**
- Different lab subjects enrolled (check lab section)
- Different number of subjects (count tuition items)
- Credits applied (payment level, not assessment level)

**Resolution:** Make sure you're comparing apples-to-apples

### Problem: Fee total doesn't match official rate
**Your Action:** 
- Take screenshot with student name + total shown
- Note the green ✓ or ⚠ indicator
- Email to: [IT contact]
- Subject: "Fee Breakdown Discrepancy - [StudentName]"

**Do not:** Manually change fees in database

---

## 📈 For Accounting Department

### Monthly Reconciliation Process

```
1. Request: List of all active students with assessments (AY 2025-2026)
2. For each student:
   - Calculate: (units × 364) + (labs × 1656) + 6956
   - Compare to: Total Assessment shown in Fee Details
   - Mark: ✓ if match, ⚠ if discrepancy
3. Report discrepancies to IT
4. Sign off: "Verified X students, Y discrepancies flagged"
```

### Budget/Rate Verification
- **Tuition Rate:** ₱364/unit ✓ (official schedule)
- **Lab Rate:** ₱1,656/subject ✓ (official schedule)
- **Misc Total:** ₱6,956 ✓ (detailed breakdown available in expansion)
- **Year:** 2025-2026 (verify if year changes)

### Export for External Audit
The Fee Details page can be exported as PDF (button top-right).
PDF includes:
- Full breakdown with all sections visible
- All 15 miscellaneous items listed
- Verification indicator
- Student details

---

## 📞 Support

**Issue Type** | **Action** | **Contact**
---|---|---
Fee shows ⚠ discrepancy | Take screenshot, note student ID | IT Department
Student questions breakdown | Use expanded sections to explain | No escalation needed
Want to modify a fee/rate | Use config/fees.php, IT deploys | IT Department + Admin
Need fee breakdown data export | Use PDF export function | No escalation needed
Curriculum/unit question | Check Subjects table or curriculum doc | Registrar Office

---

## 📋 Checklists

### Before Talking to a Fee-Complaining Student
- [ ] Open their Fee Details page
- [ ] Check for green ✓ or red ⚠
- [ ] Expand Tuition section (ready to show subjects)
- [ ] Have official rate (₱364) memorized or visible
- [ ] Know difference between misc and other categories

### Before Approving/Processing Assessment
- [ ] Check green ✓ shows
- [ ] Verify calculation formula matches subjects enrolled
- [ ] Count subject matches what student claims
- [ ] Lab count correct
- [ ] Total reasonable for their year level

### Before End-of-Month Reconciliation
- [ ] System stable (no ⚠ indicators on sample check)
- [ ] All students for the month have assessments
- [ ] Fee rates match official schedule
- [ ] Sample of 10-20 students verified before full report

---

## 🎓 Key Takeaways

1. **Everything is transparent now** — students see exactly how fees are calculated
2. **Use the expanded sections** — they're your best tool for explaining costs
3. **Green ✓ means accurate** — no need to investigate if it shows
4. **Red ⚠ means escalate** — contact IT immediately
5. **Official rates are fixed** — you can't change them without admin approval

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | Mar 26, 2026 | Initial release; expandable sections, verification |

---

**This is a living document. Updates will be posted here.**  
**Questions? Ask at staff meeting or email: [admin contact]**

---

*Ready to see it in action? Navigate to any student's Fee Details page.*
