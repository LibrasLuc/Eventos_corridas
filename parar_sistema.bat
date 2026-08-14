@echo off
setlocal
title Encerrar - Agenda de Corridas

echo Encerrando os servidores iniciados para o sistema...

for /f "tokens=5" %%P in ('netstat -ano ^| findstr /R /C:":8000 .*LISTENING"') do taskkill /PID %%P /F >nul 2>&1

set "MYSQL_ADMIN=C:\Users\01980344663\Desktop\mysql-9.7.1-winx64\bin\mysqladmin.exe"
if exist "%MYSQL_ADMIN%" "%MYSQL_ADMIN%" -u root shutdown >nul 2>&1

echo Sistema encerrado.
timeout /t 2 /nobreak >nul
endlocal
