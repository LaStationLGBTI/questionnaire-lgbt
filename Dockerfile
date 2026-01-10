FROM trafex/php-nginx

ENV DB_HOSTNAME="localhost"
ENV DB_USERNAME="USERNAME"
ENV DB_PASSWORD="PASSWORD"
ENV DB_NAME="questionnaire-lgbti"

COPY --exclude=Dockerfile --exclude=.github --chown=nginx . /var/www/html/
COPY --chown=nginx --chmod=0400 conf.php.example /var/ww/html/conf.php
