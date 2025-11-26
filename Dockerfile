# Use official PHP image with Apache
FROM php:8.2-apache

# Enable curl extension
RUN docker-php-ext-install curl

# Copy PHP files to web root
COPY . /var/www/html/

# Expose port 80
EXPOSE 80

# Set working directory
WORKDIR /var/www/html
