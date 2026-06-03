# Stage 1: Build Node.js Assets (Vite)
FROM node:20-alpine AS node_builder
WORKDIR /app
# Copy package.json and package-lock.json
COPY package*.json ./
# Install Node dependencies
RUN npm install
# Copy the rest of the application
COPY . .
# Build Vite assets
RUN npm run build

# Stage 2: PHP Application
FROM serversideup/php:8.3-fpm-nginx

# Switch to root to install PHP extensions
USER root

# Install extensions required for PhpSpreadsheet and Laravel
RUN install-php-extensions gd zip bcmath intl pdo_sqlite

# Drop back to www-data user
USER www-data

# Copy application files (excluding node_modules and vendor based on standard .dockerignore)
COPY --chown=www-data:www-data . /var/www/html

# Copy compiled frontend assets from the node_builder stage
COPY --chown=www-data:www-data --from=node_builder /app/public/build /var/www/html/public/build

# Install Composer dependencies (optimized for production)
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# Clear existing cache/views/config if any
RUN php artisan optimize:clear

# Ensure an empty SQLite database exists so Portainer can mount volumes correctly
RUN mkdir -p /var/www/html/database && touch /var/www/html/database/database.sqlite && chown www-data:www-data /var/www/html/database/database.sqlite

# serversideup web image exposes 8080 by default
EXPOSE 8080
