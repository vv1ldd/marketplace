#!/bin/sh
set -e

echo "Running migrations..."
php artisan migrate --force

if [ ! -f scripts/node_modules/bip322-js/dist/Verifier.js ]; then
  echo "Installing Bitcoin BIP-322 verifier runtime..."
  cd scripts && npm ci --omit=dev && cd ..
fi

echo "Clearing cache..."
php artisan optimize:clear

echo "Optimizing..."
php artisan config:cache
php artisan event:cache

# Public routes are registered once per market domain; route:cache fails on duplicate names.
if php artisan route:cache 2>/dev/null; then
  echo "Route cache updated."
else
  echo "Skipping route cache (duplicate route names across domains)."
fi

echo "Checking Bitcoin Vault binding readiness..."
php artisan meanly:bitcoin-binding-readiness

echo "Running deploy readiness gate (Providers, DGS Sidecar, DB, Queue, Cache)..."
php artisan meanly:production-readiness --deploy-gate

echo "Deployment finished successfully!"
