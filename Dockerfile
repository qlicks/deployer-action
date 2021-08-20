FROM ghcr.io/qlicks/magento-php-$php-version:latest

echo $AUTH_JSON > auth.json
dep $*
