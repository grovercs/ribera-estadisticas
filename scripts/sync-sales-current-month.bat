@echo off
setlocal EnableDelayedExpansion

REM Ribera Estadisticas — Sincronizacion automatica de ventas mes actual
REM Se ejecuta via Task Scheduler de Windows. Usa PHP 8.4 de WAMP.
REM NO acepta argumentos. NO detecta hora. Ejecuta SIEMPRE con source=auto.

set PHP_PATH=C:\wamp64\bin\php\php8.4.24\php.exe
set PROJECT_DIR=C:\Proyectos\antigravity\ribera-estadisticas
set ARTISAN=%PROJECT_DIR%\artisan
set LOG_DIR=%PROJECT_DIR%\storage\logs

if not exist "%LOG_DIR%" mkdir "%LOG_DIR%"

set SOURCE=auto
set LOG_FILE=%LOG_DIR%\sync_sales_%SOURCE%.log
set ERROR_FILE=%LOG_DIR%\sync_sales_%SOURCE%.error.log

(
  echo ============================================================
  echo [%date% %time%] Iniciando sincronizacion sales --period=current_month (source=%SOURCE%)
  "%PHP_PATH%" -d memory_limit=1024M "%ARTISAN%" ribera:sync-sales-locked --source=%SOURCE%
  if !errorlevel! neq 0 (
    echo [%date% %time%] ERROR: El comando termino con codigo !errorlevel!
    exit /b !errorlevel!
  )
  echo [%date% %time%] Sincronizacion completada exitosamente
  echo ============================================================
) >> "%LOG_FILE%" 2>> "%ERROR_FILE%"

endlocal
