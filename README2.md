# Modus Backend - Kirby CMS

A content management platform built with Kirby CMS, running on PHP 8.1 with Apache in a Docker environment.

## Local Development (docker-compose)

1. `git clone https://github.com/studio-guez/modus.backend.git`
1. `cd modus.backend/`
1. _Vérifier config, surtout conflits ports dans_ `docker-compose.yml`
1. Build and run with your user's UID/GID:

```bash
docker-compose up -d --build
```

5. Fix permissions for writable directories (first time only):

```bash
docker exec modus-app chown -R www-data:www-data /var/www/html/site/sessions /var/www/html/site/accounts /var/www/html/content /var/www/html/media
```

This mounts the entire project and runs Apache with your local user permissions (UID 1000), so you can edit files directly from VS Code or terminal without permission issues.

## Production (Dockerfile only)

For production, use the standard `Dockerfile` which copies files and sets `www-data` ownership:

```bash
docker build -t modus-backend .
docker run -d -p 80:80 modus-backend
```

## Default access URLs (with default ports)

- **Admin Panel**: http://localhost:8080
