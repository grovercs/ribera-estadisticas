#Requires -RunAsAdministrator
# Prepare a replacement or fresh installation. It deliberately starts disabled.
[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
if ($env:COMPUTERNAME -ne 'Direccion1') { throw 'Ejecutar solo en Direccion1.' }
$project = Split-Path $PSScriptRoot -Parent
$name = 'Ribera Sync - Process Manual Requests'
if (Get-ScheduledTask -TaskName $name -ErrorAction SilentlyContinue) {
    throw 'La tarea ya existe. No se modifica la instalacion operativa.'
}
$php = (Get-ItemProperty 'HKLM:\SYSTEM\CurrentControlSet\Services\RiberaLaravel\Parameters').Application
if (-not (Test-Path -LiteralPath $php) -or -not (Test-Path (Join-Path $project 'artisan'))) {
    throw 'No se encuentra PHP o el proyecto.'
}
& $php (Join-Path $project 'artisan') help ribera:process-sync-requests
if ($LASTEXITCODE -ne 0) { throw 'Laravel no reconoce el comando.' }
$batch = Join-Path $PSScriptRoot 'sync-manual-worker-direccion1.bat'
if (Test-Path -LiteralPath $batch) {
    throw 'El lanzador ya existe. Revisarlo antes de preparar otra instalacion.'
}

@"
@echo off
setlocal
set "PHP_PATH=$php"
set "PROJECT_DIR=$project"
set "LOG_DIR=%PROJECT_DIR%\storage\logs"
if not exist "%LOG_DIR%" mkdir "%LOG_DIR%"
cd /d "%PROJECT_DIR%"
if errorlevel 1 exit /b 1
echo [%date% %time%] DIRECCION1 inicio >> "%LOG_DIR%\sync_manual_worker.out.log"
"%PHP_PATH%" -d memory_limit=1024M "%PROJECT_DIR%\artisan" ribera:process-sync-requests >> "%LOG_DIR%\sync_manual_worker.out.log" 2>> "%LOG_DIR%\sync_manual_worker.error.log"
set "RIBERA_EXIT=%ERRORLEVEL%"
echo [%date% %time%] DIRECCION1 fin codigo=%RIBERA_EXIT% >> "%LOG_DIR%\sync_manual_worker.out.log"
exit /b %RIBERA_EXIT%
"@ | Set-Content -LiteralPath $batch -Encoding Ascii

$action = New-ScheduledTaskAction -Execute $env:ComSpec `
    -Argument ('/d /c ""' + $batch + '""') -WorkingDirectory $project
$daily = New-ScheduledTaskTrigger -Daily -At '00:00'
$repeat = New-ScheduledTaskTrigger -Once -At (Get-Date) `
    -RepetitionInterval (New-TimeSpan -Minutes 1) `
    -RepetitionDuration (New-TimeSpan -Days 1)
$daily.Repetition = $repeat.Repetition
$boot = New-ScheduledTaskTrigger -AtStartup
$boot.Delay = 'PT1M'
$account = New-ScheduledTaskPrincipal -UserId 'SYSTEM' `
    -LogonType ServiceAccount -RunLevel Highest
$settings = New-ScheduledTaskSettingsSet -Disable -StartWhenAvailable `
    -MultipleInstances IgnoreNew -RestartCount 3 `
    -RestartInterval (New-TimeSpan -Minutes 1) `
    -ExecutionTimeLimit (New-TimeSpan -Minutes 60) `
    -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
Register-ScheduledTask -TaskName $name -Action $action -Trigger @($daily, $boot) `
    -Principal $account -Settings $settings | Out-Null
Get-ScheduledTask -TaskName $name | Format-List TaskName, State
Write-Output 'Preparada y DESHABILITADA. Desactivar primero el publicador anterior antes de habilitarla.'
