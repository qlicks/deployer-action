#FROM "ghcr.io/qlicks/magento-php-${PHP-VERSION}"
FROM "php:${PHP-VERSION}-fpm"


# Copies your code file from your action repository to the filesystem path `/` of the container
COPY docker-action/deploy.php /app/deploy.php
COPY entrypoint.sh /entrypoint.sh

RUN ["chmod", "+x", "/entrypoint.sh"]

# Code file to execute when the docker container starts up (`entrypoint.sh`)
ENTRYPOINT ["/entrypoint.sh"]
