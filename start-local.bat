@echo off
title Forex Day Trader AI - Local Server
cd /d "%~dp0"

echo ========================================================
echo        FOREX DAY TRADER AI - RUNNING LOCALLY
echo ========================================================
echo.

where php >nul 2>nul
if %errorlevel% neq 0 (
    set "PATH=C:\laragon\bin\php\php-8.4.16-Win32-vs17-x64;%PATH%"
)

echo [1/4] Menyiapkan environment lokal...
for /f "tokens=5" %%a in ('netstat -aon ^| findstr :8000 ^| findstr LISTENING') do (
    taskkill /f /pid %%a >nul 2>nul
)
taskkill /f /fi "WINDOWTITLE eq Forex AI - Web Server*" >nul 2>nul
taskkill /f /fi "WINDOWTITLE eq Forex AI - Auto Monitor*" >nul 2>nul
taskkill /f /fi "WINDOWTITLE eq Forex AI - Telegram Listener*" >nul 2>nul

set PHP_CLI_SERVER_WORKERS=4
call php artisan config:clear >nul 2>nul

echo [2/4] Menjalankan Web Server (4 Workers)...
start "Forex AI - Web Server" /min /d "%~dp0" cmd /c "set PHP_CLI_SERVER_WORKERS=4 && php artisan serve --host=127.0.0.1 --port=8000 --no-reload"

echo [3/4] Menjalankan Auto-Monitor & Telegram Listener...
start "Forex AI - Auto Monitor" /min /d "%~dp0" cmd /c "php artisan schedule:work"
start "Forex AI - Telegram Listener" /min /d "%~dp0" cmd /c "php artisan telegram:poll"

echo [4/4] Membuka dashboard di browser...
ping -n 3 127.0.0.1 >nul
start http://127.0.0.1:8000

echo.
echo ========================================================
echo   STATUS: APLIKASI SUDAH BERJALAN DENGAN SUKSES!
echo.
echo   - Alamat Web Lokal      : http://127.0.0.1:8000
echo   - Alamat di EA MT5      : http://127.0.0.1:8000
echo   - Worker Web Server     : 4 Multi-threaded workers
echo   - Telegram Listener     : Background real-time polling
echo.
echo   Jendela ini boleh Anda minimize.
echo   Untuk menghentikan server kapan saja, jalankan:
echo   stop-local.bat
echo ========================================================
echo.
pause
