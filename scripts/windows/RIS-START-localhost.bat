@echo off
title PolytronX RIS - Local Dev Launcher
cd /d "%~dp0..\.."

echo ==========================================================
echo   PolytronX RIS - starting localhost stack
echo ----------------------------------------------------------
echo   WARNING: this dev stack runs on the LIVE production
echo   database and file storage. Changes here are real.
echo ==========================================================
echo.

echo [1/4] SSH tunnel to production PostgreSQL...
"C:\Program Files\Git\bin\bash.exe" -c "bash scripts/dev-tunnel.sh start"

echo [2/4] Storage sync (uploads + storage/app, ~2s cadence)...
"C:\Program Files\Git\bin\bash.exe" -c "bash scripts/dev-storage-sync.sh start"

echo [3/4] Laravel API on port 8000 ...
netstat -ano | findstr /r /c:":8000 .*LISTENING" >nul 2>&1
if errorlevel 1 (
    start "RIS api" /min cmd /c "php artisan serve --host=0.0.0.0 --port=8000"
) else (
    echo        API already running - skipping
)

echo [4/4] Vite SPA on http://localhost:3000 ...
netstat -ano | findstr /r /c:":3000 .*LISTENING" >nul 2>&1
if errorlevel 1 (
    start "RIS vite" /min cmd /c "npm run dev"
) else (
    echo        Vite already running - skipping
)

C:\Windows\System32\timeout.exe /t 6 /nobreak >nul
start "" http://localhost:3000

echo.
echo ==========================================================
echo   All services started:
echo     SPA   : http://localhost:3000
echo     API   : http://127.0.0.1:8000/api/v1/health
echo     DB    : LIVE production PostgreSQL (via SSH tunnel)
echo     Files : synced with production every ~2 seconds
echo.
echo   To stop everything, double-click RIS-STOP-localhost.bat
echo ==========================================================
echo.
pause
