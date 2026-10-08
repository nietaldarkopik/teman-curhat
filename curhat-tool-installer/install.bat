@echo off
setlocal EnableExtensions
title Instalasi Studio Teman Curhat
set "TARGET=C:\wamp64\www\curhat-tool\app"
set "SRC=%~dp0overlay"

echo.
echo ===  Instalasi Studio Teman Curhat  ===
echo Tujuan: %TARGET%
echo.

where php >nul 2>nul
if errorlevel 1 (
  echo [X] PHP tidak ditemukan di PATH.
  echo     Tambahkan folder PHP WAMP, misalnya C:\wamp64\bin\php\php8.3.x, ke PATH Windows,
  echo     lalu tutup dan buka lagi jendela ini.
  goto :fail
)
where composer >nul 2>nul
if errorlevel 1 (
  echo [X] Composer belum terpasang. Unduh dari https://getcomposer.org/download/
  goto :fail
)

for /f "delims=" %%i in ('where php') do (
  set "PHPEXE=%%i"
  goto :gotphp
)
:gotphp
echo PHP: %PHPEXE%
"%PHPEXE%" -r "exit(version_compare(PHP_VERSION,'8.2.0','>=')?0:1);"
if errorlevel 1 (
  echo [X] Butuh PHP 8.2 atau lebih baru. Ganti versi PHP di PATH.
  goto :fail
)

if exist "%TARGET%\artisan" (
  echo Project Laravel sudah ada, file aplikasi akan diperbarui.
) else (
  echo Membuat project Laravel baru. Ini butuh beberapa menit...
  cd /d C:\wamp64\www || goto :fail
  call composer create-project laravel/laravel curhat-tool\app --no-interaction
  if errorlevel 1 goto :fail
)

echo Menyalin file aplikasi...
xcopy "%SRC%\*" "%TARGET%\" /E /Y /I /Q >nul
if errorlevel 1 goto :fail
cd /d "%TARGET%"

if not exist "storage\.curhat-env-done" (
  copy /Y ".env.curhat" ".env" >nul
  "%PHPEXE%" artisan key:generate --force
  echo ok> "storage\.curhat-env-done"
) else (
  echo File .env lama dipertahankan.
)

"%PHPEXE%" artisan config:clear >nul
"%PHPEXE%" artisan view:clear >nul
"%PHPEXE%" artisan curhat:install
if errorlevel 1 goto :fail

>"%TARGET%\scheduler\php-path.txt" echo %PHPEXE%
schtasks /Create /F /SC MINUTE /MO 1 /TN "CurhatTool Scheduler" /TR "wscript.exe \"%TARGET%\scheduler\run-hidden.vbs\"" >nul
if errorlevel 1 (
  echo [!] Task Scheduler gagal dibuat. Coba jalankan install.bat dengan klik kanan, Run as administrator.
) else (
  echo Scheduler Windows terpasang: sinkron DM tiap 15 menit dan proses upload tiap menit.
)

echo.
echo === Selesai ===
echo Buka http://localhost/curhat-tool\app/public
echo Lalu isi menu Pengaturan. Panduan lengkap ada di PANDUAN.md
start "" "http://localhost/curhat-tool\app/public"
pause
exit /b 0

:fail
echo.
echo Instalasi berhenti karena ada kesalahan di atas.
pause
exit /b 1
