FROM php:8.1-fpm

WORKDIR /var/www/html

RUN apt update && \
    apt install -y zip && \
    docker-php-ext-install pdo_mysql && \
    curl -sS -o composer-setup.php https://getcomposer.org/installer && \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer && \
    rm composer-setup.php


COPY . /var/www/html

RUN composer install --no-dev --no-interaction && \
    chown -R www-data:www-data /var/www/html/storage && php artisan lti:add_platform_1.3 moodle --platform_id='$URL_LMS' --client_id="$CLIENT_ID" --deployment_id="$DEPLOYMENT_ID"

CMD ["php","artisan","serve","--host=0.0.0.0","--port=9000"].
