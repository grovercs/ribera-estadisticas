$ErrorActionPreference = 'Stop'
try {
    $configPath = Join-Path $env:ProgramData 'VielhaComputer\Ribera\heartbeat.json'
    $config = Get-Content -LiteralPath $configPath -Raw | ConvertFrom-Json
    if (-not $config.url -or -not $config.token) { throw 'Configuracion incompleta' }
    Invoke-WebRequest -UseBasicParsing -Method Post -Uri $config.url `
        -Headers @{ Authorization = 'Bearer ' + $config.token } `
        -TimeoutSec 8 | Out-Null
    exit 0
} catch {
    # Do not print the request, URL, token or exception details containing them.
    Write-Output ((Get-Date -Format s) + ' No se pudo enviar la senal a Netlify.')
    exit 1
}
