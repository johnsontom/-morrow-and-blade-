FROM php:8.2-apache

# The app reaches MariaDB through PDO, talks to the model API over cURL and
# trims text with mbstring.
RUN apt-get update \
 && apt-get install -y --no-install-recommends libcurl4-openssl-dev libonig-dev \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql curl mbstring \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/*

# The .htaccess files are part of the app's security model (config/, app/,
# database/ and storage/ deny direct access), so overrides have to be allowed.
COPY docker/php/apache-app.conf /etc/apache2/conf-available/app.conf
RUN a2enconf app

COPY docker/php/docker-entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint

WORKDIR /var/www/html

ENTRYPOINT ["/usr/local/bin/app-entrypoint"]
CMD ["apache2-foreground"]
