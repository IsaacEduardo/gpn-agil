<#
.SYNOPSIS
    Instala o agente WebScan Bridge para arrancar com a sessao do operador.

.DESCRIPTION
    Copia o executavel para %LOCALAPPDATA%\WebScanBridge, escreve a configuracao
    com a origem do EDMS e regista o arranque automatico para o utilizador
    actual. Nao exige privilegios de administrador, porque o agente so serve o
    proprio posto e nunca escuta fora do loopback.

.PARAMETER Origem
    Uma ou mais origens exactas do EDMS. Tem de bater certo com o que o browser
    envia no cabecalho Origin: esquema, host e porta, sem barra final nem caminho.

.EXAMPLE
    .\install-servico.ps1 -Origem http://162.35.116.198

.EXAMPLE
    .\install-servico.ps1 -Origem http://162.35.116.198, http://localhost
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)][string[]]$Origem,
    [string]$Executavel = "$PSScriptRoot\dist\webscan-bridge.exe"
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path $Executavel)) {
    throw "Executavel nao encontrado em $Executavel. Corra primeiro .\build.ps1"
}

$origens = @()
foreach ($item in $Origem) {
    $limpa = $item.Trim().TrimEnd('/')
    if ($limpa -notmatch '^https?://[^/]+$') {
        throw "Origem invalida: '$item'. Use o formato http://162.35.116.198 (sem barra final nem caminho)."
    }
    if ($limpa -like 'http://*' -and $limpa -notlike 'http://localhost*' -and $limpa -notlike 'http://127.0.0.1*') {
        Write-Warning "A origem $limpa usa HTTP simples. O Chrome so autoriza pedidos de uma pagina publica ao loopback a partir de HTTPS (Private Network Access), pelo que a digitalizacao tende a falhar ate o EDMS passar a HTTPS."
    }
    $origens += $limpa
}

$destino = Join-Path $env:LOCALAPPDATA 'WebScanBridge'
New-Item -ItemType Directory -Force -Path $destino | Out-Null
Copy-Item -Path $Executavel -Destination (Join-Path $destino 'webscan-bridge.exe') -Force
Write-Host "Agente instalado em $destino" -ForegroundColor Green

# Configuracao por utilizador; o token e gerado pelo agente no primeiro arranque.
$configDir = Join-Path $env:APPDATA 'WebScanBridge'
New-Item -ItemType Directory -Force -Path $configDir | Out-Null
$configPath = Join-Path $configDir 'webscan-agent.json'

if (Test-Path $configPath) {
    $config = Get-Content $configPath -Raw | ConvertFrom-Json
    $config.allowed_origins = @($origens)
} else {
    $config = [PSCustomObject]@{
        port                 = 18090
        allowed_origins      = @($origens)
        pairing_token        = ''
        driver               = 'wia'
        max_pages            = 100
        max_body_bytes       = 65536
        scan_timeout_seconds = 180
        preview_max_edge     = 320
    }
}
# UTF-8 SEM BOM, escrito pelo .NET: no Windows PowerShell 5.1 o
# `Set-Content -Encoding utf8` poe BOM, e o agente lia o ficheiro em utf-8
# estrito — o instalador escrevia uma configuracao que o agente rejeitava, e o
# posto ficava sem origens autorizadas. O agente ja tolera BOM, mas nao ha
# razao para o continuar a produzir.
$json = $config | ConvertTo-Json -Depth 5
[System.IO.File]::WriteAllText($configPath, $json, (New-Object System.Text.UTF8Encoding $false))
Write-Host "Configuracao gravada em $configPath" -ForegroundColor Green

# Arranque automatico com a sessao do utilizador.
$runKey = 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Run'
Set-ItemProperty -Path $runKey -Name 'WebScanBridge' -Value ('"' + (Join-Path $destino 'webscan-bridge.exe') + '"')
Write-Host 'Arranque automatico registado para este utilizador.' -ForegroundColor Green

Write-Host ''
Write-Host 'A arrancar o agente para gerar o codigo de pareamento...' -ForegroundColor Cyan
$token = & (Join-Path $destino 'webscan-bridge.exe') --print-token
Write-Host ''
Write-Host "CODIGO DE PAREAMENTO: $token" -ForegroundColor Yellow
Write-Host 'Introduza este codigo uma vez no EDMS, na janela "Digitalizar documento".'
