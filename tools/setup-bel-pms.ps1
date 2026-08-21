<#
.SYNOPSIS
    Sets up BEL PMS's database on a fresh XAMPP install: writes the DB config, creates
    the database, and runs the schema + every migration in order.

.DESCRIPTION
    Run this from inside an already-cloned copy of the repo (it lives at tools\setup-bel-pms.ps1
    and works out the project root from its own location). It assumes XAMPP is already
    installed and MySQL is running (or it will start it for you if -StartXampp is passed).

.PARAMETER XamppPath
    Root of the XAMPP install. Auto-detected from common install locations if omitted.

.PARAMETER DbName
    Database name to create and use. Default: bel_pms

.PARAMETER DbUser
    MySQL username to use (and to write into src/config.php). Default: root

.PARAMETER DbPass
    MySQL password to use (and to write into src/config.php). Default: empty, matching
    a stock XAMPP install.

.PARAMETER SeedDemoData
    Also runs tools/seed_demo_data.php after the migrations, filling the database with
    realistic demo departments/managers/employees/projects/tasks/defects for a walkthrough.

.PARAMETER StartXampp
    Also starts Apache and MySQL via xampp_start.exe before running, and opens the app
    in your default browser once setup finishes.

.EXAMPLE
    .\setup-bel-pms.ps1

.EXAMPLE
    .\setup-bel-pms.ps1 -SeedDemoData -StartXampp
#>

param(
    [string]$XamppPath,
    [string]$DbName = "bel_pms",
    [string]$DbUser = "root",
    [string]$DbPass = "",
    [switch]$SeedDemoData,
    [switch]$StartXampp
)

$ErrorActionPreference = "Stop"

function Write-Step($msg) {
    Write-Host ""
    Write-Host "==> $msg" -ForegroundColor Cyan
}

function Fail($msg) {
    Write-Host "ERROR: $msg" -ForegroundColor Red
    exit 1
}

# This script lives at <project root>\tools\setup-bel-pms.ps1.
$projectDir = Split-Path -Parent $PSScriptRoot

# ---------------------------------------------------------------------------
# 0. Locate XAMPP and check prerequisites
# ---------------------------------------------------------------------------
Write-Step "Checking prerequisites"

if (-not (Test-Path (Join-Path $projectDir "sql\schema.sql"))) {
    Fail "Couldn't find sql\schema.sql relative to this script. Run it from inside the cloned repo (tools\setup-bel-pms.ps1) without moving it elsewhere."
}

if (-not $XamppPath) {
    $candidates = @("C:\xampp", "D:\xampp", "C:\Program Files\xampp", "C:\Program Files (x86)\xampp")
    $XamppPath = $candidates | Where-Object { Test-Path (Join-Path $_ "mysql\bin\mysql.exe") } | Select-Object -First 1
    if (-not $XamppPath) {
        Fail "Couldn't auto-detect an XAMPP install. Pass -XamppPath 'C:\path\to\xampp' explicitly."
    }
    Write-Host "Auto-detected XAMPP at: $XamppPath"
}

if (-not (Test-Path $XamppPath)) {
    Fail "XAMPP not found at '$XamppPath'. Pass -XamppPath if it's installed elsewhere."
}

$mysqlExe    = Join-Path $XamppPath "mysql\bin\mysql.exe"
$phpExe      = Join-Path $XamppPath "php\php.exe"
$xamppStart  = Join-Path $XamppPath "xampp_start.exe"

if (-not (Test-Path $mysqlExe)) {
    Fail "MySQL client not found at '$mysqlExe'. Is this really an XAMPP install?"
}
if (-not (Test-Path $phpExe)) {
    Fail "PHP not found at '$phpExe'. Is this really an XAMPP install?"
}

# Apache only serves files under XAMPP's htdocs - if this repo was cloned somewhere else
# (e.g. C:\project\bel-pms instead of C:\xampp\htdocs\bel-pms), everything below would still
# "succeed" but the site would 404. Catch that now instead of after the database is set up.
$htdocsPath = Join-Path $XamppPath "htdocs"
$projectDirFull = (Resolve-Path $projectDir).Path
$htdocsPathFull = (Resolve-Path $htdocsPath -ErrorAction SilentlyContinue).Path
if (-not $htdocsPathFull -or -not $projectDirFull.StartsWith($htdocsPathFull, [System.StringComparison]::OrdinalIgnoreCase)) {
    Fail "This repo is at '$projectDirFull', which isn't inside XAMPP's htdocs ('$htdocsPath'). Apache can't serve it from here. Move (or re-clone) this folder into $htdocsPath and run the script from there instead."
}

Write-Host "XAMPP path : $XamppPath"
Write-Host "Project dir: $projectDir"
Write-Host "Database   : $DbName"

# ---------------------------------------------------------------------------
# 1. Optionally start XAMPP services
# ---------------------------------------------------------------------------
if ($StartXampp) {
    Write-Step "Starting Apache and MySQL"
    if (Test-Path $xamppStart) {
        Start-Process -FilePath $xamppStart -Wait
        Start-Sleep -Seconds 3
    } else {
        Write-Host "xampp_start.exe not found - start Apache and MySQL from the XAMPP Control Panel yourself." -ForegroundColor Yellow
    }
}

# ---------------------------------------------------------------------------
# 2. Write src/config.php (gitignored on purpose - holds DB credentials)
# ---------------------------------------------------------------------------
Write-Step "Writing src/config.php"

$configDir  = Join-Path $projectDir "src"
$configPath = Join-Path $configDir "config.php"

$configContent = @"
<?php
return [
    'db' => [
        'host' => 'localhost',
        'name' => '$DbName',
        'user' => '$DbUser',
        'pass' => '$DbPass',
        'charset' => 'utf8mb4',
    ],
];
"@

Set-Content -Path $configPath -Value $configContent -Encoding utf8
Write-Host "Wrote $configPath"

# ---------------------------------------------------------------------------
# 3. Create the database
# ---------------------------------------------------------------------------
Write-Step "Creating database '$DbName' (if it doesn't already exist)"

$mysqlArgs = @("-u", $DbUser)
if ($DbPass -ne "") { $mysqlArgs += "-p$DbPass" }

# schema.sql runs "DROP TABLE IF EXISTS" on its way in, so if this database already exists
# with real data in it, running this would destroy that data. Refuse and let the person
# decide, rather than silently wiping something.
$existingTableCheck = & $mysqlExe @mysqlArgs -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$DbName' AND table_name = 'users';" 2>$null
if ($LASTEXITCODE -eq 0 -and $existingTableCheck -and [int]$existingTableCheck -gt 0) {
    $rowCount = & $mysqlExe @mysqlArgs -N -B -e "SELECT COUNT(*) FROM ``$DbName``.users;" 2>$null
    if ($rowCount -and [int]$rowCount -gt 0) {
        Fail "Database '$DbName' already exists here with $rowCount user row(s) in it. schema.sql drops and recreates tables, which would destroy that data. Back it up, drop the database, or pick a different -DbName first."
    }
}

& $mysqlExe @mysqlArgs -e "CREATE DATABASE IF NOT EXISTS $DbName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
if ($LASTEXITCODE -ne 0) { Fail "Could not create database. Check DB credentials / that MySQL is running." }

# ---------------------------------------------------------------------------
# 4. Load schema.sql, then every numbered migration in order
# ---------------------------------------------------------------------------
Write-Step "Loading schema and migrations"

$sqlDir = Join-Path $projectDir "sql"

function Invoke-SqlFile($file) {
    Write-Host "  -> $($file.Name)"
    Get-Content $file.FullName -Raw | & $mysqlExe @mysqlArgs $DbName
    if ($LASTEXITCODE -ne 0) { Fail "Failed running $($file.Name)" }
}

$schemaFile = Get-ChildItem $sqlDir -Filter "schema.sql" -ErrorAction SilentlyContinue
if (-not $schemaFile) { Fail "sql/schema.sql not found." }
Invoke-SqlFile $schemaFile

# Numbered migrations (002_*.sql, 003_*.sql, ...), sorted numerically, seed files excluded.
$migrations = Get-ChildItem $sqlDir -Filter "0*.sql" |
    Where-Object { $_.Name -notlike "seed_*" } |
    Sort-Object { [int]($_.Name -replace '^(\d+)_.*$', '$1') }

foreach ($m in $migrations) { Invoke-SqlFile $m }

# ---------------------------------------------------------------------------
# 5. Optionally seed realistic demo data
# ---------------------------------------------------------------------------
if ($SeedDemoData) {
    Write-Step "Seeding demo data"
    $seedScript = Join-Path $projectDir "tools\seed_demo_data.php"
    if (-not (Test-Path $seedScript)) {
        Write-Host "tools/seed_demo_data.php not found in this repo - skipping." -ForegroundColor Yellow
    } else {
        Push-Location $projectDir
        & $phpExe $seedScript
        if ($LASTEXITCODE -ne 0) { Fail "Demo data seeding failed - see the error above." }
        Pop-Location
    }
} else {
    Write-Host ""
    Write-Host "Skipped demo data. Re-run with -SeedDemoData to populate the app with realistic" -ForegroundColor Yellow
    Write-Host "departments/managers/employees/projects/tasks/defects for a walkthrough." -ForegroundColor Yellow
}

# ---------------------------------------------------------------------------
# 6. Done
# ---------------------------------------------------------------------------
Write-Step "Setup complete"

$folderName = Split-Path -Leaf $projectDir
$url = "http://localhost/$folderName/login.php"
Write-Host "App URL : $url"
Write-Host "Admin   : admin@bel.co.in / admin123 (change this after first login)"
if ($SeedDemoData) {
    Write-Host "Demo users all use password: Test1234!"
}
Write-Host ""
Write-Host "Uploads (photos/documents/attachments) will be created automatically at:"
Write-Host "  $XamppPath\bel-pms-uploads\"

if ($StartXampp) {
    Start-Process $url
}
