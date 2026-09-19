@echo off
title PolytronX RIS - Stop Local Dev
cd /d "%~dp0..\.."

echo ==========================================================
echo   PolytronX RIS - stopping localhost stack
echo ==========================================================
echo [1/2] Stopping storage sync + SSH tunnel...
"C:\Program Files\Git\bin\bash.exe" -c "bash scripts/dev-storage-sync.sh stop; bash scripts/dev-tunnel.sh stop"

echo [2/2] Stopping API + Vite (by window, then by port as fallback)...
C:\Windows\System32\taskkill.exe /fi "WINDOWTITLE eq RIS api*" /t /f >nul 2>&1
C:\Windows\System32\taskkill.exe /fi "WINDOWTITLE eq RIS vite*" /t /f >nul 2>&1
for /f "tokens=5" %%p in ('netstat -ano ^| findstr /r /c:":8000 .*LISTENING"') do C:\Windows\System32\taskkill.exe /pid %%p /t /f >nul 2>&1
for /f "tokens=5" %%p in ('netstat -ano ^| findstr /r /c:":3000 .*LISTENING"') do C:\Windows\System32\taskkill.exe /pid %%p /t /f >nul 2>&1

echo.
echo Done. Localhost stack is stopped.
echo.
pause
