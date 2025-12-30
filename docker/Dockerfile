FROM php:8.2-apache

# Build arguments
ARG USER_ID=1000
ARG GROUP_ID=1000

# Set system timezone
ENV TZ=Europe/Kyiv

# Enable Apache rewrite module
RUN a2enmod rewrite

# Install system dependencies
ENV DEBIAN_FRONTEND=noninteractive
RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    libzip-dev \
    libsqlite3-dev \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

# Configure PHP timezone
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone \
    && printf '[PHP]\ndate.timezone = "%s"\n', $TZ > /usr/local/etc/php/conf.d/tzone.ini

# Synchronize user/group IDs
RUN usermod -u ${USER_ID} www-data && groupmod -g ${GROUP_ID} www-data

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set DocumentRoot to public folder
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

# Update Apache configuration
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf

# Set directory permissions
RUN echo '<Directory /var/www/html/public/>' >> /etc/apache2/apache2.conf && \
    echo '    Options Indexes FollowSymLinks' >> /etc/apache2/apache2.conf && \
    echo '    AllowOverride All' >> /etc/apache2/apache2.conf && \
    echo '    Require all granted' >> /etc/apache2/apache2.conf && \
    echo '</Directory>' >> /etc/apache2/apache2.conf

# Configure Git safe directory
RUN git config --global --add safe.directory /var/www/html

WORKDIR /var/www/html
