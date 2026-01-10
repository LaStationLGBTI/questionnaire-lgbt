FROM trafex/php-nginx

ENV DB_HOSTNAME="localhost"
ENV DB_USERNAME="USERNAME"
ENV DB_PASSWORD="PASSWORD"
ENV DB_NAME="questionnaire-lgbti"

RUN apk add --no-cache php-pdo_mysql

COPY --chown=nginx . /var/www/html/
COPY --chown=nginx --chmod=0400 conf.php.example /var/www/html/conf.php
