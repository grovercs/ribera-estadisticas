#Requires -RunAsAdministrator
[CmdletBinding()]
param(
    [string]$TaskName = "Ribera Sync - Process Manual Requests"
)

$ErrorActionPreference = 'Stop'

$ProjectDir  = "C:\Proyectos\antigravity\ribera-estadisticas"
$BatchPath   = Join-Path $ProjectDir "scripts\sync-manual-worker.bat"

if (-not (Test-Path $BatchPath -PathType Leaf)) {
    throw "No existe el batch del worker manual: $BatchPath"
}

# ---------------------------------------------------------------------------
# Requerir admin
# ---------------------------------------------------------------------------
$currentPrincipal = New-Object Security.Principal.WindowsPrincipal(
    [Security.Principal.WindowsIdentity]::GetCurrent()
)
$isAdmin = $currentPrincipal.IsInRole(
    [Security.Principal.WindowsBuiltInRole]::Administrator
)
if (-not $isAdmin) {
    throw "Este script requiere PowerShell ejecutado como Administrador."
}

# ---------------------------------------------------------------------------
# Eliminar tarea previa si existe
# ---------------------------------------------------------------------------
$existing = Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
if ($existing) {
    Write-Host "Eliminando tarea existente '$TaskName'..."
    Stop-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
    Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false
}

# ---------------------------------------------------------------------------
# Crear trigger DIARIO real con repeticion cada 1 minuto durante 24 horas.
#
# El anterior -Once -RepetitionDuration P1D se ejecuta cada minuto SOLO durante
# 24 h y no se reinicia al dia siguiente. schtasks.exe con /SC DAILY genera un
# MSFT_TaskDailyTrigger que si se reinicia cada dia.
# ---------------------------------------------------------------------------
$taskArgs = @(
    "/Create"
    "/TN", "`"$TaskName`""
    "/TR", "`"$BatchPath`""
    "/SC", "DAILY"
    "/MO", "1"
    "/ST", "00:00"
    "/RI", "1"
    "/DU", "24:00"
    "/RU", "`"NT AUTHORITY\SYSTEM`""
    "/RL", "HIGHEST"
    "/F"
)

Write-Host "Creando tarea con schtasks.exe: DAILY /ST 00:00 /RI 1 /DU 24:00"
$schtasksOutput = & schtasks.exe $taskArgs 2>&1
if ($LASTEXITCODE -ne 0) {
    throw "schtasks.exe fallo con codigo $($LASTEXITCODE): $schtasksOutput"
}

# ---------------------------------------------------------------------------
# Forzar directorio de trabajo del batch al root del proyecto
# ---------------------------------------------------------------------------
$task = Get-ScheduledTask -TaskName $TaskName
if ($task.Actions.Count -gt 0) {
    $task.Actions[0].WorkingDirectory = $ProjectDir
    Set-ScheduledTask -InputObject $task | Out-Null
}

# ---------------------------------------------------------------------------
# Validar que tiene NextRunTime
# ---------------------------------------------------------------------------
$taskInfo = Get-ScheduledTaskInfo -TaskName $TaskName
if (-not $taskInfo.NextRunTime) {
    throw "La tarea se creo pero no tiene NextRunTime valido."
}

Write-Host "Tarea '$TaskName' registrada correctamente."
Write-Host "Proxima ejecucion programada: $($taskInfo.NextRunTime)"

# ---------------------------------------------------------------------------
# Iniciar inmediatamente para comprobar que funciona
# ---------------------------------------------------------------------------
Start-ScheduledTask -TaskName $TaskName
Write-Host "Tarea iniciada manualmente para validacion."
