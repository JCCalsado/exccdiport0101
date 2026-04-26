# ⚡ Quick Start - Create Your First Notification (5 Minutes)

## 🎯 Goal
Send a payment reminder to specific student: jcdc742713@gmail.com

---

## Step 1: Navigate to Notifications
```
1. Log in as Admin
2. Click "Admin Dashboard" (or go to /admin/dashboard)
3. Find "Notifications" in sidebar menu
4. Click "Create Notification" button
```

---

## Step 2: Fill in Basic Info (30 seconds)

Fill in these 3 fields:

| Field | Example |
|-------|---------|
| **Title** | `Final Tuition Payment Reminder` |
| **Message** | `Your remaining balance is ₱25,500. Please submit payment by March 15, 2026. Contact accounting@school.edu for assistance.` |
| **Start Date** | Today's date |

---

## Step 3: Select Target Student (1 minute)

```
1. Under "Who should see this?" select "All Students"
2. Scroll down to "Send to Specific Student (Optional)"
3. In the search box, type: jcdc742713@gmail.com
4. Click on the student name in the dropdown
5. You'll see: "John Doe (jcdc742713@gmail.com)" selected
```

---

## Step 4: Set Date Range (30 seconds)

```
1. Start Date: Today (already filled by default)
2. End Date: March 15, 2026 (or any deadline)
   - Leave empty if you want it to show indefinitely
```

---

## Step 5: ⚠️ ACTIVATE THE NOTIFICATION (CRITICAL!)

**This is the most important step!**

```
1. Look at RIGHT SIDE of form
2. Find the BIG GREEN TOGGLE BUTTON
3. Click it - it should turn GREEN
4. Status below should say "✓ Notification Active"
5. Message will say "Students will see this notification"
```

**Without this step, students won't see anything!**

---

## Step 6: Check Preview (30 seconds)

```
1. Look at the preview card on the right
2. Verify it shows:
   - Your title ✓
   - Your message ✓
   - "For: jcdc742713@gmail.com" ✓
   - Active dates ✓
```

---

## Step 7: Save & Go Live (10 seconds)

```
1. Scroll to bottom of form
2. Click "Create Notification" (blue button)
3. You'll see success message
4. 🎉 Done! Notification is now ACTIVE
```

---

## ✅ Verify It Works

**For Admin:**
1. Go to Notifications list
2. Find your notification at top (sorted by newest)
3. Status should show as "Active"

**For Student:**
1. Login as jcdc742713@gmail.com
2. Go to Dashboard
3. At the top, you should see a **blue banner** with your notification
4. Click to read full message

---

## 🔄 What Happens Next?

### Timeline

| Event | What Happens |
|-------|--------------|
| **Now** | Student sees notification on dashboard |
| **Until 3/15** | Notification stays visible |
| **Student Pays** | Notification auto-disappears when balance = 0 |
| **After 3/15** | Notification auto-hides (end date passed) |

### Auto-Completion Example

```
Scenario: Student makes payment of ₱25,500

Timeline:
1. ✓ Admin records payment in system
2. ✓ Student balance becomes ₱0
3. ✓ System auto-detects zero balance
4. ✓ Notification marked as "complete"
5. ✓ When student loads dashboard next time
6. ✓ Notification disappears
7. ✓ Dashboard looks clean - only active notif visible
```

**No manual work needed!** It happens automatically.

---

## 🛠️ Common Tasks

### I want to send to ALL students (not just one)

```
1. Under "Who should see this?" select "All Students"
2. DON'T select a specific student
3. Leave search box empty
4. Save - notification goes to all students
```

### I need to edit the notification

```
1. Open Notifications list
2. Find your notification
3. Click it to view
4. Click edit button
5. Make changes (toggle, message, dates, etc.)
6. Click "Update Notification"
```

### I want to disable notification without deleting

```
1. Open notification
2. Click the GREEN toggle to turn it GRAY
3. Save
4. Status changes to "Inactive"
5. Students won't see it anymore
6. You can turn it back on later
```

### I want to delete notification completely

```
1. Open notification
2. Find delete button (usually at bottom)
3. Confirm deletion
4. Notification is permanently removed
```

---

## ⚠️ Important Notes

### ✅ DO
- ✓ Click the ENABLE toggle (most common mistake!)
- ✓ Include clear payment amount
- ✓ Set realistic deadline
- ✓ Use professional tone
- ✓ Test by viewing as student

### ❌ DON'T
- ✗ Forget to enable the notification (won't work!)
- ✗ Type end date before start date
- ✗ Make message too long (keep it concise)
- ✗ Delete notification before it's supposed to end

---

## 🆘 Troubleshooting

### "Student can't see their notification"

**Checklist:**
- [ ] Is the GREEN toggle button ACTIVE? (Not gray!)
- [ ] Did you click "Create Notification" to save?
- [ ] Is today's date after the start date?
- [ ] Is today's date before the end date?
- [ ] Is the student logged in? (Not as different user)
- [ ] Did you refresh the page?

**Fix:**
1. Go to notification
2. Check toggle is GREEN
3. Go to Student Dashboard
4. Hard refresh: Ctrl+Shift+R (Windows) or Cmd+Shift+R (Mac)

### "I created it but changed my mind"

**Solution:**
- Click the toggle to turn it GRAY (Inactive)
- Save
- Notification disappears from student dashboards
- You can re-activate later if needed

### "Notification isn't disappearing after payment"

This happens automatically when balance hits 0, but may take a few moments.

**Workaround:**
- Have student refresh dashboard (Ctrl+Shift+R)
- Give system 5 minutes to process

---

## 🎓 Learning Path

1. **Day 1**: Create notification for all students (system announcement)
2. **Day 2**: Create notification for specific student (individual reminder)
3. **Day 3**: Create phased payment reminders with different end dates
4. **Day 4**: Experiment with disabling/re-enabling notifications
5. **Day 5**: Integrate into weekly admin workflow

---

## 💡 Best Practices

### Message Format

```
✓ GOOD:
"Your remaining balance is ₱25,500.
Payment due: March 15, 2026
Payment portal: portal.school.edu
Questions? Email: accounting@school.edu"

✗ AVOID:
"PAY YOUR FEES NOW!!!"
(All caps are hard to read and look aggressive)
```

### Timing

```
✓ GOOD:
- Send reminder 1-2 weeks before deadline
- Send to all who have balance > 0
- Auto-hide when payment made

✗ AVOID:
- Sending on same day as deadline (too late)
- Keeping old notifications active
- Leaving notifications on after deadline passes
```

### Targeting

```
✓ GOOD:
- All Students: "Extended deadline notice"
- Specific Student: "Personal payment reminder"
- Different dates: "Phase 1, Phase 2, Phase 3 payments"

✗ AVOID:
- Mixing confidential info in all-student notification
- Forgetting to activate specific student notification
- Creating duplicate notifications for same student
```

---

## 📱 Testing on Mobile

Notifications are fully responsive:

```
Desktop: Blue banner, full message visible
Tablet: Responsive layout, touch-friendly
Mobile: Stacked layout, full message readable
```

Test it:
1. Create notification
2. Open on desktop - verify looks good
3. Open on mobile - verify still readable
4. Verify message isn't cut off

---

## 🚀 You're Ready!

You now have everything you need to:
- ✓ Create notifications
- ✓ Target specific students
- ✓ Set activation dates
- ✓ Monitor active notifications
- ✓ Auto-complete on payment

**Next step:** Create your first notification right now!

---

*Last updated: February 20, 2026*
*All features tested and ready for production*
