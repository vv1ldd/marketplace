FROM serversideup/php:8.2-fpm-nginx

# Switch to root to install extensions
USER root

# Install additional PHP extensions
# (opcache, redis, pdo_mysql, zip are already included in the base image)
RUN install-php-extensions bcmath intl gd

# Set working directory
WORKDIR /var/www/html

# Copy application code with correct permissions
COPY --chown=webuser:webgroup . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Install Node.js dependencies and build assets (Vite)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && \
    apt-get install -y nodejs && \
    npm ci && \
    npm run build && \
    rm -rf node_modules

# Switch back to non-root user
USER webuser
