#!/bin/bash
environ=$1

if [ -z "$environ" ]
then
    environ="development"
fi
echo "Setting up $environ database"

echo "drop db"
php bin/console doctrine:database:drop --force --env=$environ
echo "create db"
php bin/console doctrine:database:create --env=$environ
echo "migration"
php bin/console doctrine:migrations:migrate --no-interaction --env=$environ

echo "clone or pull OWASP-SAMM model repository"
sh ./scripts/clone_owasp_samm.sh --env=$environ

echo "Syncing from OWASP SAMM repo..."
php bin/console app:sync-from-owasp-samm 1 --env=$environ


echo "clone or pull DSOMM model repository"
sh ./scripts/clone_dsomm.sh --env=$environ

echo "Syncing from DSOMM repo..."
php bin/console app:sync-from-dsomm --metamodel=2 --env=$environ

