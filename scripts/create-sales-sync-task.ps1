#Requires -RunAsAdministrator

# Ribera Estadisticas — Fase 1: Crear tarea programada para sincronizar sales --period=current_month
# Ejecutar con PowerShell como Administrador

$TaskName = "Ribera Sync - Sales Current Month"
$Description = "Sincroniza ventas del mes actual desde el ERP SQL Server hacia Supabase cada hora en horario comercial."

$PhpPath = "C:\wamp64\bin\php\php8.4.24\php.exe"
$ProjectDir = "C:\Proyectos\antigravity\ribera-estadisticas"
$ScriptPath = "$ProjectDir\scripts\sync-sales-current-month.bat"

# Trigger: cada hora entre las 08:00 y 21:00, todos los dias
$Trigger = New-ScheduledTaskTrigger -Daily -At 08:00
# Ajustar a repeticion de 1 hora durante 13 horas
# PowerShell no expone intervalos directamente en New-ScheduledTaskTrigger, asi que usamos Repetition
$Trigger.Repetition = $(New-ScheduledTaskTrigger -Once -At 08:00 -RepetitionInterval (New-TimeSpan -Hours 1) -RepetitionDuration (New-TimeSpan -Hours 13)).Repetition

# Accion: ejecutar el batch y heredar el directorio de trabajo
$Action = New-ScheduledTaskAction -Execute $ScriptPath -WorkingDirectory $ProjectDir

# Opciones de la tarea
$Principal = New-ScheduledTaskPrincipal -UserId "NT AUTHORITY\SYSTEM" -LogonType ServiceAccount -RunLevel Highest
$Settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable -ExecutionTimeLimit (New-TimeSpan -Minutes 60) -MultipleInstances IgnoreNew

# Crear o actualizar la tarea
$Existing = Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
if ($Existing) {
    Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false
}

Register-ScheduledTask -TaskName $TaskName -Description $Description -Action $Action -Trigger $Trigger -Principal $Principal -Settings $Settings -Force

Write-Host "Tarea programada creada/actualizada: $TaskName" -ForegroundColor Green
Write-Host "Ejecutable: $ScriptPath" -ForegroundColor Cyan
Write-Host "Primer disparo: 08:00, repeticion cada hora hasta las 21:00." -ForegroundColor Cyan
