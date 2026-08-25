# ADC PMS - Post-deploy optimize (run after every release)
# Usage: .\scripts\optimize.ps1
$ErrorActionPreference = "Stop"
Set-Location (Split-Path -Parent $PSScriptRoot)

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache 2>$null
php artisan icons:cache 2>$null

Write-Host "Optimization complete. If using database/redis queues, ensure 'php artisan queue:work' is running (supervisor)."
