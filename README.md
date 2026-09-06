> [!IMPORTANT]
> **Archived — this repository is read-only.**
>
> The code has moved to the **[studio-guez/modus](https://github.com/studio-guez/modus)**
> monorepo, where it now lives under `cms/`. Every commit of this repository was
> imported there with `git subtree` and is an ancestor of the monorepo's `main`,
> so the whole history is preserved — nothing here is lost.
>
> Open issues and pull requests, and all new work, belong in the monorepo.
> Everything below describes the old standalone setup and is kept for reference
> only; the current instructions are in the monorepo's `README.md`.

# Modus Backend - Kirby CMS

A content management platform built with Kirby CMS, running on PHP 8.4 with Apache in a Docker environment.

## Local Development (docker-compose)

1. `git clone https://github.com/studio-guez/modus.backend.git`
1. `cd modus.backend/`
1. _Vérifier config, surtout conflits ports dans_ `docker-compose.yml`
1. Build and run with your user's UID/GID:

```bash
docker-compose up -d --build
```

5. Install Composer dependencies (first time only):

```bash
docker exec -w /var/www/html modus-app composer install --no-interaction
```

6. Fix permissions for writable directories (first time only):

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

- **Admin Panel**: http://localhost:8080/panel

## Configuration

- Config files are located in `site/config/`
- Writable directories: `site/accounts`, `site/sessions`, `content`, `media`

## Troubleshooting

- **`Class "Kirby" not found`**: Run `composer install` inside the container (step 5 above)
- **Permission errors**: Re-run the `chown` command from step 6
- **PHP extension errors**: Ensure `mbstring`, `gd`, and `zip` extensions are enabled (included in the Dockerfile)

Pour plus de détails sur la configuration avancée, consultez la [documentation officielle de Kirby](https://getkirby.com/docs/guide/quickstart).

## Updating the project

All update commands run **inside the Docker container** (no local PHP/Composer/npm required).

### 1. Rebuild the image after PHP version changes

```bash
docker compose up -d --build
```

### 2. Update Composer dependencies (Kirby + all packages)

```bash
docker compose exec app composer update --no-interaction
```

### 3. Security audit

```bash
docker compose exec app composer audit
```

### PHP version

The PHP version is defined in four places — keep them in sync:

| File | Setting |
|---|---|
| `Dockerfile` | `FROM php:X.Y-apache` |
| `Dockerfile.dev` | `FROM php:X.Y-apache` |
| `docker-compose.yml` | `PHP_VERSION: "X.Y"` |
| `composer.json` | `"php": "^X.Y"` |


