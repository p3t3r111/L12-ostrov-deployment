#!/bin/bash
set -e

git pull

php artisan optimize:clear

echo "Deploy hotový"
