FROM serversideup/php:8.2-fpm-nginx

# Install additional PHP extensions
# bcmath: required by many Laravel packages
# intl: required for internalization
# redis: for Redis cache/queue
# zip: for Composer
# pdo_mysql: for MySQL connection
RUN install-php-extensions bcmath intl opcache redis pdo_mysql zip gd

# Set working directory
WORKDIR /var/www/html

# Copy application code with correct permissions
COPY --chown=webuser:webgroup . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Install Node.js dependencies and build assets (Vite)
# Using a multi-stage build or installing node here is fine for simple setups. 
# serversideup images sometimes have node, but let's be safe and install lts.
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && \
    apt-get install -y nodejs && \
    npm ci && \
    npm run build && \
    rm -rf node_modules

# Switch to non-root user
USER webuser
