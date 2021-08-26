# Container image that runs your code
ARG php_version
FROM ghcr.io/qlicks/magento-deployer:${php_version}

# Copies your code file from your action repository to the filesystem path `/` of the container
COPY deploy.php /deploy.php
COPY entrypoint.sh /entrypoint.sh

RUN ["chmod", "+x", "/entrypoint.sh"]

# Code file to execute when the docker container starts up (`entrypoint.sh`)
ENTRYPOINT ["/entrypoint.sh"]
