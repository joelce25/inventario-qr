@echo off
setlocal
cd /d "%~dp0"
set "PHP_EXE=C:\xampp\php\php.exe"
if not exist "%PHP_EXE%" set "PHP_EXE=php"
"%PHP_EXE%" -v >nul 2>&1
if errorlevel 1 (
  echo No se encontro PHP. Instala XAMPP en C:\xampp o agrega php.exe al PATH.
  pause
  exit /b 1
)
echo Activa MySQL e importa sql\inventario.sql antes de continuar.
echo Abre http://localhost:8000 en tu navegador. Usa Ctrl+C para detener el servidor.
"%PHP_EXE%" -S localhost:8000 -t public
pause
