# Ribera Estadisticas — Eliminar tareas programadas de sincronizacion sales
#
# NOTA: Este script requiere permisos de Administrador para eliminar tareas programadas.

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

$PhpPath = "C:\wamp64\bin\php\php8.4.24\php.exe"
$ProjectDir = "C:\Proyectos\antigravity\ribera-estadisticas"
$BatchPath = "$ProjectDir\scripts\sync-sales-auto.bat"
$BatchLegacy = "$ProjectDir\scripts\sync-sales-current-month.bat"
$WorkerArgument = "$ProjectDir\artisan ribera:process-sync-requests"

$ExactNames = @(
    "Ribera Sync - Sales",
    "Ribera Sync - Sales Current Month",
    "Ribera Sync - Sales Auto 13:15",
    "Ribera Sync - Sales Auto 19:15",
    "Ribera Sync - Process Manual Requests"
)

$FoundTasks = @()

# Buscar por nombres exactos
foreach ($TaskName in $ExactNames) {
    $task = Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
    if ($task) { $FoundTasks += $task }
}

# Buscar por acciones relacionadas con Ribera
$allTasks = Get-ScheduledTask -ErrorAction SilentlyContinue
foreach ($t in $allTasks) {
    try {
        foreach ($action in $t.Actions) {
            $actionText = ($action.Execute + ' ' + $action.Arguments).ToLower()
            if ($actionText -match [regex]::Escape($BatchPath.ToLower()) -or
                $actionText -match [regex]::Escape($BatchLegacy.ToLower()) -or
                $actionText -match [regex]::Escape($WorkerArgument.ToLower()) -or
                ($actionText -match [regex]::Escape($ProjectDir.ToLower()) -and $actionText -match 'ribera|sync-sales|process-sync')) {
                $alreadyFound = $FoundTasks | Where-Object { $_.TaskName -eq $t.TaskName }
                if (-not $alreadyFound) { $FoundTasks += $t }
                break
            }
        }
    } catch {
        continue
    }
}

if ($FoundTasks.Count -eq 0) {
    Write-Host "No se encontraron tareas programadas de Ribera Sync." -ForegroundColor Yellow
    exit 0
}

Write-Host "Tareas encontradas:" -ForegroundColor Yellow
foreach ($t in $FoundTasks) {
    Write-Host "  - $($t.TaskName)" -ForegroundColor Cyan
}

if (-not $Force) {
    $confirm = Read-Host "Deseas eliminarlas? (S/N)"
    if ($confirm -notin @('S','s','SI','Si','si','YES','Yes','yes')) {
        Write-Host "Operacion cancelada." -ForegroundColor Cyan
        exit 0
    }
}

foreach ($t in $FoundTasks) {
    try {
        Unregister-ScheduledTask -TaskName $t.TaskName -Confirm:$false -ErrorAction Stop
        Write-Host "Tarea eliminada: $($t.TaskName)" -ForegroundColor Green
    } catch {
        Write-Error "No se pudo eliminar $($t.TaskName): $_"
    }
}
