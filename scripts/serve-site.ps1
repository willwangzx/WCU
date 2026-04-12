param(
    [int]$Port = 8000
)

$ErrorActionPreference = "Stop"

$repoRoot = Split-Path -Parent $PSScriptRoot
Set-Location $repoRoot

Write-Host "Serving WCU at http://127.0.0.1:$Port/"
python -m http.server $Port --bind 127.0.0.1
