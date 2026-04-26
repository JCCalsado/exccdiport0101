# ACCOUNTING ROLE CAPABILITIES ANALYSIS

**Generated:** February 21, 2026  
**Analysis Date:** Current System State

---

## EXECUTIVE SUMMARY

The **Accounting** role is a **financial operations specialist** with:
- ✅ **10 accessible pages/features**
- ❌ **11 restricted features** (Admin-only)
- **Primary focus:** Student payment tracking, fee management, transaction recording

---

## ✅ WHAT ACCOUNTING CAN DO

### 1. **Dashboard & Analytics**
| Feature | Route | Capability |
|---------|-------|-----------|
| Accounting Dashboard | `/accounting/dashboard` | View financial stats, trends, student balances |
| Accounting Transactions | `/accounting/transactions` | View all transaction history filtered |
| Analytics | *Implied in Dashboard* | Payment trends, collection rates, revenue reports |
| Reports | *In Accounting/Pages* | Generate financial reports |

### 2. **Student Archive Management**
| Feature | Route | Capability |
|---------|-------|-----------|
| Student List | `/students` | View all students (archive) |
| Student Profile | `/students/{id}` | View individual student details |
| Student Edit | `/students/{id}/edit` | Edit student information |
| Student Workflow History | `/students/{id}/workflow-history` | View enrollment workflow history |
| Student Workflow Advance | `students/{id}/advance-workflow` (POST) | Advance student through workflow stages |

### 3. **Student Fee Management**
| Feature | Route | Capability |
|---------|-------|-----------|
| Fee Management Index | `/student-fees` | List all students with fee status |
| View Student Fees | `/student-fees/{user}` | View specific student's fee assessment & payment terms |
| Edit Student Fees | `/student-fees/{user}/edit` | Modify fee/assessment details |
| Create Assessment | `/student-fees/create` | Create new fee assessment for a student |
| Create Student | `/student-fees/create-student` | Manually enroll new student |
| Store Assessment | `student-fees/store` (POST) | Save new fee assessment |
| Store Student | `student-fees/store-student` (POST) | Save newly enrolled student |
| Record Payment | `student-fees/{user}/payments` (POST) | Record payment against specific student's fees |
| Export PDF | `/student-fees/{user}/export-pdf` | Download student fee summary as PDF |

### 4. **Fee Catalog Management**
| Feature | Route | Capability |
|---------|-------|-----------|
| Fee List | `/fees` | View fee types/catalog |
| Create Fee | `/fees/create` | Add new fee type |
| Edit Fee | `/fees/{id}/edit` | Modify fee definition |
| Delete Fee | `/fees/{id}` (DELETE) | Remove fee type |

### 5. **Transaction Management**
| Feature | Route | Capability |
|---------|-------|-----------|
| Transaction List | `/transactions` | View all transactions (admin view) |
| Create Transaction | `/transactions/create` | Create manual transaction entry |
| Store Transaction | `/transactions` (POST) | Save new transaction (✅ **CASH ONLY**) |
| View Transaction | `/transactions/{id}` | View transaction details |
| Delete Transaction | `/transactions/{id}` (DELETE) | Remove transaction record |

**Key:** Students can also pay via `/account/pay-now` route, but CANNOT use Cash (role-based validation).

### 6. **Subject Management**
| Feature | Route | Capability |
|---------|-------|-----------|
| Subject List | `/subjects` | View all subjects |
| Create Subject | `/subjects/create` | Add new subject |
| Edit Subject | `/subjects/{id}/edit` | Modify subject details |
| Delete Subject | `/subjects/{id}` (DELETE) | Remove subject |

### 7. **Workflow Management (NEW)**
| Feature | Route | Capability |
|---------|-------|-----------|
| View Workflows | `/accounting-workflows` | List all workflow instances |
| Create Workflow | `/accounting-workflows/create` | Create new workflow request |
| Store Workflow | `accounting-workflows` (POST) | Submit workflow for processing |
| View Workflow | `/accounting-workflows/{id}` | View workflow details |
| Update Workflow | `/accounting-workflows/{id}` (PUT) | Modify workflow status |
| Delete Workflow | `/accounting-workflows/{id}` (DELETE) | Cancel workflow |

### 8. **Data Access & Visibility**
| Data Type | Access Level |
|-----------|--------------|
| All Student Records | ✅ Read, Edit, Create |
| All Transactions | ✅ Read, Create (Cash), Delete |
| All Payment Terms | ✅ Read, Edit, Create |
| All Fees | ✅ Read, Edit, Create, Delete |
| Financial Statistics | ✅ Full Dashboard Access |
| Payment Trends | ✅ 6-month Historical Data |
| Collection Rates | ✅ Real-time Calculation |

### 9. **Payment Methods Allowed (When Recording)**
✅ **CASH** (on-campus, in-person)  
✅ GCash  
✅ Bank Transfer  
✅ Credit Card  
✅ Debit Card  

---

## ❌ WHAT ACCOUNTING CANNOT DO

### 1. **Admin-Only Functions (11 Restrictions)**

| Feature | Admin Only? | Why |
|---------|-----------|-----|
| Admin Dashboard | ✅ YES | Strategic metrics, system health |
| User Management | ✅ YES | Create/edit admin/accounting staff |
| Admin User Activation/Deactivation | ✅ YES | System access control |
| Notification System (Create/Edit) | ✅ YES | System-wide messaging |
| Student Deletion | ✅ YES | Permanent record removal |
| Role Assignment | ✅ YES | Define user access levels |
| Semester Configuration | ✅ YES | System-wide academic periods |
| Payment Method Validation Config | ✅ YES | System payment settings |
| Account Balance Override | ✅ YES | Financial correction authority |
| User Deactivation | ✅ YES | Employee access control |
| Workflow Approval (if implemented) | ✅ YES | Final authorization gate |

### 2. **Student Access Restrictions**

| Feature | Student Can't Do |
|---------|-----------------|
| View other students' data | ✅ Blocked |
| Record cash payments | ✅ Blocked (must select GCash/Bank/Card) |
| Create assessments | ✅ Blocked |
| Delete own transactions | ✅ Blocked |
| Modify fee catalog | ✅ Blocked |

### 3. **Pages NOT Visible in Sidebar for Accounting**

| Feature | Status |
|---------|--------|
| Student Dashboard | ❌ Student only |
| My Account (Student) | ❌ Student only |
| Transaction History (Personal) | ✅ Can access via Admin view only |
| Admin Dashboard | ❌ Admin only |
| Admin Users | ❌ Admin only |
| Notifications Mgmt | ❌ Admin only |
| User Management | ❌ Admin only |
| My Profile (Student) | ❌ Student only |

---

## SIDEBAR NAVIGATION FOR ACCOUNTING USER

```
📊 Accounting Dashboard          → /accounting/dashboard
💰 Fee Management               → /fees
📚 Subject Management           → /subjects
📂 Archives                     → /students
🧾 Student Fee Management       → /student-fees
📋 Transaction (Staff View)     → /transactions/create
```

---

## WORKFLOW ACTIONS ACCOUNTING CAN PERFORM

### Student Field Updates
```
Table: students
Can Edit: ✅
- first_name, last_name, middle_initial
- student_id, student_number
- email, phone
- course, year_level
- enrollment_status
- academic_period, batch

Cannot Edit: ❌
- id (Primary Key)
- user_id (Role assignment)
- deleted_at (hard delete only)
```

### Fee Management Capabilities
```
Can Create: ✅ StudentAssessment
Can Edit: ✅ StudentPaymentTerm balances, status
Can Delete: ❌ History (soft-delete only via routes)

Can Record: ✅ Payment transactions
Can View: ✅ All carryover logic, payment terms
```

### Workflow State Changes
```
Can Change: ✅ Student enrollment_status
States Available:
  - pending → active
  - active → suspended
  - suspended → active
  - active → graduated
```

---

## RECENT FIXES APPLIED TO ACCOUNTING

### ✅ Payment Method Restrictions (Feb 21, 2026)
- **Transactions/Create.vue:** Cash method ONLY
- **Student/AccountOverview.vue:** Cash REMOVED (students restricted)
- **StudentFees/Show.vue:** Cash REMOVED (students restricted)
- **TransactionController:** Role-based validation enforced
  - Students: GCash, Bank Transfer, Credit Card, Debit Card
  - Admin/Accounting: All methods including Cash

**Result:** Accounting can now record in-person campus payments with Cash method only via `/transactions/create`.

---

## DATA VISIBILITY MATRIX

|  | Accounting Can See | Accounting Can Edit | Accounting Can Create |
|---|---|---|---|
| Student Records | ✅ All | ✅ Most Fields | ✅ New Students |
| Transactions | ✅ All | ❌ View Only | ✅ Manual Entry |
| Fees | ✅ All | ✅ Yes | ✅ New Fees |
| Payment Terms | ✅ All | ✅ Balance/Status | ✅ Via Assessment |
| Financial Reports | ✅ All | ❌ No | ❌ No |
| Admin Logs | ❌ No | ❌ No | ❌ No |
| User Access Logs | ❌ No | ❌ No | ❌ No |

---

## POTENTIAL ENHANCEMENTS FOR ACCOUNTING

### 🔵 Current State (Complete)
- ✅ Dashboard with analytics
- ✅ Fee & payment management
- ✅ Transaction recording
- ✅ Role-based payment methods
- ✅ Student archive access
- ✅ Workflow management

### 🟡 Could Be Added
- 📊 Export transactions to Excel/CSV
- 📧 Automated payment reminders (mass)
- 🔄 Batch payment processing
- 📅 Payment schedule optimization
- 🎯 Collection performance targeting
- 💾 Archived assessments audit trail
- 🔐 Payment reversal capability (with approval)

### 🔴 Should Remain Admin-Only
- User role assignment
- System configuration
- Data deletion/archival
- Semester management
- Access control

---

## SUMMARY STATISTICS

| Metric | Count |
|--------|-------|
| Pages Accessible by Accounting | 10 |
| Routes Restricted to Admin | 8 |
| Payment Methods Available | 5 |
| Student Actions | 10+ |
| Financial Operations | 7+ |
| **Overall Capability Level** | **70-80% of Staff Operations** |

---

