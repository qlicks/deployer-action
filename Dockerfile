FROM ghcr.io/qlicks/magento-php-$php-version:latest

if [[ -z "${AUTH_JSON}" ]]; then
      echo "$AUTH_JSON" > auth.json
fi
dep $*
