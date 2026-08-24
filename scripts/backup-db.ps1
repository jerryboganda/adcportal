# ADC PMS - Database Backup Script (PowerShell 5.1 compatible)
# Usage: .\scripts\backup-db.ps1
$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$stamp = Get-Date -Format "yyyyMMdd_HHmmss"
$dir = Join-Path $root "backups\db"
if (-not (Test-Path $dir)) { New-Item -ItemType Directory -Path $dir | Out-Null }

$envFile = Join-Path $root ".env"
$envMap = @{}
Get-Content $envFile | ForEach-Object {
    if ($_ -match "^\s*([A-Z0-9_]+)\s*=\s*`"?([^`"]*)`"?\s*$") { $envMap[$Matches[1]] = $Matches[2] }
}

$driver = $envMap["DB_CONNECTION"]
if ($driver -eq "sqlite") {
    $src = Join-Path $root "database\database.sqlite"
    if ($envMap.ContainsKey("DB_DATABASE") -and $envMap["DB_DATABASE"]) { $src = Join-Path $root $envMap["DB_DATABASE"] }
    $dest = Join-Path $dir "sqlite_$stamp.sqlite"
    Copy-Item $src $dest
    Write-Host "Backed up sqlite -> $dest"
} else {
    $dest = Join-Path $dir "mysql_$stamp.sql"
    $h = "127.0.0.1"; if ($envMap.ContainsKey("DB_HOST")) { $h = $envMap["DB_HOST"] }
    $p = "3306"; if ($envMap.ContainsKey("DB_PORT")) { $p = $envMap["DB_PORT"] }
    $db = $envMap["DB_DATABASE"]; $user = $envMap["DB_USERNAME"]; $pass = $envMap["DB_PASSWORD"]
    & mysqldump -h $h -P $p -u $user ('-p' + $pass) $db | Out-File -FilePath $dest -Encoding utf8
    Write-Host "Backed up mysql -> $dest"
}

Get-ChildItem $dir | Sort-Object LastWriteTime -Descending | Select-Object -Skip 14 | Remove-Item -Force -ErrorAction SilentlyContinue
