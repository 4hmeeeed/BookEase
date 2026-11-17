<#
.SYNOPSIS
    Builds the InfinityFree upload for BookEase from the last commit.

.DESCRIPTION
    Creates deploy\htdocs\ : everything that goes into InfinityFree's htdocs folder.
      - exports HEAD with `git archive` (uncommitted changes are NOT included)
      - installs PHP packages without dev tools; the autoloader is NOT optimised,
        because InfinityFree silently rejects PHP files larger than 1 MB
      - builds the CSS/JS with Vite (using this repo's node_modules)
      - writes htdocs\.htaccess: requests go into public\, cottage photos are
        served from storage\app\public (no symlinks on InfinityFree), and hidden
        files such as .env are never served
      - drops tests and build-only files, and stops if any PHP file is over 1 MB
    Then upload the CONTENTS of deploy\htdocs into htdocs and create htdocs\.env
    from .env.production.example. See DEPLOY.md.

.PARAMETER Zip
    Also write deploy\bookease-htdocs-<commit>.zip (a backup copy of the upload).

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File scripts\make-deploy-bundle.ps1
#>
param([switch]$Zip)

$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
$deploy = Join-Path $root 'deploy'
$htdocs = Join-Path $deploy 'htdocs'

function Invoke-Checked([string]$What, [scriptblock]$Command) {
    & $Command
    if ($LASTEXITCODE -ne 0) { throw "$What failed (exit code $LASTEXITCODE)." }
}

# 1. Export the last commit --------------------------------------------------
Push-Location $root
try {
    $commit = (git rev-parse --short HEAD).Trim()
    if (git status --porcelain --untracked-files=no) {
        Write-Warning 'There are uncommitted changes. They are NOT in the bundle, which is built from the last commit.'
    }

    Write-Host "Building the InfinityFree bundle from commit $commit ..."
    if (Test-Path $htdocs) { Remove-Item -Recurse -Force $htdocs }
    Get-ChildItem -Path $deploy -Filter 'bookease-htdocs-*.zip' -ErrorAction SilentlyContinue | Remove-Item -Force
    New-Item -ItemType Directory -Force -Path $htdocs | Out-Null

    $sourceZip = Join-Path $deploy 'source.zip'
    Invoke-Checked 'git archive' { git archive --format=zip --output $sourceZip HEAD }
    Expand-Archive -Path $sourceZip -DestinationPath $htdocs
    Remove-Item $sourceZip
}
finally { Pop-Location }

# 2. PHP packages and front-end build -------------------------------------------
Push-Location $htdocs
try {
    Invoke-Checked 'composer config' { composer config optimize-autoloader false }
    Invoke-Checked 'composer install' { composer install --no-dev --no-interaction --prefer-dist --no-progress --no-scripts }
    # deploy\htdocs sits inside the repo, so Node finds the repo's node_modules.
    Invoke-Checked 'vite build' { node (Join-Path $root 'node_modules\vite\bin\vite.js') build }
}
finally { Pop-Location }

# 3. Remove what the server doesn't need -----------------------------------------
$notNeeded = @(
    'tests', '.github', 'scripts', 'docs', 'resources\css', 'resources\js', 'database\seeders\photos',
    'phpunit.xml', 'package.json', 'package-lock.json', 'vite.config.js', 'tailwind.config.js', 'postcss.config.js',
    '.editorconfig', '.gitattributes', '.gitignore', '.env.example', '.env.production.example',
    'README.md', 'CLAUDE.md', 'DESIGN.md', 'PRODUCT.md', 'DEPLOY.md'
)
foreach ($item in $notNeeded) {
    $path = Join-Path $htdocs $item
    if (Test-Path $path) { Remove-Item -Recurse -Force $path }
}

# Folders PHP writes to at runtime.
foreach ($dir in 'storage\app\public', 'storage\framework\cache\data', 'storage\framework\sessions', 'storage\framework\views', 'storage\logs', 'bootstrap\cache') {
    New-Item -ItemType Directory -Force -Path (Join-Path $htdocs $dir) | Out-Null
}

# 4. htdocs\.htaccess --------------------------------------------------------------
$htaccess = @'
# BookEase on InfinityFree: htdocs is the web root; the app's public folder is htdocs/public.
Options -Indexes

<IfModule mod_rewrite.c>
    RewriteEngine On

    # Never serve hidden files such as .env (/.well-known stays open for SSL checks).
    RewriteRule (^|/)\.(?!well-known/) - [F,L]

    # Cottage photos: /storage/x is storage/app/public/x (InfinityFree has no symlinks).
    RewriteRule ^storage/(.*)$ storage/app/public/$1 [END]

    # Everything else is handled inside public/ (Laravel's own .htaccess and index.php).
    RewriteCond %{REQUEST_URI} !^/public/
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
'@
Set-Content -Path (Join-Path $htdocs '.htaccess') -Value $htaccess -Encoding ascii

# 5. Checks ----------------------------------------------------------------------
if (Test-Path (Join-Path $htdocs '.env')) { throw 'A .env file ended up in the bundle. Delete it: secrets must never be uploaded inside the bundle.' }
if (Test-Path (Join-Path $htdocs 'public\hot')) { throw 'public\hot is in the bundle; the site would try to load assets from a dev server.' }
if (-not (Test-Path (Join-Path $htdocs 'public\build\manifest.json'))) { throw 'The Vite build is missing (public\build\manifest.json).' }

$tooBig = Get-ChildItem -Path $htdocs -Recurse -File -Filter '*.php' | Where-Object { $_.Length -gt 1MB }
if ($tooBig) {
    $tooBig | ForEach-Object { Write-Host ('  {0} ({1:N2} MB)' -f $_.FullName, ($_.Length / 1MB)) }
    throw 'InfinityFree silently rejects PHP files over 1 MB (listed above).'
}

$fileCount = (Get-ChildItem -Path $htdocs -Recurse -File -Force).Count
$sizeMb = (Get-ChildItem -Path $htdocs -Recurse -File -Force | Measure-Object -Property Length -Sum).Sum / 1MB

if ($Zip) {
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $zipPath = Join-Path $deploy "bookease-htdocs-$commit.zip"
    [System.IO.Compression.ZipFile]::CreateFromDirectory($htdocs, $zipPath)
    Write-Host "Zip copy: $zipPath"
}

Write-Host ('Bundle ready: {0}  ({1} files, {2:N1} MB, commit {3})' -f $htdocs, $fileCount, $sizeMb, $commit)
Write-Host 'Next: upload the CONTENTS of that folder into htdocs, then add htdocs\.env (see DEPLOY.md).'
