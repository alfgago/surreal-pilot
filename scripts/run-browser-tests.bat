@echo off
echo Setting up browser testing environment...

REM Ensure testing database exists
if not exist "database\testing.sqlite" (
    echo Creating testing database...
    type nul > database\testing.sqlite
)

REM Delete and recreate testing database to avoid migration issues
echo Recreating testing database...
if exist "database\testing.sqlite" del "database\testing.sqlite"
type nul > database\testing.sqlite

REM Run migrations for testing environment
echo Running migrations...
php artisan migrate --env=dusk.local --force

REM Seed the testing database
echo Seeding database...
php artisan db:seed --env=dusk.local --force
php artisan db:seed --class=TestUserSeeder --env=dusk.local --force

REM Clear caches for testing environment
echo Clearing caches...
php artisan config:clear --env=dusk.local
php artisan cache:clear --env=dusk.local
php artisan view:clear --env=dusk.local

REM Run browser tests
echo Running browser tests...
php artisan dusk --env=dusk.local %*

echo Browser testing complete.