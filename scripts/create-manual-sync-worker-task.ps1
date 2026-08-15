# Ribera Estadisticas — Crear/actualizar worker de solicitudes manuales de sync
#
# NOTA: Este script requiere permisos de Administrador para crear tareas programadas.
#       Si no se ejecuta como administrador, mostrara advertencia y terminara.
#
# Crea/actualiza una tarea programada que cada 1 minuto consulta sync_requests
# y ejecuta la sincronizacion de sales cuando hay una solicitud manual pendiente.

param(
    [switch]$Force
)

# Detectar si la sesion esta elevada
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole] "Administrator")

if (-not $isAdmin) {
    Write-Warning "Este script requiere ejecutarse como Administrador."
    Write-Warning "Por favor, abre PowerShell con 'Ejecutar como administrador' y vuelve a ejecutar."
    exit 1
}

$TaskName = "Ribera Sync - Process Manual Requests"
$Description = "Consulta sync_requests cada minuto y ejecuta ribera:process-sync-requests para solicitudes manuales de sales."

$PhpPath = "C:\wamp64\bin\php\php8.4.24\php.exe"
$ProjectDir = "C:\Proyectos\antigravity\ribera-estadisticas"
$Argument = "$ProjectDir\artisan ribera:process-sync-requests"

function Find-RiberaWorkerTask {
    param([string]$ExactName)

    # Busqueda exacta por nombre
    $task = Get-ScheduledTask -TaskName $ExactName -ErrorAction SilentlyContinue
    if ($task) { return $task }

    # Busqueda por accion relacionada con Ribera
    $allTasks = Get-ScheduledTask -ErrorAction SilentlyContinue
    foreach ($t in $allTasks) {
        try {
            foreach ($action in $t.Actions) {
                $actionText = ($action.Execute + ' ' + $action.Arguments).ToLower()
                if ($actionText -match [regex]::Escape($Argument.ToLower()) -or
                    ($actionText -match [regex]::Escape($ProjectDir.ToLower()) -and $actionText -match 'process-sync')) {
                    return $t
                }
            }
        } catch {
            continue
        }
    }
    return $null
}

$Existing = Find-RiberaWorkerTask -ExactName $TaskName

if ($Existing) {
    Write-Host "Tarea existente encontrada: $($Existing.TaskName)" -ForegroundColor Yellow
    if (-not $Force) {
        $confirm = Read-Host "Deseas actualizarla? (S/N)"
        if ($confirm -notin @('S','s','SI','Si','si','YES','Yes','yes')) {
            Write-Host "Omitiendo $TaskName" -ForegroundColor Cyan
            exit 0
        }
    }
    try {
        Unregister-ScheduledTask -TaskName $Existing.TaskName -Confirm:$false -ErrorAction Stop
        Write-Host "  Tarea anterior eliminada." -ForegroundColor Green
    } catch {
        Write-Error "No se pudo eliminar la tarea existente $($Existing.TaskName): $_"
        exit 1
    }
}

$Trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).Date -RepetitionInterval (New-TimeSpan -Minutes 1) -RepetitionDuration (New-TimeSpan -Days 1)
$Action = New-ScheduledTaskAction -Execute $PhpPath -Argument $Argument -WorkingDirectory $ProjectDir
$Principal = New-ScheduledTaskPrincipal -UserId "NT AUTHORITY\SYSTEM" -LogonType ServiceAccount -RunLevel Highest
$Settings = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -StartWhenAvailable `
    -ExecutionTimeLimit (New-TimeSpan -Minutes 65) `
    -MultipleInstances IgnoreNew

Register-ScheduledTask `
    -TaskName $TaskName `
    -Description $Description `
    -Action $Action `
    -Trigger $Trigger `
    -Principal $Principal `
    -Settings $Settings `
    -Force | Out-Null

# Ejecutar inmediatamente para no esperar hasta la medianoche
Start-ScheduledTask -TaskName $TaskName

Write-Host "Tarea programada creada/actualizada: $TaskName" -ForegroundColor Green
Write-Host "  Ejecutable: $PhpPath" -ForegroundColor Cyan
Write-Host "  Argumento: $Argument" -ForegroundColor Cyan
Write-Host "  Frecuencia: cada 1 minuto, 24 horas" -ForegroundColor Cyan
Write-Host "  Primera ejecucion iniciada ahora." -ForegroundColor Green
