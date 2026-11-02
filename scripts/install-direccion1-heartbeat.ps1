#Requires -RunAsAdministrator
[CmdletBinding()]
param([Parameter(Mandatory=$true)][uri]$HeartbeatUrl)

$ErrorActionPreference = 'Stop'
if ($env:COMPUTERNAME -ne 'Direccion1') { throw 'Ejecutar solo en Direccion1.' }
if ($HeartbeatUrl.Scheme -ne 'https' -or $HeartbeatUrl.UserInfo -or $HeartbeatUrl.Query -or
    $HeartbeatUrl.Fragment -or $HeartbeatUrl.AbsolutePath -ne '/.netlify/functions/direccion1-heartbeat') {
    throw 'Indica la URL HTTPS exacta de la funcion direccion1-heartbeat.'
}
$root = Split-Path $PSScriptRoot -Parent
$sender = Join-Path $PSScriptRoot 'send-direccion1-heartbeat.ps1'
$batch = Join-Path $PSScriptRoot 'sync-manual-worker-direccion1.bat'
$taskName = 'Ribera Sync - Process Manual Requests'
$task = Get-ScheduledTask -TaskName $taskName
if (-not $task.Settings.Enabled) { throw 'La tarea de produccion debe estar habilitada.' }
if (-not (Test-Path $sender) -or -not (Test-Path $batch)) { throw 'Falta el lanzador o el emisor de senal.' }
$content = Get-Content -LiteralPath $batch -Raw
$exitLine = 'exit /b %RIBERA_EXIT%'
if ($content -notmatch '(?im)^exit /b %RIBERA_EXIT%\s*$') { throw 'Lanzador no reconocido. No se modifica.' }
$secureToken = Read-Host 'Clave RIBERA_HEARTBEAT_SECRET de Netlify (no se mostrara)' -AsSecureString
$credential = New-Object System.Net.NetworkCredential('', $secureToken)
$token = $credential.Password
if ($token.Length -lt 32) { throw 'La clave debe tener al menos 32 caracteres aleatorios.' }

# Validate the endpoint before changing the running task or launcher.
Invoke-WebRequest -UseBasicParsing -Method Post -Uri $HeartbeatUrl.AbsoluteUri `
    -Headers @{ Authorization = 'Bearer ' + $token } -TimeoutSec 10 | Out-Null

$directory = Join-Path $env:ProgramData 'VielhaComputer\Ribera'
New-Item -ItemType Directory -Force -Path $directory | Out-Null
$acl = New-Object System.Security.AccessControl.DirectorySecurity
$acl.SetAccessRuleProtection($true, $false)
foreach ($sidText in @('S-1-5-18', 'S-1-5-32-544')) {
    $sid = New-Object System.Security.Principal.SecurityIdentifier($sidText)
    $rule = New-Object System.Security.AccessControl.FileSystemAccessRule(
        $sid, 'FullControl', 'ContainerInherit,ObjectInherit', 'None', 'Allow')
    $acl.AddAccessRule($rule)
}
Set-Acl -LiteralPath $directory -AclObject $acl
$configPath = Join-Path $directory 'heartbeat.json'
@{ url = $HeartbeatUrl.AbsoluteUri; token = $token } | ConvertTo-Json |
    Set-Content -LiteralPath $configPath -Encoding UTF8
$token = $null
$credential = $null

$wasEnabled = $task.Settings.Enabled
try {
    Disable-ScheduledTask -TaskName $taskName | Out-Null
    $scheduler = New-Object -ComObject 'Schedule.Service'
    $scheduler.Connect()
    $running = @($scheduler.GetRunningTasks(0) | Where-Object { $_.Path -eq ('\' + $taskName) })
    if ($running.Count -gt 0) { throw 'Hay una ejecucion en curso; espera a que termine y repite.' }
    if ($content -notmatch 'send-direccion1-heartbeat\.ps1') {
        $backup = Join-Path $directory ('worker-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.bat.backup')
        Copy-Item -LiteralPath $batch -Destination $backup
        $hook = '"%SystemRoot%\System32\WindowsPowerShell\v1.0\powershell.exe" -NoProfile -NonInteractive -ExecutionPolicy Bypass -File "%PROJECT_DIR%\scripts\send-direccion1-heartbeat.ps1" >> "%LOG_DIR%\heartbeat.log" 2>&1'
        $updated = $content.Replace($exitLine, $hook + "`r`n" + $exitLine)
        $updated | Set-Content -LiteralPath $batch -Encoding Ascii
    }
} finally {
    if ($wasEnabled) { Enable-ScheduledTask -TaskName $taskName | Out-Null }
}
Write-Output 'Emisor instalado. La tarea mantiene su codigo de salida original.'
Write-Output 'Verifica en Netlify una senal enviada por SYSTEM antes de activar los avisos.'
