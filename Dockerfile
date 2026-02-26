FROM php:8.1-apache

# Activer mod_rewrite pour Kirby
RUN a2enmod rewrite

# Installer dépendances système + extensions PHP
RUN apt-get update && apt-get install -y --no-install-recommends \
    unzip \
    git \
    libzip-dev \
    zlib1g-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libwebp-dev \
    libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" zip gd mbstring \
    && rm -rf /var/lib/apt/lists/*

# Installe Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Ajouter un ServerName pour éviter les logs
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Activer config Apache custom (si utilisée)
COPY apache.conf /etc/apache2/conf-available/custom.conf
RUN a2enconf custom || true

# Copier les fichiers avec les bonnes permissions
COPY --chown=www-data:www-data . /var/www/html
WORKDIR /var/www/html

# Installer les dépendances PHP (Kirby)
RUN composer install --no-dev --optimize-autoloader || true