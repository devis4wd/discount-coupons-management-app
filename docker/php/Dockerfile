FROM php:8.3-apache

# Install MySQL-related PHP extensions.
# The current application uses MySQLi; PDO MySQL support is included as well.
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Enable Apache's mod_rewrite module so rewrite rules can be used if needed.
RUN a2enmod rewrite

# Set the working directory to Apache's default document root.
WORKDIR /var/www/html
