FROM serversideup/php:8.2-fpm-nginx

# 1. Run as root to install system packages and extensions
USER root

# Install PHP extensions (compilation might take a while)
RUN install-php-extensions bcmath intl gd

# Install Node.js (LTS)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && \
    apt-get install -y nodejs

# 2. Switch to 'www-data' for the rest of the build
# This ensures files created by composer/npm are owned by the correct user,
# preventing "Permission denied" errors at runtime.
USER www-data
WORKDIR /var/www/html

# Copy application code from host
COPY --chown=www-data:www-data . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Install JS dependencies and build assets
RUN npm ci && \
    npm run build && \
    rm -rf node_modules
