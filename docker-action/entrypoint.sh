#!/bin/sh -l

printenv
pwd
ls -la /app-home

set -e

dep $*
