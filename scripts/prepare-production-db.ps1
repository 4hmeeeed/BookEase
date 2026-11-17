<#
.SYNOPSIS
    Prepares the first production database as an SQL file for phpMyAdmin.

.DESCRIPTION
    InfinityFree has no command line, so the tables are built on this laptop:
      1. creates an empty local database (default: bookease_production) in XAMPP's MariaDB
      2. creates all tables (php artisan migrate): no sample cottages, no dev accounts
      3. asks for the owner's name, email and password (php artisan bookease:make-owner)
      4. exports everything to deploy\bookease-production.sql
    Import that file in InfinityFree's phpMyAdmin (DEPLOY.md, step 4).
    Run it in a normal PowerShell window: step 3 asks questions.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File scripts\prepare-production-db.ps1
#>
param([string]$Database = 'bookease_production')

$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
$out = Join-Path $root 'deploy\bookease-production.sql'

if ($Database -notmatch '^[A-Za-z0-9_]+$') { throw 'Use only letters, numbers and _ in the database name.' }

function Invoke-Checked([string]$What, [scriptblock]$Command) {
    & $Command
    if ($LASTEXITCODE -ne 0) { throw "$What failed (exit code $LASTEXITCODE)." }
}

Push-Location $root
$previous = $env:DB_DATABASE
try {
    Invoke-Checked 'Creating the database' {
        mysql -u root -e "CREATE DATABASE IF NOT EXISTS $Database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    }

    # A real environment variable wins over .env, so artisan uses this database.
    $env:DB_DATABASE = $Database
    Invoke-Checked 'Creating the tables' { php artisan migrate --force }

    Write-Host ''
    Write-Host 'Now the real owner account (use the owner''s own email; the password is never shown):'
    Invoke-Checked 'Creating the owner account' { php artisan bookease:make-owner }

    New-Item -ItemType Directory -Force -Path (Split-Path -Parent $out) | Out-Null
    Invoke-Checked 'Exporting the database' {
        mysqldump -u root --single-transaction --skip-lock-tables --no-tablespaces --default-character-set=utf8mb4 "--result-file=$out" $Database
    }

    Write-Host ''
    Write-Host "Done: $out"
    Write-Host 'Import it in InfinityFree > MySQL Databases > phpMyAdmin > Import (DEPLOY.md, step 4).'
}
finally {
    $env:DB_DATABASE = $previous
    Pop-Location
}
