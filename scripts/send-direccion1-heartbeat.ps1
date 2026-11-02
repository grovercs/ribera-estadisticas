$ErrorActionPreference = 'Stop'
try {
    $configPath = Join-Path $env:ProgramData 'VielhaComputer\Ribera\heartbeat.json'
    $config = Get-Content -LiteralPath $configPath -Raw | ConvertFrom-Json
    if (-not $config.url -or -not $config.token) { throw 'Configuracion incompleta' }
    # Keep the worker minutely; throttle only successful heartbeat sends.
    $lastSentPath = Join-Path $env:ProgramData 'VielhaComputer\Ribera\heartbeat-last-sent.txt'
    if (Test-Path -LiteralPath $lastSentPath) {
        try {
            $lastSent = [DateTimeOffset]::Parse((Get-Content -LiteralPath $lastSentPath -Raw))
            $elapsed = ([DateTimeOffset]::UtcNow - $lastSent).TotalSeconds
            if ($elapsed -ge 0 -and $elapsed -lt 300) { exit 0 }
        } catch {
            # An invalid timestamp must not suppress delivery.
        }
    }
    $heartbeatResponse = Invoke-WebRequest -UseBasicParsing -MaximumRedirection 0 -Method Post -Uri $config.url `
        -Headers @{ Authorization = 'Bearer ' + $config.token } `
        -TimeoutSec 8
    if ($heartbeatResponse.StatusCode -ne 204) { throw 'Respuesta del receptor no valida' }
    # Record completion only after a confirmed HTTP 204. Failed sends retry next minute.
    [DateTimeOffset]::UtcNow.ToString('o') | Set-Content -LiteralPath $lastSentPath -Encoding Ascii
    exit 0
} catch {
    # Do not print the request, URL, token or exception details containing them.
    Write-Output ((Get-Date -Format s) + ' No se pudo enviar la senal a Netlify.')
    exit 1
}
