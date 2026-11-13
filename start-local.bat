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

echo [1/3] Menyiapkan environment lokal...
call php artisan config:clear >nul 2>nul

echo [2/3] Menjalankan Web Server & Auto-Monitor...
start "Forex AI - Web Server" /min cmd /c "php artisan serve --host=127.0.0.1 --port=8000"
start "Forex AI - Auto Monitor" /min cmd /c "php artisan schedule:work"

echo [3/3] Membuka dashboard di browser...
timeout /t 2 /nobreak >nul
start http://127.0.0.1:8000

echo.
echo ========================================================
echo   STATUS: APLIKASI SUDAH BERJALAN DENGAN SUKSES!
echo.
echo   - Alamat Web Lokal : http://127.0.0.1:8000
echo   - Alamat di EA MT5 : http://127.0.0.1:8000
echo.
echo   Jendela ini boleh Anda minimize.
echo   Untuk menghentikan server kapan saja, jalankan:
echo   stop-local.bat
echo ========================================================
echo.
pause

