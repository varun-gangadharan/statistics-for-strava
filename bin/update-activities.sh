#!/bin/bash

# Run migrations.
bin/console doctrine:migrations:migrate --no-interaction

# Update strava stats.
bin/console app:strava:import-data
bin/console app:strava:build-files

# Vacuum database
bin/console app:strava:vacuum