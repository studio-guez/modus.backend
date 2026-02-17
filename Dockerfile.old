FROM php:8.1-apache

# Activer mod_rewrite pour Kirby
RUN a2enmod rewrite

# Installer dépendances système
RUN apt-get update && apt-get install -y \
    unzip \
    git \
    libzip-dev \
    libjpeg-dev \
    libpng-dev \
    libfreetype6-dev \
    libwebp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install zip gd mbstring

# Installe Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Ajouter un ServerName pour éviter les logs
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Activer config Apache custom (si utilisée)
COPY apache.conf /etc/apache2/conf-available/custom.conf
RUN a2enconf custom || true

# Copier les fichiers
COPY . /var/www/html
WORKDIR /var/www/html

# Installer les dépendances PHP (Kirby)
RUN composer install --no-dev --optimize-autoloader || true

# Fixer les permissions
RUN chown -R www-data:www-data /var/www/html

