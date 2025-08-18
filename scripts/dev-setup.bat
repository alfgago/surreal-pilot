@echo off
echo Setting up SurrealPilot development environment...

echo.
echo 1. Installing PHP dependencies...
composer install

echo.
echo 2. Installing Node dependencies...
npm install

echo.
echo 3. Setting up environment file...
if not exist .env (
    copy .env.example .env
    echo Environment file created from example
) else (
    echo Environment file already exists
)

echo.
echo 4. Generating application key...
php artisan key:generate

echo.
echo 5. Creating database if it doesn't exist...
if not exist database\database.sqlite (
    type nul > database\database.sqlite
    echo SQLite database created
)

echo.
echo 6. Running migrations...
php artisan migrate --seed

echo.
echo 7. Installing NativePHP...
php artisan native:install

echo.
echo 8. Building assets...
npm run build

echo.
echo 9. Clearing caches...
php artisan config:clear
php artisan cache:clear
php artisan view:clear

echo.
echo Development environment setup complete!
echo.
echo To start development:
echo - Run: composer run dev (for web development)
echo - Run: composer run native:dev (for desktop development)
echo.
pause