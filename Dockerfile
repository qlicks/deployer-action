FROM busybox
ARG php-version
RUN printenv

FROM ghcr.io/qlicks/magento-php-${PHP_VERSION}:latest

COPY entrypoint.sh /entrypoint.sh


ENTRYPOINT ["/entrypoint.sh"]
