#!/bin/sh
set -e

echo "Running migrations..."
php artisan migrate --force

echo "Clearing cache..."
php artisan optimize:clear

echo "Optimizing..."
php artisan optimize

echo "Deployment finished successfully!"
