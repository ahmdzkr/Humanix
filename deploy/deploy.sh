#!/usr/bin/env bash
# Deploy rilis HUMANIX. Pemakaian: ./deploy/deploy.sh v1.2.0
# Berhenti di galat pertama; aplikasi tetap dalam mode pemeliharaan bila gagal di tengah jalan
# (lihat docs/DEPLOYMENT.md bagian 13 untuk rollback).
set -euo pipefail

TAG="${1:?Pemakaian: deploy.sh <tag-rilis>}"
cd "$(dirname "$0")/.."

php artisan down --retry=60
php artisan humanix:backup --no-prune

git fetch --tags
git checkout "$TAG"

composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build

php artisan migrate --force
php artisan optimize
php artisan queue:restart

php artisan up
echo "Deploy $TAG selesai."
