# 🔧 Breadcrumb & Admin Route Standardization Log

**Date:** February 20, 2026
**Objective:**
Standardize breadcrumb implementation and ensure all Notification routes are correctly prefixed under `/admin`.

---

# 📋 Executive Summary

All inconsistencies between frontend links, controller redirects, and route prefixes have been resolved.

### ✅ Results

* All Notification routes now live under `/admin/notifications`
* All frontend links updated
* All controller redirects corrected
* Breadcrumb implementation standardized
* Duplicate resource routes removed
* Route name conflicts resolved
* Layout breadcrumb propagation fixed

---

# 📦 Files Modified

| # | File                                              | Changes                          |
| - | ------------------------------------------------- | -------------------------------- |
| 1 | `app/Http/Controllers/NotificationController.php` | 3 redirect fixes                 |
| 2 | `resources/js/components/AppSidebar.vue`          | Sidebar link fix                 |
| 3 | `resources/js/pages/Admin/Dashboard.vue`          | 2 link fixes                     |
| 4 | `resources/js/pages/Admin/Notifications/Show.vue` | Breadcrumb cleanup               |
| 5 | `resources/js/pages/Admin/Notifications/Form.vue` | Breadcrumb correction            |
| 6 | `resources/js/pages/Admin/Users/Edit.vue`         | Breadcrumb simplification        |
| 7 | `routes/web.php`                                  | Admin prefix + duplicate removal |
| 8 | `resources/js/layouts/AppLayout.vue`              | Breadcrumb prop pass-through fix |

---

# 🎯 Detailed Technical Changes

---

## 1️⃣ Controller Redirect Corrections

### File:

```
app/Http/Controllers/NotificationController.php
```

### Problem:

Redirects pointed to old path:

```
/notifications
```

### Fix:

All redirects updated to:

```
/admin/notifications
```

### Changes:

#### Store

```diff
- return redirect('/notifications')
+ return redirect('/admin/notifications')
```

#### Update

```diff
- return redirect('/notifications')
+ return redirect('/admin/notifications')
```

#### Destroy

```diff
- return redirect('/notifications')
+ return redirect('/admin/notifications')
```

---

## 2️⃣ Sidebar Navigation Fix

### File:

```
resources/js/components/AppSidebar.vue
```

### Problem:

Sidebar link used old path.

```diff
- href: '/notifications'
+ href: '/admin/notifications'
```

---

## 3️⃣ Admin Dashboard Link Fixes

### File:

```
resources/js/pages/Admin/Dashboard.vue
```

Two links updated:

```diff
- '/notifications'
+ '/admin/notifications'
```

---

## 4️⃣ Breadcrumb Standardization

Previously, breadcrumbs inconsistently used:

```ts
computed<BreadcrumbItem[]>(...)
```

This was unnecessary and inconsistent with other Admin pages.

### New Standard Pattern:

```ts
const breadcrumbItems: BreadcrumbItem[] = [
  { title: 'Admin', href: '/admin' },
  { title: 'Notifications', href: '/admin/notifications' },
]
```

---

## 5️⃣ Notifications Show Page Fix

### File:

```
resources/js/pages/Admin/Notifications/Show.vue
```

### Removed:

```diff
- import { computed } from 'vue'
```

### Replaced computed wrapper with static array:

```diff
- const breadcrumbItems = computed<BreadcrumbItem[]>(() => [
+ const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Notifications', href: '/admin/notifications' },
    { 
      title: props.notification.title, 
      href: `/admin/notifications/${props.notification.id}` 
    },
- ])
+ ]
```

---

## 6️⃣ Notifications Form Page Fix

### File:

```
resources/js/pages/Admin/Notifications/Form.vue
```

### Problem:

Breadcrumb for current page used actual route instead of `#`.

### Fix:

```diff
- href: `/admin/notifications/${props.notification?.id}/edit`
+ href: '#'
```

Reason:
Current page breadcrumb should not link to itself.

---

## 7️⃣ Users Edit Page Simplification

### File:

```
resources/js/pages/Admin/Users/Edit.vue
```

Removed unnecessary computed wrapper.

```diff
- const breadcrumbItems = computed<BreadcrumbItem[]>(() => [
+ const breadcrumbItems: BreadcrumbItem[] = [
```

Standardized across all Admin pages.

---

# 8️⃣ Critical Layout Fix — Breadcrumb Prop Pass-Through

### File:

```
resources/js/layouts/AppLayout.vue
```

### Problem:

Breadcrumbs were passed as `items` instead of `breadcrumbs`.

```diff
- <AppLayout :items="breadcrumbs">
+ <AppSidebarLayout :breadcrumbs="breadcrumbs">
```

### Why This Matters

Breadcrumb flow:

```
Page → AppLayout → AppSidebarLayout → Breadcrumb Component
```

If prop name mismatches, breadcrumbs will not render.

This fix ensures breadcrumbs properly display in the interface.

---

# 9️⃣ Route Prefix Standardization

### File:

```
routes/web.php
```

### Problem:

View-only Notification route lacked `admin` prefix.

### Fix:

```diff
- Route::middleware([...])->group(function () {
+ Route::middleware([...])->prefix('admin')->group(function () {
```

---

# 🔥 Duplicate Route Removal

Duplicate resource definition caused:

```
Unable to prepare route [users.index]
Another route has already been assigned name [users.index]
```

Removed unused:

```php
Route::resource('users', UserController::class);
```

Kept correct:

```php
Route::resource('users', AdminController::class);
```

---

# ✅ Final Route Structure

## Notifications

```
admin/notifications
admin/notifications/create
admin/notifications/{notification}
admin/notifications/{notification}/edit
```

## Users

```
admin/users
admin/users/create
admin/users/{user}
admin/users/{user}/edit
```

All consistent and namespaced.

---

# 🧪 Verification Checklist

Run:

```bash
php artisan route:clear
php artisan route:list
```

Confirm:

* No duplicate route names
* All Notification routes prefixed with `admin/`
* All Users routes prefixed with `admin/`

---

# 📊 Before vs After

| Category                  | Before         | After              |
| ------------------------- | -------------- | ------------------ |
| Route Prefixing           | Mixed          | Fully standardized |
| Redirect Paths            | Inconsistent   | Fully corrected    |
| Breadcrumb Implementation | Mixed patterns | Standardized       |
| Layout Prop Passing       | Incorrect      | Fixed              |
| Duplicate Routes          | Present        | Removed            |
| 404 Errors                | Occurring      | Resolved           |

---

# 🚀 Outcome

✔ No 404 errors
✔ Breadcrumbs render correctly
✔ Admin routes properly namespaced
✔ No route name conflicts
✔ Clean, maintainable structure
✔ Consistent Admin module pattern

---

# 🧠 Architectural Result

The Admin module now follows a clear hierarchy:

```
Admin
 ├── Dashboard
 ├── Users
 └── Notifications
```

All routes, breadcrumbs, and redirects reflect this structure.

