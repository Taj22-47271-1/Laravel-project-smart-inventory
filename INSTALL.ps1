param(
    [string]$ProjectPath = (Get-Location).Path
)

$ErrorActionPreference = 'Stop'
$PackPath = Split-Path -Parent $MyInvocation.MyCommand.Path

$folders = @('app', 'database', 'resources', 'tests')

foreach ($folder in $folders) {
    $source = Join-Path $PackPath $folder
    $destination = Join-Path $ProjectPath $folder

    if (Test-Path $source) {
        Copy-Item -Path (Join-Path $source '*') -Destination $destination -Recurse -Force
    }
}

Write-Host ''
Write-Host 'New files copied successfully.' -ForegroundColor Green
Write-Host 'Manual patches are still required:' -ForegroundColor Yellow
Write-Host '  1. patches/model-relationships.md'
Write-Host '  2. patches/routes-web.md'
Write-Host '  3. patches/bootstrap-app-middleware.md'
Write-Host '  4. patches/sidebar-links.blade.php'
Write-Host ''
Write-Host 'Then run:' -ForegroundColor Cyan
Write-Host '  composer dump-autoload'
Write-Host '  php artisan migrate'
Write-Host '  php artisan db:seed --class=RemainingPermissionSeeder'
Write-Host '  php artisan optimize:clear'
Write-Host '  php artisan test'
