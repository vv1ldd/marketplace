FROM serversideup/php:8.4-fpm-nginx

# 1. Run as root to install system packages and extensions
USER root

# Install PHP extensions. Build imagick from GitHub because pecl.php.net can
# transiently return broken redirects during emergency deploys.
ARG IMAGICK_VERSION=3.8.0
RUN install-php-extensions bcmath intl gd && \
    apt-get update && \
    apt-get install -y --no-install-recommends libmagickwand-dev && \
    curl -fsSL "https://github.com/Imagick/imagick/archive/refs/tags/${IMAGICK_VERSION}.tar.gz" -o /tmp/imagick.tar.gz && \
    mkdir -p /tmp/imagick && \
    tar -xzf /tmp/imagick.tar.gz -C /tmp/imagick --strip-components=1 && \
    cd /tmp/imagick && \
    phpize && \
    ./configure && \
    make -j"$(nproc)" && \
    make install && \
    docker-php-ext-enable imagick && \
    rm -rf /tmp/imagick /tmp/imagick.tar.gz && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

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
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Generate key (needed for some commands) and publish assets
# We set a dummy key for build time if not present, though artisan usually needs valid config.
# Ideally we set APP_KEY in build args, but here we can just try to run commands that don't need DB.
#RUN php artisan package:discover --ansi || true
#RUN php artisan filament:upgrade --no-interaction --quiet || true

# Install JS dependencies and build assets
RUN npm ci && \
    npm run build && \
    rm -rf node_modules

# Runtime dependency for Bitcoin binding signature verification (BIP-322).
RUN cd scripts && npm ci --omit=dev
