# Docker Setup for CCDI Account Portal

## Prerequisites
- Docker Desktop (includes Docker and Docker Compose)
- Git

## Quick Start

### Using Docker Compose (Recommended for development)

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd exccdiport0101
   ```

2. **Build and start containers**
   ```bash
   docker-compose up -d --build
   ```

3. **Run database migrations**
   ```bash
   docker-compose exec app php artisan migrate --force
   ```

4. **Seed the database (optional)**
   ```bash
   docker-compose exec app php artisan db:seed
   ```

5. **Access the application**
   - Application: `http://localhost:8080`
   - MySQL: `localhost:3306`

### Using Docker Build (for production)

1. **Build the image**
   ```bash
   docker build -t ccdi-portal:latest .
   ```

2. **Run the container**
   ```bash
   docker run -d \
     --name ccdi-portal \
     -p 8080:8080 \
     -e APP_ENV=production \
     -e APP_DEBUG=false \
     -e DB_HOST=<your-mysql-host> \
     -e DB_DATABASE=ccdi_portal \
     -e DB_USERNAME=ccdi_user \
     -e DB_PASSWORD=<your-password> \
     ccdi-portal:latest
   ```

## Useful Commands

### Docker Compose
```bash
# Start containers
docker-compose up -d

# Stop containers
docker-compose down

# View logs
docker-compose logs -f app

# Execute artisan commands
docker-compose exec app php artisan <command>

# Access application shell
docker-compose exec app sh

# Rebuild containers
docker-compose up -d --build
```

### Docker
```bash
# View running containers
docker ps

# View all containers
docker ps -a

# View container logs
docker logs <container-id>

# Access container shell
docker exec -it <container-id> sh

# Stop container
docker stop <container-id>

# Remove container
docker rm <container-id>

# Remove image
docker rmi ccdi-portal:latest
```

## Environment Variables

Create a `.env` file in the project root:

```env
APP_NAME="CCDI Account Portal"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost:8080

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=ccdi_portal
DB_USERNAME=ccdi_user
DB_PASSWORD=secret
DB_ROOT_PASSWORD=root
```

## Database

MySQL 8.0 runs in a separate container. Data persists in a Docker volume named `mysql_data`.

To reset the database:
```bash
docker-compose down -v
docker-compose up -d
docker-compose exec app php artisan migrate --force
docker-compose exec app php artisan db:seed
```

## Logs

- **Application Logs**: `docker-compose logs -f app`
- **MySQL Logs**: `docker-compose logs -f mysql`
- **Nginx Logs**: Inside container at `/var/log/nginx/`
- **PHP-FPM Logs**: Inside container at `/var/log/php-fpm.log`

## Troubleshooting

### Port already in use
If port 8080 is already in use, modify `docker-compose.yml`:
```yaml
ports:
  - "8081:8080"  # Change to any available port
```

### Database connection error
Check that MySQL container is running:
```bash
docker-compose ps
```

Verify database settings in `.env` match `docker-compose.yml`.

### Storage permissions
If you get permission errors, run:
```bash
docker-compose exec app chown -R nobody:nobody /app/storage /app/bootstrap/cache
```

## Deployment to Railway

The `nixpacks.toml` file is configured for automatic Railway deployment. Simply push to your repository and Railway will:
1. Install dependencies
2. Build frontend assets
3. Optimize Laravel
4. Start the application

Environment variables can be set in Railway dashboard.

## Performance Tips

1. **Production Build**: The Dockerfile uses multi-stage builds to minimize image size
2. **Layer Caching**: Dependencies are installed before copying app code for better caching
3. **Health Checks**: Container includes health check endpoints
4. **Resource Limits**: Configure in `docker-compose.yml` as needed
