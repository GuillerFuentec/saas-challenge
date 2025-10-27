FROM php:8.2-apache

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

WORKDIR /var/www/html

RUN docker-php-ext-install pdo pdo_mysql && a2enmod rewrite

COPY . .

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf \
    && printf '<Directory "%s">\n    AllowOverride All\n    Require all granted\n</Directory>\n' "${APACHE_DOCUMENT_ROOT}" > /etc/apache2/conf-available/app-overrides.conf \
    && a2enconf app-overrides

EXPOSE 80
