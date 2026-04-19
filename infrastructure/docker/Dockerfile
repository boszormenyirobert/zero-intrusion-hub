# Dockerfile for Symfony 6+ (PHP 8.3.14, Apache, MySQL, Redis, OpenSSL, cURL)
FROM php:8.3.14-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libzip-dev \
    unzip \
    git \
    curl \
    nodejs \
    npm \
    openssl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libmcrypt-dev \
    libssl-dev \
    libcurl4-openssl-dev \
    wget \
    gnupg \
    redis-server \
    && docker-php-ext-install intl pdo pdo_mysql zip opcache gd xml mbstring curl

# Install Redis extension
RUN pecl install redis-6.0.2 \
    && docker-php-ext-enable redis

# Configure OpenSSL and cURL CA certificates
RUN mkdir -p /etc/ssl/certs \
    && curl -o /etc/ssl/certs/cacert.pem https://curl.se/ca/cacert.pem \
    && echo "openssl.cafile=/etc/ssl/certs/cacert.pem" >> /usr/local/etc/php/conf.d/openssl.ini \
    && echo "curl.cainfo=/etc/ssl/certs/cacert.pem" >> /usr/local/etc/php/conf.d/curl.ini

# Enable Apache mod_rewrite
RUN a2enmod rewrite


# Set working directory
WORKDIR /var/www/html


# Copy application files
COPY . /var/www/html

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Mark /var/www/html as safe git directory
RUN git config --global --add safe.directory /var/www/html

# Install PHP and frontend dependencies during image build so the container is self-contained.
RUN composer install --no-interaction --prefer-dist --optimize-autoloader \
    && npm ci --no-audit --no-fund \
    && npm run build

# Ensure correct permissions for var and cache
RUN mkdir -p /var/www/html/var/cache \
    && chown -R www-data:www-data /var/www/html/var \
    && chmod -R 775 /var/www/html/var

# Set Apache DocumentRoot to /var/www/html/public
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|g' /etc/apache2/sites-available/000-default.conf \
    && sed -i 's|<Directory /var/www/html>|<Directory /var/www/html/public>|g' /etc/apache2/apache2.conf

# Ensure .htaccess is present in public directory
RUN cp /var/www/html/public/.htaccess /var/www/html/public/.htaccess || true

# Set permissions for JWT keys (will be generated at runtime)
RUN mkdir -p /var/www/html/config/jwt \
    && chown -R www-data:www-data /var/www/html/config/jwt

# Expose port 80
EXPOSE 80

# Entrypoint script to generate JWT keys and set .env file
COPY infrastructure/docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]
