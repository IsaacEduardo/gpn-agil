<#
.SYNOPSIS
    Gera o executavel distribuivel do agente WebScan Bridge.

.DESCRIPTION
    Produz dist\webscan-bridge.exe, um ficheiro unico que nao exige Python
    instalado no posto de trabalho. Correr a partir da pasta agent\.

.EXAMPLE
    .\build.ps1
    .\build.ps1 -SkipTests
#>
[CmdletBinding()]
param(
    [switch]$SkipTests
)

$ErrorActionPreference = 'Stop'
Set-Location -Path $PSScriptRoot

Write-Host '==> A instalar dependencias de compilacao' -ForegroundColor Cyan
python -m pip install --quiet --upgrade -r requirements-build.txt

if (-not $SkipTests) {
    Write-Host '==> A correr a suite de testes' -ForegroundColor Cyan
    python -m unittest discover -s tests -t .
    if ($LASTEXITCODE -ne 0) {
        throw 'Os testes falharam. A compilacao foi interrompida.'
    }
}

Write-Host '==> A compilar o executavel' -ForegroundColor Cyan
python -m PyInstaller `
    --onefile `
    --name webscan-bridge `
    --console `
    --clean `
    --noconfirm `
    --hidden-import win32com.client `
    --hidden-import pythoncom `
    --collect-submodules webscan_bridge `
    run_agent.py

$exe = Join-Path $PSScriptRoot 'dist\webscan-bridge.exe'
if (-not (Test-Path $exe)) {
    throw 'A compilacao terminou sem produzir o executavel.'
}

Write-Host ''
Write-Host "Executavel gerado: $exe" -ForegroundColor Green
Write-Host 'Distribua-o com o ficheiro webscan-agent.example.json e o guia do operador.'
