#Requires -RunAsAdministrator

# Ribera Estadisticas — Fase 1: Eliminar tarea programada de sincronizacion sales

$TaskName = "Ribera Sync - Sales Current Month"

$Existing = Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
if ($Existing) {
    Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false
    Write-Host "Tarea programada eliminada: $TaskName" -ForegroundColor Green
} else {
    Write-Host "No existe la tarea: $TaskName" -ForegroundColor Yellow
}
