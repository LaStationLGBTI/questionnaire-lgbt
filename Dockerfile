FROM trafex/php-nginx

ENV DB_HOSTNAME="localhost"
ENV DB_USERNAME="USERNAME"
ENV DB_PASSWORD="PASSWORD"
ENV DB_NAME="questionnaire-lgbti"

USER root
RUN apk add --no-cache php-pdo_mysql
USER nobody

COPY --chown=nobody . /var/www/html/
COPY --chown=nobody conf.php.example /var/www/html/conf.php
RUN rm -rf .git Dockerfile .dockerignore
