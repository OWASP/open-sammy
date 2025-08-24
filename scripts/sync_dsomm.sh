#!/bin/bash
echo "clone or pull DSOMM model repository"
./scripts/clone_dsomm.sh

echo "Syncing from DSOMM repo..."
php bin/console app:sync-from-dsomm --metamodel=2