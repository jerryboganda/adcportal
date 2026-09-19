@echo off
title PolytronX RIS - Local Dev Launcher
cd /d "%~dp0..\.."

echo ==========================================================
echo   PolytronX RIS - starting localhost stack
echo ----------------------------------------------------------
echo   Localhost stack:
echo     SPA : http://localhost:3000
echo     API : http://127.0.0.1:8000
echo ==========================================================
echo.

echo [1/2] Laravel API on http://127.0.0.1:8000 ...
netstat -ano | findstr /r /c:":8000 .*LISTENING" >nul 2>&1
if errorlevel 1 (
    start "RIS api" /min cmd /c "php artisan serve --host=127.0.0.1 --port=8000"
) else (
    echo        API already running - skipping
)

echo [2/2] Vite SPA on http://localhost:3000 ...
netstat -ano | findstr /r /c:":3000 .*LISTENING" >nul 2>&1
if errorlevel 1 (
    start "RIS vite" /min cmd /c "npm run dev"
) else (
    echo        Vite already running - skipping
)

C:\Windows\System32\timeout.exe /t 4 /nobreak >nul
start "" http://localhost:3000

echo.
echo ==========================================================
echo   All services started:
echo     SPA   : http://localhost:3000
echo     API   : http://127.0.0.1:8000/api/v1/health
echo.
echo   To stop everything, double-click RIS-STOP-localhost.bat
echo ==========================================================
echo.
pause
