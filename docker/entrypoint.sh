#!/bin/sh
set -e
cd /var/www/html

php bin/console cache:clear --no-warmup
php bin/console assets:install public --no-interaction

echo "Migrations…"
i=0
until php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration; do
    i=$((i + 1))
    [ "$i" -ge 15 ] && { echo "Base de données injoignable."; exit 1; }
    echo "En attente de MySQL ($i)…"; sleep 3
done

echo "Données de démonstration (idempotent)…"
php bin/console doctrine:fixtures:load --append --no-interaction

chown -R www-data:www-data var
exec "$@"
