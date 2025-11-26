# Use official PHP image with Apache
FROM php:8.2-apache

# Install dependencies for curl extension
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    pkg-config \
    unzip \
    && docker-php-ext-install curl \
    && rm -rf /var/lib/apt/lists/*

# Copy PHP files to web root
COPY . /var/www/html/

# Expose port 80
EXPOSE 80

# Set working directory
WORKDIR /var/www/html
