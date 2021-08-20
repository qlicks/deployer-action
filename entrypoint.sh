#!/bin/bash

set -e

if [[ -z "${AUTH_JSON}" ]]; then
      echo "$AUTH_JSON" > auth.json
fi
dep $* 

