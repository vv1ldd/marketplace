FROM serversideup/php:8.3-fpm-nginx

# 1. Run as root to install system packages and extensions
USER root

# Install PHP extensions (compilation might take a while)
RUN install-php-extensions bcmath intl gd zip

# Install Node.js (LTS) and system dependencies required for Composer
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && \
    apt-get install -y nodejs git unzip

# 2. Switch to 'www-data' for the rest of the build
# This ensures files created by composer/npm are owned by the correct user,
# preventing "Permission denied" errors at runtime.
USER www-data
WORKDIR /var/www/html

# Copy application code from host
COPY --chown=www-data:www-data . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts --ignore-platform-reqs

# Generate key (needed for some commands) and publish assets
# We set a dummy key for build time if not present, though artisan usually needs valid config.
# Ideally we set APP_KEY in build args, but here we can just try to run commands that don't need DB.
#RUN php artisan package:discover --ansi || true
#RUN php artisan filament:upgrade --no-interaction --quiet || true

# Install JS dependencies and build assets
RUN npm ci && \
    npm run build && \
    rm -rf node_modules
