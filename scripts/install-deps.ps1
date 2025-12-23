param(
    [string]$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot ".."))
)

$ErrorActionPreference = 'Stop'

Set-Location $ProjectRoot

if(!(Test-Path -LiteralPath "composer.json")){
    throw "composer.json not found in $ProjectRoot"
}

$composerPhar = Join-Path $ProjectRoot "composer.phar"
if(!(Test-Path -LiteralPath $composerPhar)){
    Write-Host "Downloading composer.phar..."
    Invoke-WebRequest -Uri "https://getcomposer.org/download/latest-stable/composer.phar" -OutFile $composerPhar
}

Write-Host "Installing PHP dependencies into vendor/ ..."
php $composerPhar install --no-dev --optimize-autoloader --classmap-authoritative

Write-Host "Done. vendor/ is ready; server runtime no longer needs Composer."