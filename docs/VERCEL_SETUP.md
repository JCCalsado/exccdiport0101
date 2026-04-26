# Vercel Deployment Configuration

## Changes Made

1. **Upgraded PHP Runtime**: `vercel-php@0.7.2` → `vercel-php@0.8.29`
   - Newer version includes MySQL PDO extension pre-compiled
   - Fixes "could not find driver" error

2. **Disabled Telescope by Default**: 
   - Telescope only enabled in `local` environment
   - Prevents database access issues during deployment

3. **Removed Migrations from Build Command**:
   - ❌ NO `php artisan migrate:fresh --seed` in build
   - ✅ Migrations run separately (see steps below)
   - Reason: Serverless builds shouldn't access the database

4. **Updated `.vercelignore`**:
   - Prevents `.env` file from being deployed
   - Vercel uses environment variables instead

## Required Vercel Environment Variables

You MUST set these in **Vercel Dashboard** → **Project Settings** → **Environment Variables**:

```
APP_ENV=production
APP_DEBUG=false
APP_KEY=<your base64 key from local .env>
DB_CONNECTION=mysql
DB_HOST=<your Railway host or DB host>
DB_PORT=<your DB port>
DB_DATABASE=<your database name>
DB_USERNAME=<your DB username>
DB_PASSWORD=<your DB password>
TELESCOPE_ENABLED=false
```

## Important Notes

- **Never deploy `.env` to Git** — Use environment variables instead
- **Database must be accessible** from Vercel (Railway, AWS RDS, etc.)
- **Storage permissions** — Make sure `/storage` directory is writable
- **Migrations** — Run migrations locally, commit to database (Vercel can't run `php artisan migrate`)

## Deployment Steps

1. Commit your changes:
```bash
git add vercel.json config/telescope.php api/php.ini .vercelignore .env.example docs/VERCEL_SETUP.md
git commit -m "Fix Vercel deployment: upgrade PHP, disable migrations from build"
git push
```

2. Set environment variables in Vercel Dashboard

3. Trigger a deployment (automatic on git push)

4. **Run migrations** (see "Running Migrations on Vercel" section below)

---

## Running Migrations on Vercel

### Option A: Local Migrations (Recommended)

Run migrations locally, then only deploy the application code:

```bash
# 1. Locally: ensure your .env has production database credentials
DB_HOST=<production-host> DB_DATABASE=<production-db> php artisan migrate --force

# 2. Then deploy to Vercel (no migrations run during build)
git push
```

### Option B: SSH Tunnel to Production Database

If you have SSH access to your production server:

```bash
# 1. Create SSH tunnel to your database
ssh -N -L 3306:<your-db-host>:3306 user@your-server &

# 2. Run migrations through the tunnel
DB_HOST=127.0.0.1 php artisan migrate --force

# 3. Close tunnel
killall ssh
```

### Option C: One-Time Deployment Job (Advanced)

For Vercel Cron Jobs (requires GitHub Actions or similar CI):

1. Create `.github/workflows/migrate-production.yml`:
```yaml
name: Migrate Production Database
on:
  workflow_dispatch:  # Manual trigger

jobs:
  migrate:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Run Laravel migrations
        run: |
          php artisan migrate --force
        env:
          APP_ENV: production
          DB_HOST: ${{ secrets.DB_HOST }}
          DB_DATABASE: ${{ secrets.DB_DATABASE }}
          DB_USERNAME: ${{ secrets.DB_USERNAME }}
          DB_PASSWORD: ${{ secrets.DB_PASSWORD }}
```

2. Trigger from **GitHub** → **Actions** → **Migrate Production Database** → **Run workflow**

---

## Verify Deployment

Once deployed and migrations are complete: ```
GET https://your-domain.vercel.app/
```

Should load successfully without database errors.
