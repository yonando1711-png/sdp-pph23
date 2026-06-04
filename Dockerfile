# Stage 1: Build Node.js Assets (Vite)
FROM node:20 AS node_builder
RUN apt-get update && apt-get install -y php-cli php-mbstring php-xml php-curl php-sqlite3 unzip curl \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app
# Copy package.json and package-lock.json
COPY package*.json ./
# Install Node dependencies
RUN npm install
# Copy the rest of the application
COPY . .
# Install Composer dependencies (ignoring platform reqs) so Laravel can boot during Vite build
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev --ignore-platform-reqs --no-scripts
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

# Ensure an empty SQLite database exists so Portainer can mount volumes correctly
# We do this BEFORE composer install so that Laravel's package:discover doesn't crash
RUN mkdir -p /var/www/html/storage && touch /var/www/html/storage/database.sqlite

# Set environment variables for the build phase to prevent artisan commands from crashing
ENV DB_CONNECTION=sqlite
ENV DB_DATABASE=/var/www/html/storage/database.sqlite
ENV APP_ENV=production

# Install Composer dependencies (optimized for production)
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev --no-scripts --ignore-platform-reqs

# serversideup web image exposes 8080 by default
EXPOSE 8080
