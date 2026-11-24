@echo off
chcp 65001 >nul
echo ===============================================================
echo   Aikchol Hospital - Labor Room Document Management System (LRDMS)
echo   Production Initial Setup Script
echo ===============================================================
echo.

cd /d "%~dp0.."

echo [*] Checking .env configuration...
if not exist .env (
    if exist .env.production (
        echo     Copying .env.production to .env...
        copy .env.production .env >nul
    ) else (
        echo     [!] Warning: .env.production not found. Please create .env manually.
    )
) else (
    echo     .env file already exists.
)

echo.
echo [*] Running Database Migrations...
php artisan migrate --force
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] Migration failed! Please check MySQL connection in .env
    pause
    exit /b 1
)

echo.
echo [*] Seeding Master Data and Initial Admin User...
php artisan db:seed --force
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] Database seeding failed!
    pause
    exit /b 1
)

echo.
echo [*] Caching Configuration, Routes, and Blade Views...
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo.
echo ===============================================================
echo   SUCCESS: Installation and Setup completed successfully!
echo.
echo   URL       : http://191.1.1.6:8082/lrdupload
echo   Username  : admin
echo   Password  : Admin@Aikchol2026!
echo ===============================================================
echo.
pause
