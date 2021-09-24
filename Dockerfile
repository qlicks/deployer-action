FROM ghcr.io/qlicks/magento-php-7.4:latest

COPY deploy.php /deploy.php

COPY entrypoint.sh /entrypoint.sh

RUN ["chmod", "+x", "/entrypoint.sh"]

ENTRYPOINT ["/entrypoint.sh"]