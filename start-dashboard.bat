@echo off
title Appify Next.js Dashboard
cd /d "%~dp0dashboard"

set "PATH=C:\Program Files\nodejs;%PATH%"

echo ===================================================
echo   Starting Appify Dashboard [Next.js]
echo ===================================================

if not exist "node_modules" (
    echo [NPM] Installing dependencies - please wait...
    call "C:\Program Files\nodejs\npm.cmd" install
)

echo.
echo ===================================================
echo   Dashboard is starting at: http://localhost:3000
echo ===================================================
echo Press Ctrl+C to stop the dashboard server.
echo.

call "C:\Program Files\nodejs\npm.cmd" run dev

if %errorlevel% neq 0 (
    echo.
    echo Server stopped with error.
    pause
)
