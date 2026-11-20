@echo off
title N Magazine - Local Server Launcher
color 0A
cls

echo ========================================================
echo   N MAGAZINE - CAMPUS NEWS ^& LIFESTYLE MAGAZINE
echo   Localhost Server Launcher
echo ========================================================
echo.

REM 1. Find PHP Executable
set PHP_BIN=
where php >nul 2>&1
if %ERRORLEVEL% equ 0 (
    set PHP_BIN=php
) else if exist "C:\xamppp\php\php.exe" (
    set PHP_BIN=C:\xamppp\php\php.exe
) else if exist "C:\xampp\php\php.exe" (
    set PHP_BIN=C:\xampp\php\php.exe
) else (
    echo [ERROR] PHP executable was not found!
    echo Please install XAMPP or add PHP to your Windows PATH.
    echo Searched locations: PATH, C:\xamppp\php\php.exe, C:\xampp\php\php.exe
    pause
    exit /b 1
)

echo [OK] Using PHP: %PHP_BIN%

REM 2. Check MySQL Status
echo [..] Checking MySQL status on port 3306...
powershell -Command "try { $client = New-Object System.Net.Sockets.TcpClient('127.0.0.1', 3306); $client.Close(); exit 0 } catch { exit 1 }" >nul 2>&1
if %ERRORLEVEL% equ 0 (
    echo [OK] MySQL is running on port 3306!
) else (
    echo [WARNING] MySQL does NOT appear to be running on port 3306.
    echo Please start the MySQL module from your XAMPP Control Panel.
)

echo.
echo Starting local web server at http://localhost:8000 ...
echo Press Ctrl+C in this window anytime to stop the server.
echo.

start "" "http://localhost:8000/setup.php"
"%PHP_BIN%" -S localhost:8000
