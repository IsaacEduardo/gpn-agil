<#
.SYNOPSIS
    Instala o agente WebScan Bridge uma vez por PC, para todas as contas do Windows.

.DESCRIPTION
    Copia o executavel para %ProgramFiles%\WebScanBridge, escreve a configuracao
    da maquina em %ProgramData%\WebScanBridge e regista o arranque automatico
    para todas as contas (HKLM\...\Run). Qualquer pessoa que entre no PC fica com
    o scanner disponivel no EDMS, sem instalar nem introduzir nada.

    Exige PowerShell como Administrador (escreve em Program Files e HKLM).

    Por omissao o agente NAO pede codigo de pareamento: a protecao contra outros
    sites vem da lista de origens autorizadas e do endereco loopback. Use
    -ExigirPareamento so se quiser mesmo o codigo (sera o mesmo para todas as
    contas deste PC).

    Tambem retira instalacoes antigas por utilizador (%LOCALAPPDATA%), que num PC
    partilhado disputavam a porta com o agente da maquina.

.PARAMETER Origem
    Uma ou mais origens exactas do EDMS. Tem de bater certo com o que o browser
    envia no cabecalho Origin: esquema, host e porta, sem barra final nem caminho.
    Por omissao, a producao: https://ondaka-gph.ao.

.PARAMETER ExigirPareamento
    Liga o codigo de pareamento. O instalador gera-o e mostra-o no fim.

.EXAMPLE
    .\install-servico.ps1

.EXAMPLE
    .\install-servico.ps1 -Origem https://ondaka-gph.ao, http://localhost
#>
[CmdletBinding()]
param(
    # Producao em HTTPS desde 2026-09-28 (o acesso pelo IP redirecciona para o dominio).
    [string[]]$Origem = @('https://ondaka-gph.ao'),
    [switch]$ExigirPareamento,
    [string]$Executavel = "$PSScriptRoot\dist\webscan-bridge.exe"
)

$ErrorActionPreference = 'Stop'

$identidade = [Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()
if (-not $identidade.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw 'Abra o PowerShell como Administrador (botao direito > Executar como administrador) e repita.'
}

if (-not (Test-Path $Executavel)) {
    throw "Executavel nao encontrado em $Executavel. Corra primeiro .\build.ps1"
}

$origens = @()
foreach ($item in $Origem) {
    $limpa = $item.Trim().TrimEnd('/')
    if ($limpa -notmatch '^https?://[^/]+$') {
        throw "Origem invalida: '$item'. Use o formato https://ondaka-gph.ao (sem barra final nem caminho)."
    }
    if ($limpa -like 'http://*' -and $limpa -notlike 'http://localhost*' -and $limpa -notlike 'http://127.0.0.1*') {
        Write-Warning "A origem $limpa usa HTTP simples. O Chrome so autoriza pedidos de uma pagina publica ao loopback a partir de HTTPS (Private Network Access), pelo que a digitalizacao tende a falhar ate o EDMS passar a HTTPS."
    }
    $origens += $limpa
}
Write-Host ("Origens autorizadas: " + ($origens -join ', ')) -ForegroundColor Cyan

# Parar todos os agentes em execucao, de qualquer sessao: o executavel em uso
# nao pode ser substituido, e um agente antigo continuaria a ocupar a porta.
Get-Process -Name 'webscan-bridge' -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
Start-Sleep -Seconds 1

# --- Executavel da maquina -----------------------------------------------------
$destino = Join-Path $env:ProgramFiles 'WebScanBridge'
$exe = Join-Path $destino 'webscan-bridge.exe'
New-Item -ItemType Directory -Force -Path $destino | Out-Null
Copy-Item -Path $Executavel -Destination $exe -Force
Write-Host "Agente instalado em $destino" -ForegroundColor Green

# --- Configuracao da maquina ---------------------------------------------------
# Em %ProgramData% as contas normais podem ler mas nao alterar o ficheiro criado
# aqui pelo administrador — que e o que se quer: as origens sao do posto.
$configDir = Join-Path $env:ProgramData 'WebScanBridge'
New-Item -ItemType Directory -Force -Path $configDir | Out-Null
$configPath = Join-Path $configDir 'webscan-agent.json'

$tokenAnterior = ''
if (Test-Path $configPath) {
    $anterior = Get-Content $configPath -Raw | ConvertFrom-Json
    if ($anterior.pairing_token) { $tokenAnterior = [string]$anterior.pairing_token }
}

$token = ''
if ($ExigirPareamento) {
    if ($tokenAnterior) {
        $token = $tokenAnterior
    } else {
        # A conta normal nao pode gravar neste ficheiro, pelo que o codigo e
        # gerado aqui e nao pelo agente no primeiro arranque.
        $bytes = New-Object byte[] 24
        [Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
        $token = [Convert]::ToBase64String($bytes).TrimEnd('=').Replace('+', '-').Replace('/', '_')
    }
}

$config = [PSCustomObject]@{
    port                 = 18090
    allowed_origins      = @($origens)
    require_pairing      = [bool]$ExigirPareamento
    pairing_token        = $token
    driver               = 'wia'
    max_pages            = 100
    max_body_bytes       = 65536
    scan_timeout_seconds = 180
    preview_max_edge     = 320
}
# UTF-8 SEM BOM, escrito pelo .NET: no Windows PowerShell 5.1 o
# `Set-Content -Encoding utf8` poe BOM, e versoes antigas do agente recusavam-no.
$json = $config | ConvertTo-Json -Depth 5
[System.IO.File]::WriteAllText($configPath, $json, (New-Object System.Text.UTF8Encoding $false))
Write-Host "Configuracao gravada em $configPath" -ForegroundColor Green

# --- Arranque automatico para todas as contas -----------------------------------
$runMaquina = 'HKLM:\Software\Microsoft\Windows\CurrentVersion\Run'
Set-ItemProperty -Path $runMaquina -Name 'WebScanBridge' -Value ('"' + $exe + '"')
Write-Host 'Arranque automatico registado para todas as contas deste PC.' -ForegroundColor Green

# --- Instalacoes antigas, por utilizador ---------------------------------------
# Sem o executavel, a entrada HKCU\Run que cada conta ainda tenha fica inerte;
# a do administrador actual e retirada tambem.
$removidos = 0
foreach ($perfil in Get-ChildItem 'C:\Users' -Directory -ErrorAction SilentlyContinue) {
    $antigo = Join-Path $perfil.FullName 'AppData\Local\WebScanBridge\webscan-bridge.exe'
    if (Test-Path $antigo) {
        Remove-Item $antigo -Force -ErrorAction SilentlyContinue
        if (-not (Test-Path $antigo)) { $removidos++ } else { Write-Warning "Nao foi possivel remover $antigo" }
    }
}
Remove-ItemProperty -Path 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Run' -Name 'WebScanBridge' -ErrorAction SilentlyContinue
if ($removidos -gt 0) {
    Write-Host "Removidas $removidos instalacao(oes) antiga(s) por utilizador." -ForegroundColor Yellow
}

# --- Arranque imediato nesta sessao --------------------------------------------
# Via explorer.exe para o agente correr sem privilegios de administrador,
# como correra nas proximas sessoes a partir do Run.
Start-Process -FilePath 'explorer.exe' -ArgumentList ('"' + $exe + '"')
Write-Host ''
Write-Host 'Instalacao concluida. As restantes contas ficam com o scanner ao entrar no Windows.' -ForegroundColor Green

if ($ExigirPareamento) {
    Write-Host ''
    Write-Host "CODIGO DE PAREAMENTO: $token" -ForegroundColor Yellow
    Write-Host 'O mesmo codigo serve todas as contas deste PC. Introduza-o uma vez em cada browser, na janela "Digitalizar documento".'
} else {
    Write-Host 'Sem codigo de pareamento: o scanner liga-se sozinho no EDMS.'
}
