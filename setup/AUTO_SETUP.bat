@echo off
REM clayon/setup/AUTO_SETUP.bat
REM 
REM Automated setup script for Clayon SMS Platform (Windows)
REM Usage: AUTO_SETUP.bat

setlocal enabledelayedexpansion

echo ==========================================
echo   Clayon SMS Platform - Auto Setup
echo ==========================================
echo.

REM Check PHP
where php >nul 2>nul
if errorlevel 1 (
    echo ERROR: PHP not found. Please install PHP 7.4 or higher.
    pause
    exit /b 1
)

for /f "tokens=*" %%i in ('php -v') do (
    set phpver=%%i
    goto phpver_done
)
:phpver_done
echo PHP detected: !phpver!
echo.

REM Get script directory
set SCRIPT_DIR=%~dp0
for %%A in ("%SCRIPT_DIR%..\.") do set PROJECT_ROOT=%%~fA

echo Project path: %PROJECT_ROOT%
echo.

REM Check if run-all-setup.php exists
if not exist "%SCRIPT_DIR%run-all-setup.php" (
    echo ERROR: run-all-setup.php not found
    pause
    exit /b 1
)

echo Running setup...
echo ==========================================
echo.

REM Execute setup
php "%SCRIPT_DIR%run-all-setup.php"

echo.
echo ==========================================
echo Setup Complete!
echo ==========================================
echo.
echo Next steps:
echo 1. Save the Admin API Key displayed above
echo 2. Update TALKSASA_API_KEY in clayon\.env2
echo 3. Add cron worker (Windows Task Scheduler or WSL)
echo 4. Access dashboard: http://localhost/clayon/pages/login.html
echo.
echo For more info: http://localhost/clayon/QUICK_START.php
echo.

pause
