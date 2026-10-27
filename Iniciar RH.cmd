@echo off
cd /d "%~dp0"
set "RH_PHP=php"
where php >nul 2>nul
if errorlevel 1 set "RH_PHP=%~dp0..\ferramentas\php\php.exe"
if not exist "%RH_PHP%" if /i not "%RH_PHP%"=="php" (
  echo PHP nao encontrado. Instale PHP 8.2 ou superior com PDO, SQLite, MySQL, mbstring e Zip.
  pause
  exit /b 1
)
set "RH_DB_DRIVER=sqlite"
echo.
echo Portal Radeane RH em PHP - http://localhost:4318
echo Mantenha esta janela aberta enquanto usar o sistema.
echo.
"%RH_PHP%" -S 127.0.0.1:4318 -t PUBLIC PUBLIC/router.php
pause
