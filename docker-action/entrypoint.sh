#!/bin/sh -l

printenv
pwd
ls -la 

set -e

dep $*
