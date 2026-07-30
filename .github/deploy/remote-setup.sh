#!/bin/bash
# Runs on the Dreamhost server itself, invoked over plain SSH by
# .github/workflows/deploy.yml after the two archives have been scp'd up.
set -e

mkdir -p ~/selbuildi-app ~/selbuildi.com
tar xzf ~/deploy-app.tar.gz -C ~/selbuildi-app
tar xzf ~/deploy-public.tar.gz -C ~/selbuildi.com
rm -f ~/deploy-app.tar.gz ~/deploy-public.tar.gz
cd ~/selbuildi-app

php -v

if ! command -v composer >/dev/null 2>&1; then
    if [ ! -f ~/composer.phar ]; then
        php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
        php composer-setup.php --install-dir="$HOME" --filename=composer.phar
        rm -f composer-setup.php
    fi
    COMPOSER_BIN="php $HOME/composer.phar"
else
    COMPOSER_BIN="composer"
fi

export COMPOSER_MEMORY_LIMIT=-1
$COMPOSER_BIN install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# .env lives only on the server and is never touched by this workflow - it
# isn't part of either archive, and extracting on top of an existing
# directory doesn't delete files the archive doesn't contain.
php artisan storage:link || true
php artisan migrate --force || echo "migrate failed - .env probably not set up yet"
php artisan config:cache || echo "config:cache failed - .env probably not set up yet"
php artisan route:cache || true
php artisan view:cache || true
