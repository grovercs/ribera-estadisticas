@echo off
setlocal EnableDelayedExpansion

REM Ribera Estadisticas — Fase 1: Sincronizacion automatica de ventas mes actual
REM Se ejecuta via Task Scheduler de Windows. Usa PHP 8.4 de WAMP.

set PHP_PATH=C:\wamp64\bin\php\php8.4.24\php.exe
set PROJECT_DIR=C:\Proyectos\antigravity\ribera-estadisticas
set ARTISAN=%PROJECT_DIR%\artisan
set LOG_DIR=%PROJECT_DIR%\storage\logs

if not exist "%LOG_DIR%" mkdir "%LOG_DIR%"

set LOG_FILE=%LOG_DIR%\sync_sales_current_month.log
set ERROR_FILE=%LOG_DIR%\sync_sales_current_month.error.log

(
  echo ============================================================
  echo [%date% %time%] Iniciando sincronizacion sales --period=current_month
  "%PHP_PATH%" "%ARTISAN%" ribera:sync-to-supabase sales --period=current_month
  if !errorlevel! neq 0 (
    echo [%date% %time%] ERROR: El comando termino con codigo !errorlevel!
    exit /b !errorlevel!
  )
  echo [%date% %time%] Sincronizacion completada exitosamente
  echo ============================================================
) >> "%LOG_FILE%" 2>> "%ERROR_FILE%"

endlocal
