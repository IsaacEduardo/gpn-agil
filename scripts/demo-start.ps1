# =============================================================================
#  GPN-AGIL — Arranque do ambiente de DEMONSTRAÇÃO (Windows / PowerShell)
# -----------------------------------------------------------------------------
#  Abre 3 janelas: servidor web, Reverb (tempo real) e fila de notificações.
#  Uso:   pwsh -File scripts\demo-start.ps1     (ou clicar direito > Run with PowerShell)
#  Parar: fechar as 3 janelas, ou Ctrl+C em cada uma.
# =============================================================================

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot   # raiz do projeto (pasta acima de \scripts)
Set-Location $root

Write-Host "== GPN-AGIL :: arranque da demo ==" -ForegroundColor Cyan
Write-Host "Raiz: $root"

# 1) Garantir assets compilados (public/build)
if (-not (Test-Path "$root\public\build\manifest.json")) {
    Write-Host "Assets não encontrados — a compilar (npm run build)..." -ForegroundColor Yellow
    npm run build
} else {
    Write-Host "Assets já compilados (public/build)." -ForegroundColor Green
}

# 2) Limpar caches para garantir config fresca
php artisan config:clear | Out-Null
php artisan view:clear   | Out-Null
Write-Host "Caches limpos." -ForegroundColor Green

# 3) Lançar os 3 serviços em janelas separadas
function Start-Svc($title, $cmd) {
    Start-Process powershell -ArgumentList @(
        '-NoExit','-Command',
        "`$Host.UI.RawUI.WindowTitle='$title'; Set-Location '$root'; $cmd"
    )
    Write-Host "  → $title" -ForegroundColor Green
}

Write-Host "A lançar serviços..." -ForegroundColor Cyan
Start-Svc 'GPN :: WEB (8000)'    'php artisan serve --host=127.0.0.1 --port=8000'
Start-Svc 'GPN :: REVERB (8080)' 'php artisan reverb:start --host=127.0.0.1 --port=8080'
Start-Svc 'GPN :: QUEUE'         'php artisan queue:work --queue=notifications --sleep=1 --tries=3'

Start-Sleep -Seconds 3
Write-Host ""
Write-Host "================================================================" -ForegroundColor Cyan
Write-Host "  Sistema pronto:  http://127.0.0.1:8000/login" -ForegroundColor White
Write-Host "  Password de demo (todas as contas abaixo):  Demo@2026" -ForegroundColor White
Write-Host "  ------------------------------------------------------------" -ForegroundColor DarkGray
Write-Host "  admin@gpnagil.com     -> Administrador (vê tudo)"
Write-Host "  pedro.camati@mail.ao  -> Super Chefe de Gabinete"
Write-Host "  carlos@mail.com       -> Chefe de Gabinete"
Write-Host "  gilberto@mail.com     -> Chefe de Departamento"
Write-Host "  isaac@mail.com        -> Utilizador (Logística)"
Write-Host "================================================================" -ForegroundColor Cyan
Write-Host "Para GRAVAR: prime Win+G (Xbox Game Bar) e segue docs\ROTEIRO-DEMO-CLIENTE.md" -ForegroundColor Yellow
