# LOCAL MYSQL SETUP — Before Production Deployment

> Test your system with MySQL locally before deploying to Vercel + Railway

---

## Problem

Your current `.env` uses SQLite (default):
```env
DB_CONNECTION=sqlite
```

**SQLite works locally but NOT on Vercel (read-only filesystem).**

To catch database issues early, **switch to MySQL locally** before deploying to production.

---

## Step 1: Create Local MySQL Database

### Using Laragon (Recommended)

```bash
# Laragon already has MySQL running on localhost:3306
# Just create a new database for testing

# Open Laragon Terminal (click "Terminal" button)
mysql -u root -p

# In MySQL CLI:
CREATE DATABASE ccdiaccportal_prod;
CREATE USER 'ccdi_user'@'localhost' IDENTIFIED BY 'your_password_123';
GRANT ALL PRIVILEGES ON ccdiaccportal_prod.* TO 'ccdi_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### Using MySQL Command Line (Windows)

```bash
# Open Command Prompt as Admin
cd "C:\Program Files\MySQL\MySQL Server 8.0\bin"

mysql -u root -p
# Enter root password

# In MySQL:
CREATE DATABASE ccdiaccportal_prod;
CREATE USER 'ccdi_user'@'localhost' IDENTIFIED BY 'your_password_123';
GRANT ALL PRIVILEGES ON ccdiaccportal_prod.* TO 'ccdi_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

## Step 2: Update Local .env for MySQL Testing

**File: `.env`** (your LOCAL development file)

```env
# Change from SQLite to MySQL
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ccdiaccportal_prod
DB_USERNAME=ccdi_user
DB_PASSWORD=your_password_123
```

**DO NOT commit this to Git.** This is local only.

---

## Step 3: Test the Connection

```bash
# From project root
cd C:\laragon\www\exccdiport0101

# Test DB connection
php artisan tinker

# In tinker:
>>> DB::connection()->getPDO();
// Should return PDO object (no error)

>>> exit()
```

If you get a connection error, check:
- Is MySQL running? (Laragon → MySQL should be green)
- Is DB_HOST correct? (usually `127.0.0.1`)
- Is DB_PORT correct? (usually `3306`)
- Did you create the database?
- Is the password correct?

---

## Step 4: Run Migrations

```bash
# Clear old cached configs
php artisan config:clear
php artisan cache:clear

# Run migrations
php artisan migrate

# Output should show:
# Migrated: 2025_09_05_222341_add_student_fields_to_users_table
# Migrated: 2025_09_05_222430_create_transactions_table
# ... etc
```

---

## Step 5: Test Seeding

```bash
# Run seeders
php artisan db:seed

# Should complete without errors
# Check the database filled with test data
```

**Verify in MySQL:**

```bash
mysql -u ccdi_user -p ccdiaccportal_prod

# In MySQL:
SELECT COUNT(*) FROM users;
SELECT COUNT(*) FROM students;
SELECT COUNT(*) FROM student_assessments;
```

Should show your seeded data counts.

---

## Step 6: Test the Application

```bash
# Stop old server (if running)
# Ctrl+C in the terminal

# Start fresh
composer run dev

# Or manually:
php artisan serve
npm run dev

# Visit http://localhost:8000
```

**Test these flows:**
- ✅ Register a new student
- ✅ Login as admin (`admin@ccdi.edu.ph` / `password`)
- ✅ View student list
- ✅ Create a payment
- ✅ Check transaction history

---

## Step 7: Back to SQLite (Optional)

After testing, if you want to go back to SQLite locally:

```bash
# Revert .env
DB_CONNECTION=sqlite

# Clear caches
php artisan config:clear

# Reseed SQLite
php artisan migrate:fresh
php artisan db:seed
```

---

## Troubleshooting

### Error: "Connection refused"
```
MySQL is not running

Fix:
- Windows: Services → MySQL80 → Right-click → Start
- Laragon: Click the MySQL module to start
```

### Error: "Access denied for user"
```
Wrong credentials

Fix:
- Verify password: SHOW VARIABLES LIKE 'validate_password%';
- Reset root: Look up "MySQL reset root password Windows"
```

### Error: "Unknown database"
```
Database not created

Fix:
- In MySQL CLI: SHOW DATABASES;
- Create it: CREATE DATABASE ccdiaccportal_prod;
```

### Migrations fail with "Column not found"
```
Old migration from SQLite not compatible with MySQL

Fix:
1. Back up .env
2. Switch to SQLite
3. Run: php artisan migrate:fresh
4. Try MySQL again
```

---

## Next Steps

Once testing succeeds locally with MySQL:

1. ✅ Your code is compatible with MySQL
2. ✅ Migrations work correctly
3. ✅ Seeding succeeds
4. ✅ Ready for production on Railway!

Proceed to **DEPLOYMENT_GUIDE.md** to deploy to Vercel + Railway.

---

## Reference: MySQL vs SQLite

| Aspect | SQLite | MySQL |
|---|---|---|
| Connection | File-based (local only) | Network (works remotely) |
| Performance | Good for small apps | Great for production |
| Transactions | ✅ Full ACID | ✅ Full ACID |
| Scaling | ❌ Limited | ✅ Scales well |
| Backups | File copy | Database dump |
| Vercel | ❌ Ephemeral | ✅ Works with Railway |

---

**Tested:** ☐ MySQL connection works  
**Date:** ___________  
**Ready for Vercel:** ☐ YES
