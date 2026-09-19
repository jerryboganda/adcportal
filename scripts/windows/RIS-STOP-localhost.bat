@echo off
title PolytronX RIS - Stop Local Dev
cd /d "%~dp0..\.."

echo ==========================================================
echo   PolytronX RIS - stopping localhost stack
echo ==========================================================
echo.

echo [2/2] Stopping API + Vite (by window, then by port as fallback)...
C:\Windows\System32\taskkill.exe /fi "WINDOWTITLE eq RIS api*" /t /f >nul 2>&1
C:\Windows\System32\taskkill.exe /fi "WINDOWTITLE eq RIS vite*" /t /f >nul 2>&1
for /f "tokens=5" %%p in ('netstat -ano ^| findstr /r /c:":8000 .*LISTENING"') do C:\Windows\System32\taskkill.exe /pid %%p /t /f >nul 2>&1
for /f "tokens=5" %%p in ('netstat -ano ^| findstr /r /c:":3000 .*LISTENING"') do C:\Windows\System32\taskkill.exe /pid %%p /t /f >nul 2>&1

echo.
echo Done. Localhost stack is stopped.
echo.
pause
