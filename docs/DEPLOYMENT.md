# Workflow System Deployment Guide

## Prerequisites

- PHP 8.2 or higher
- Composer 2.x
- Node.js 18.x or higher
- MySQL 8.0 or higher
- Redis (optional, for queue management)

## Installation Steps

### 1. Clone Repository
```bash
git clone https://github.com/JCCalsado/exccdiport01.git
cd exccdiport01
```

### 2. Install Dependencies
```bash
composer install
npm install
```

### 3. Environment Configuration
```bash
cp .env.example .env
php artisan key:generate
```

Update `.env` with database credentials and other settings.

### 4. Database Setup
```bash
php artisan migrate
php artisan db:seed --class=WorkflowSeeder
```

### 5. Build Frontend Assets
```bash
npm run build
```

### 6. Storage Linking
```bash
php artisan storage:link
```

### 7. Queue Worker (Production)
```bash
php artisan queue:work --daemon
```

## Testing

### Run All Tests
```bash
php artisan test
```

### Run Specific Test Suite
```bash
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
```

## Artisan Commands

### Create Workflow
```bash
php artisan workflow:create {name} {type}
```

### List Active Workflows
```bash
php artisan workflow:list
```

### Clear Workflow Cache
```bash
php artisan workflow:clear-cache
```

## Production Checklist

- [ ] Set `APP_ENV=production`
- [ ] Set `APP_DEBUG=false`
- [ ] Configure proper database credentials
- [ ] Set up SSL certificate
- [ ] Configure mail server
- [ ] Set up queue worker with supervisor
- [ ] Enable cache (`php artisan config:cache`)
- [ ] Enable route cache (`php artisan route:cache`)
- [ ] Set proper file permissions
- [ ] Configure backup system
- [ ] Set up monitoring (New Relic, Sentry, etc.)

## Troubleshooting

### Workflow Not Advancing
- Check queue worker is running
- Verify database connections
- Check workflow step configuration

### Notifications Not Sending
- Verify mail configuration
- Check queue worker logs
- Ensure `WORKFLOW_NOTIFICATION_ENABLED=true`

### Permission Errors
- Run `php artisan storage:link`
- Check storage directory permissions (775)
- Verify web server user owns files
