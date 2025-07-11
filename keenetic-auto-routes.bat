@echo off
title Keenetic routes auto-update
setlocal enabledelayedexpansion

:: cfg
set PHP_FOLDER=%~dp0php
set PHP_EXE=%PHP_FOLDER%\php.exe
set PHP_URL=https://windows.php.net/downloads/releases/php-8.1.32-nts-Win32-vs16-x64.zip
set PHP_ZIP=php.zip
set SCRIPT_URL=https://raw.githubusercontent.com/hellcodewriter/keenetic-auto-routes/refs/heads/main/app.php
set SCRIPT_FILE=%~dp0app.php
set ROUTES_URI=https://raw.githubusercontent.com/hellcodewriter/keenetic-auto-routes/refs/heads/main/routes.bat
set ROUTES_FILE=%~dp0routes.bat


@echo off
:: check admin permissions
powershell -Command "If (-not ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole] 'Administrator')) { exit 1 }"

if %errorlevel%==1 (
    echo Run this script as admin! (is needed to enable telnet)
    pause
    exit
)

dism /online /Enable-Feature /FeatureName:TelnetClient /NoRestart

:: Check php on local
if exist "%PHP_EXE%" (
    echo [INFO] PHP already installed.
) else (
    echo [INFO] PHP not found, Downloading...
    powershell -Command "Invoke-WebRequest -Uri '%PHP_URL%' -OutFile '%PHP_ZIP%'"
    echo [INFO] Unpacking...
    powershell -Command "Expand-Archive -Path '%PHP_ZIP%' -DestinationPath '%PHP_FOLDER%'"
    del /f /q "%PHP_ZIP%"
)

:: Download php script
echo [INFO] Downloading php script...
powershell -Command "Invoke-WebRequest -Uri '%SCRIPT_URL%' -OutFile '%SCRIPT_FILE%'"

:: Download routes file
echo [INFO] Downloading php script...
powershell -Command "Invoke-WebRequest -Uri '%ROUTES_URI%' -OutFile '%ROUTES_FILE%'"

echo PHP_EXE
echo [INFO] Launching php script...
"%PHP_EXE%" "%SCRIPT_FILE%"

pause
