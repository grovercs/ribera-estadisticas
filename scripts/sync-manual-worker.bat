@echo off
setlocal EnableDelayedExpansion

REM Ribera Estadisticas — Worker de solicitudes manuales de sincronizacion
REM Se ejecuta via Task Scheduler cada 1 minuto. Usa PHP 8.4 de WAMP.

set PHP_PATH=C:\wamp64\bin\php\php8.4.24\php.exe
set PROJECT_DIR=C:\Proyectos\antigravity\ribera-estadisticas
set ARTISAN=%PROJECT_DIR%\artisan
set LOG_DIR=%PROJECT_DIR%\storage\logs

if not exist "%LOG_DIR%" mkdir "%LOG_DIR%"

REM Log técnico del batch (stdout/stderr del wrapper). El worker PHP escribe en su propio log interno.
set BAT_LOG_FILE=%LOG_DIR%\sync_manual_worker.out.log
set BAT_ERROR_FILE=%LOG_DIR%\sync_manual_worker.error.log

(
  echo ============================================================
  echo [%date% %time%] Iniciando worker de solicitudes manuales
  "%PHP_PATH%" -d memory_limit=1024M "%ARTISAN%" ribera:process-sync-requests
  if !errorlevel! neq 0 (
    echo [%date% %time%] ERROR: El worker termino con codigo !errorlevel!
    exit /b !errorlevel!
  )
  echo [%date% %time%] Worker finalizado correctamente
  echo ============================================================
) >> "%BAT_LOG_FILE%" 2>> "%BAT_ERROR_FILE%"

endlocal
