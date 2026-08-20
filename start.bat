@echo off
chcp 65001 >nul 2>&1
cd /d "%~dp0"

echo.
echo ==========================================
echo   Hypixel BedWars Dashboard + Friends
echo ==========================================
echo.
echo [DIR] %CD%
echo.

set "PHP_EXE="
set "PORT=8080"

if exist "%~dp0php\php.exe" (
    set "PHP_EXE=%~dp0php\php.exe"
    goto found
)

where php >nul 2>&1
if not errorlevel 1 (
    set "PHP_EXE=php"
    goto found
)

if exist "C:\xampp\php\php.exe" (
    set "PHP_EXE=C:\xampp\php\php.exe"
    goto found
)

if exist "C:\php\php.exe" (
    set "PHP_EXE=C:\php\php.exe"
    goto found
)

echo [X] PHP not found
echo.
echo Please run setup-local.bat first
echo.
pause
exit /b 1

:found
echo [OK] PHP: %PHP_EXE%
"%PHP_EXE%" -v
echo.

"%PHP_EXE%" -m | findstr /i /c:"curl" >nul
if errorlevel 1 (
    echo [X] curl extension missing
    echo.
    echo Please re-run setup-local.bat
    echo.
    pause
    exit /b 1
)
echo [OK] curl enabled
echo.

netstat -ano | findstr ":%PORT% " | findstr "LISTENING" >nul 2>&1
if not errorlevel 1 (
    echo [WARN] Port %PORT% is already in use.
    echo        Switching to port 8081 ...
    set "PORT=8081"
)

echo [OK] Starting server on port %PORT% ...
echo.
echo   Home:        http://localhost:%PORT%/
echo   Friends:     http://localhost:%PORT%/friends.html
echo   API Test:    http://localhost:%PORT%/api/test.php
echo   Tracker:     http://localhost:%PORT%/api/tracker.php
echo.
echo   Keep this window open. Close it to stop the server.
echo   Press Ctrl+C to stop.
echo ------------------------------------------
echo.

"%PHP_EXE%" -S localhost:%PORT% -t "%CD%"
if errorlevel 1 (
    echo.
    echo [X] Server failed to start.
    echo.
    pause
)
