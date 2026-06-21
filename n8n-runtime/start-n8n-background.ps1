$ErrorActionPreference = 'Stop'

$listening = Get-NetTCPConnection -LocalPort 5678 -State Listen -ErrorAction SilentlyContinue
if ($listening) {
    Write-Output "n8n já está ativo no processo $($listening.OwningProcess)."
    exit 0
}

$stdout = Join-Path $PSScriptRoot 'n8n.stdout.log'
$stderr = Join-Path $PSScriptRoot 'n8n.stderr.log'
$startScript = Join-Path $PSScriptRoot 'start-n8n.ps1'

$process = Start-Process -FilePath 'powershell.exe' `
    -ArgumentList '-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', "`"$startScript`"" `
    -WindowStyle Hidden `
    -RedirectStandardOutput $stdout `
    -RedirectStandardError $stderr `
    -PassThru

Write-Output "n8n iniciado em segundo plano no processo $($process.Id)."
