@echo off
title Forex Day Trader AI - Hentikan Server
cd /d "%~dp0"

echo ========================================================
echo        MENGHENTIKAN SERVER LOKAL FOREX DAY TRADER
echo ========================================================
echo.

taskkill /f /fi "WINDOWTITLE eq Forex AI - Web Server*" >nul 2>nul
taskkill /f /fi "WINDOWTITLE eq Forex AI - Auto Monitor*" >nul 2>nul
taskkill /f /fi "WINDOWTITLE eq Forex AI - Telegram Listener*" >nul 2>nul

for /f "tokens=5" %%a in ('netstat -aon ^| findstr :8000 ^| findstr LISTENING') do (
    taskkill /f /pid %%a >nul 2>nul
)

echo Server, auto-monitor, dan telegram listener telah dihentikan.
ping -n 3 127.0.0.1 >nul
