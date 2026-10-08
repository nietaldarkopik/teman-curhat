@echo off
rem Dipanggil Task Scheduler Windows setiap menit (lewat run-hidden.vbs).
cd /d "%~dp0.."
set /p PHPEXE=<"%~dp0php-path.txt"
"%PHPEXE%" artisan schedule:run >> storage\logs\scheduler.log 2>&1
