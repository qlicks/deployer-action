ENV php-version
FROM ghcr.io/qlicks/magento-php-${php-version}:latest

COPY entrypoint.sh /entrypoint.sh


ENTRYPOINT ["/entrypoint.sh"]
