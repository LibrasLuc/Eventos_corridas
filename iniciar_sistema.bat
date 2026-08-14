@echo off
setlocal
title Inicializador - Agenda de Corridas

set "MYSQL_DIR=C:\Users\01980344663\Desktop\mysql-9.7.1-winx64"
set "PHP_DIR=C:\Users\01980344663\Desktop\php\php-8.5.9-nts-Win32-vs17-x64"
set "APP_DIR=C:\Users\01980344663\Desktop\Projetos\Full\Eventos_corridas"

echo ========================================
echo   Iniciando Agenda Municipal de Corridas
echo ========================================
echo.

if not exist "%MYSQL_DIR%\bin\mysqld.exe" (
    echo ERRO: MySQL nao encontrado em:
    echo %MYSQL_DIR%\bin\mysqld.exe
    pause
    exit /b 1
)

if not exist "%PHP_DIR%\php.exe" (
    echo ERRO: PHP nao encontrado em:
    echo %PHP_DIR%\php.exe
    pause
    exit /b 1
)

if not exist "%APP_DIR%\login.php" (
    echo ERRO: Projeto nao encontrado em:
    echo %APP_DIR%
    pause
    exit /b 1
)

netstat -ano | findstr /R /C:":3306 .*LISTENING" >nul
if errorlevel 1 (
    echo [1/3] Iniciando MySQL...
    start "MySQL - Corridas" /D "%MYSQL_DIR%" "%MYSQL_DIR%\bin\mysqld.exe" --console
    timeout /t 4 /nobreak >nul
) else (
    echo [1/3] MySQL ja esta funcionando na porta 3306.
)

netstat -ano | findstr /R /C:":8000 .*LISTENING" >nul
if errorlevel 1 (
    echo [2/3] Iniciando servidor PHP...
    start "PHP - Agenda de Corridas" /D "%APP_DIR%" "%PHP_DIR%\php.exe" -S localhost:8000
    timeout /t 2 /nobreak >nul
) else (
    echo [2/3] Servidor ja esta funcionando na porta 8000.
)

echo [3/3] Abrindo o sistema no navegador...
start "" "http://localhost:8000/login.php"

echo.
echo Sistema iniciado. Nao feche as janelas do MySQL e do PHP.
timeout /t 3 /nobreak >nul
endlocal

