FROM php:8.2-apache-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libonig-dev libzip-dev libcurl4-openssl-dev unzip \
 && docker-php-ext-install pdo_mysql mbstring zip curl \
 && a2enmod headers \
 && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY . /var/www/html/
RUN cp /var/www/html/config.example.php /var/www/html/config.php \
 && cp -a /var/www/html/assets/. /var/www/html/public/assets/ \
 && mkdir -p /var/www/html/uploads && chown -R www-data:www-data /var/www/html/uploads \
 && chmod 770 /var/www/html/uploads
COPY deploy/php.ini /usr/local/etc/php/conf.d/smartrecruit.ini
COPY deploy/apache.conf /etc/apache2/sites-available/000-default.conf
COPY deploy/start-web.sh /usr/local/bin/smartrecruit-start
RUN chmod +x /usr/local/bin/smartrecruit-start
CMD ["smartrecruit-start"]
