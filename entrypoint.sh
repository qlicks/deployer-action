#!/bin/sh -l

PHP_VERSION=$1
ls -la 
cd /docker-action
echo "creating docker image with alpine version: $PHP_VERSION"

# here we can make the construction of the image as customizable as we need
# and if we need parameterizable values it is a matter of sending them as inputs
docker build -t docker-action --build-arg php_version="$PHP_VERSION" . && docker run -v $PWD:/app/ docker-action
