FROM trafex/php-nginx

ENV DB_HOSTNAME="localhost"
ENV DB_USERNAME="USERNAME"
ENV DB_PASSWORD="PASSWORD"
ENV DB_NAME="questionnaire-lgbti"
ENV SMTP_HOST=""
ENV SMTP_PORT="587"
ENV SMTP_SECURE="tls"
ENV SMTP_USER=""
ENV SMTP_PASS=""
ENV SMTP_FROM="noreply@lastation-lgbti.eu"
ENV SMTP_FROM_NAME="La Station LGBTQIA+"

USER root
RUN apk add --no-cache php85-pdo_mysql
USER nobody

COPY --chown=nobody . /var/www/html/
COPY --chown=nobody conf.php.example /var/www/html/conf.php
RUN rm -rf .git Dockerfile .dockerignore
