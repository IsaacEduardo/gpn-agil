Write-Host "Iniciando preparação para deploy..."

$root = Get-Location
$tempDir = "$root\temp_deploy"
$projectDest = "$tempDir\gpn_project"
$publicDest = "$tempDir\public_html"

# Limpeza inicial
if (Test-Path $tempDir) { Remove-Item $tempDir -Recurse -Force }
if (Test-Path "$root\gpn_project.zip") { Remove-Item "$root\gpn_project.zip" -Force }
if (Test-Path "$root\public_html.zip") { Remove-Item "$root\public_html.zip" -Force }

New-Item -ItemType Directory -Path $projectDest | Out-Null
New-Item -ItemType Directory -Path $publicDest | Out-Null

# 1. Preparar gpn_project (Backend)
Write-Host "Copiando arquivos do sistema (isso pode demorar alguns segundos)..."
$itemsToCopy = Get-ChildItem -Path $root | Where-Object { 
    $_.Name -ne "public" -and 
    $_.Name -ne ".git" -and 
    $_.Name -ne "node_modules" -and 
    $_.Name -ne "temp_deploy" -and 
    $_.Name -ne ".trae" -and
    $_.Name -ne ".vscode" -and
    $_.Name -ne ".env" -and
    $_.Extension -ne ".zip"
}

foreach ($item in $itemsToCopy) {
    Copy-Item -Path $item.FullName -Destination $projectDest -Recurse -Force
}

# Tentar limpar dev dependencies na cópia se composer existir
if (Get-Command composer -ErrorAction SilentlyContinue) {
    Write-Host "Otimizando dependências (removendo pacotes de dev)..."
    Push-Location $projectDest
    try {
        composer install --no-dev --optimize-autoloader --ignore-platform-reqs
    } catch {
        Write-Host "Aviso: Não foi possível otimizar o composer, seguindo com vendor original."
    }
    Pop-Location
}

# 2. Preparar public_html (Frontend)
Write-Host "Copiando arquivos públicos..."
Copy-Item -Path "$root\public\*" -Destination $publicDest -Recurse -Force

# 3. Zipar
Write-Host "Criando gpn_project.zip..."
Compress-Archive -Path $projectDest -DestinationPath "$root\gpn_project.zip"

Write-Host "Criando public_html.zip..."
Compress-Archive -Path "$publicDest\*" -DestinationPath "$root\public_html.zip"

# Limpeza final
Remove-Item $tempDir -Recurse -Force

Write-Host "SUCESSO! Arquivos prontos na raiz do projeto:"
Write-Host "1. gpn_project.zip (Backend - Envie para a raiz /mnt/home7/infinit5/)"
Write-Host "2. public_html.zip (Frontend - Envie para dentro de public_html)"
