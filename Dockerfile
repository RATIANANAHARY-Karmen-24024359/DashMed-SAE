FROM php:8.2-apache

# Install PDO MySQL extension
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Setup DocumentRoot to point to /public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Fix Apache "More than one MPM loaded" error and ensure it listens to Railway's $PORT
RUN sed -s -i -e "s/80/\${PORT:-80}/" /etc/apache2/ports.conf /etc/apache2/sites-available/*.conf
RUN a2dismod mpm_event mpm_worker && a2enmod mpm_prefork || true

WORKDIR /var/www/html
