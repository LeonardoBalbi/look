$ErrorActionPreference = 'Stop'

$envFile = Join-Path $PSScriptRoot '.env'
if (-not (Test-Path -LiteralPath $envFile)) {
    throw 'Arquivo n8n-runtime\.env não encontrado. Copie .env.example e preencha os segredos.'
}

Get-Content -LiteralPath $envFile | ForEach-Object {
    $line = $_.Trim()
    if ($line -and -not $line.StartsWith('#')) {
        $parts = $line.Split('=', 2)
        if ($parts.Count -eq 2) {
            [Environment]::SetEnvironmentVariable($parts[0].Trim(), $parts[1].Trim(), 'Process')
        }
    }
}

$env:N8N_USER_FOLDER = Join-Path $PSScriptRoot 'data'

Set-Location $PSScriptRoot
& (Join-Path $PSScriptRoot 'node_modules\.bin\n8n.cmd') start
