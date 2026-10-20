@echo off
rem Runs the site at http://localhost:8000 for testing, with the local "naf"
rem database (XAMPP MySQL). Clean URLs like /shop come from tools\dev-router.php.
rem Close this window to stop the site; MySQL keeps running in its own window.
cd /d "%~dp0"
tasklist /fi "imagename eq mysqld.exe" | find /i "mysqld.exe" >nul || start "MySQL" /min C:\xampp\mysql\bin\mysqld.exe --defaults-file=C:\xampp\mysql\bin\my.ini --standalone
start "" http://localhost:8000/
C:\xampp\php\php.exe -S localhost:8000 tools\dev-router.php
