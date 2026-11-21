<#
.SYNOPSIS
    Uploads BookEase to InfinityFree over FTP and prepares the one-time installer.

.DESCRIPTION
    Run AFTER scripts\make-deploy-bundle.ps1 and scripts\prepare-production-db.ps1.
    It asks for the secrets itself (they are never written into the repo):
      - the hosting account password (FTP and MySQL use the same one)
      - the Brevo API key and the verified sender address
      - the resort's GCash / Maya numbers (optional)
    Then it:
      1. writes the production .env (new APP_KEY; Gemini and Google keys copied from the local .env)
      2. packs deploy\htdocs into zip parts under 8 MB (InfinityFree's per-file limit is 10 MB)
      3. uploads the parts, the .env, the database dump and a one-time installer
      4. prints the installer's address: open it to extract, import and finish

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File scripts\infinityfree-publish.ps1
#>
param(
    [string]$SiteUrl = 'https://labnasan.site.je',
    [string]$FtpHost = 'ftpupload.net',
    [string]$Account = 'if0_43497444',
    [string]$DbHost = 'sql101.infinityfree.com',
    [string]$DbName = 'if0_43497444_bookease'
)

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression, System.IO.Compression.FileSystem

$root = Split-Path -Parent $PSScriptRoot
$deploy = Join-Path $root 'deploy'
$htdocs = Join-Path $deploy 'htdocs'
$dump = Join-Path $deploy 'bookease-production.sql'
$outbox = Join-Path $deploy 'upload'

if (-not (Test-Path (Join-Path $htdocs 'artisan'))) { throw 'Run scripts\make-deploy-bundle.ps1 first (deploy\htdocs is missing).' }
if (-not (Test-Path $dump)) { throw 'Run scripts\prepare-production-db.ps1 first (deploy\bookease-production.sql is missing).' }

function Read-Plain([string]$Prompt, [switch]$Secret, [switch]$Optional) {
    while ($true) {
        if ($Secret) {
            $s = Read-Host -Prompt $Prompt -AsSecureString
            $v = [Runtime.InteropServices.Marshal]::PtrToStringBSTR([Runtime.InteropServices.Marshal]::SecureStringToBSTR($s))
        } else {
            $v = Read-Host -Prompt $Prompt
        }
        $v = "$v".Trim()
        if ($v -ne '' -or $Optional) { return $v }
        Write-Host '  This one is required.' -ForegroundColor Yellow
    }
}

function Get-LocalEnv([string]$Key) {
    $line = Get-Content (Join-Path $root '.env') | Where-Object { $_ -match "^$Key=" } | Select-Object -First 1
    if (-not $line) { return '' }
    return ($line -replace "^$Key=", '').Trim().Trim('"').Trim("'")
}

function Format-EnvValue([string]$Value) {
    if ($Value -notmatch '[\s#"''=$\\]') { return $Value }
    # Single quotes: .env takes the value literally (no ${...} expansion).
    if ($Value -notmatch "'") { return "'" + $Value + "'" }
    return '"' + ($Value -replace '\\', '\\' -replace '"', '\"' -replace '\$', '\$') + '"'
}

Write-Host ''
Write-Host "Publishing BookEase to $SiteUrl" -ForegroundColor Cyan
Write-Host 'Nothing you type here is shown or saved in the project.'
Write-Host ''
$password = Read-Plain 'Hosting account password (InfinityFree > Account > FTP Details > show)' -Secret
$brevoKey = Read-Plain 'Brevo API key (Brevo > SMTP & API > API keys)' -Secret
$sender = Read-Plain 'Sender email verified in Brevo (guests see this as the sender)'
$gcashNumber = Read-Plain 'Resort GCash number (Enter to skip)' -Optional
$gcashName = if ($gcashNumber) { Read-Plain 'Name on that GCash account' } else { '' }
$mayaNumber = Read-Plain 'Resort Maya number (Enter to skip)' -Optional
$mayaName = if ($mayaNumber) { Read-Plain 'Name on that Maya account' } else { '' }
$phone = Read-Plain 'Resort contact phone shown to guests (Enter to skip)' -Optional

# 1. .env ---------------------------------------------------------------------------
# A fresh 32-byte key, made here rather than captured from artisan (its output can carry colour codes).
$keyBytes = New-Object byte[] 32
[Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($keyBytes)
$appKey = 'base64:' + [Convert]::ToBase64String($keyBytes)

$values = [ordered]@{
    APP_KEY = $appKey
    APP_URL = $SiteUrl
    DB_HOST = $DbHost
    DB_DATABASE = $DbName
    DB_USERNAME = $Account
    DB_PASSWORD = $password
    MAIL_MAILER = 'brevo'
    BREVO_API_KEY = $brevoKey
    MAIL_FROM_ADDRESS = $sender
    GCASH_NUMBER = $gcashNumber
    GCASH_ACCOUNT_NAME = $gcashName
    MAYA_NUMBER = $mayaNumber
    MAYA_ACCOUNT_NAME = $mayaName
    RESORT_CONTACT_PHONE = $phone
    RESORT_CONTACT_EMAIL = $sender
    GEMINI_API_KEY = (Get-LocalEnv 'GEMINI_API_KEY')
    GOOGLE_CLIENT_ID = (Get-LocalEnv 'GOOGLE_CLIENT_ID')
    GOOGLE_CLIENT_SECRET = (Get-LocalEnv 'GOOGLE_CLIENT_SECRET')
}

$envLines = Get-Content (Join-Path $root '.env.production.example')
foreach ($key in $values.Keys) {
    $line = "$key=" + (Format-EnvValue ([string]$values[$key]))
    $index = [Array]::FindIndex([string[]]$envLines, [Predicate[string]]{ param($l) $l -match "^$key=" })
    if ($index -ge 0) { $envLines[$index] = $line } else { $envLines += $line }
}

if (Test-Path $outbox) { Remove-Item -Recurse -Force $outbox }
New-Item -ItemType Directory -Path $outbox | Out-Null
$envFile = Join-Path $outbox '.env'
[IO.File]::WriteAllLines($envFile, [string[]]$envLines, (New-Object Text.UTF8Encoding $false))

# 2. Zip parts ----------------------------------------------------------------------
Write-Host 'Packing the site into parts under 8 MB ...'
$limit = 8MB
$files = Get-ChildItem -Path $htdocs -Recurse -File -Force | Where-Object { $_.FullName -ne (Join-Path $htdocs '.htaccess') }
$partNo = 0; $stream = $null; $zip = $null
foreach ($file in $files) {
    if ($null -eq $zip -or $stream.Length -gt $limit) {
        if ($zip) { $zip.Dispose(); $stream.Dispose() }
        $partNo++
        $stream = [IO.File]::Open((Join-Path $outbox ("bookease-part-{0:D2}.zip" -f $partNo)), 'Create')
        $zip = New-Object IO.Compression.ZipArchive($stream, [IO.Compression.ZipArchiveMode]::Create, $true)
    }
    $name = $file.FullName.Substring($htdocs.Length + 1).Replace('\', '/')
    [void][IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $file.FullName, $name, [IO.Compression.CompressionLevel]::Optimal)
    $stream.Flush()
}
if ($zip) { $zip.Dispose(); $stream.Dispose() }
Copy-Item (Join-Path $htdocs '.htaccess') (Join-Path $outbox 'htaccess.pending')
Copy-Item $dump (Join-Path $outbox 'bookease-production.sql')
$token = -join ((48..57) + (97..122) | Get-Random -Count 24 | ForEach-Object { [char]$_ })
$installer = "_install-$token.php"
Copy-Item (Join-Path $PSScriptRoot 'infinityfree-installer.php') (Join-Path $outbox $installer)
$big = Get-ChildItem $outbox -File | Where-Object { $_.Length -gt 10MB }
if ($big) { throw ('Over 10 MB, InfinityFree would refuse: ' + ($big.Name -join ', ')) }
Write-Host "  $partNo parts."

# 3. Upload ------------------------------------------------------------------------
$curlConfig = Join-Path $env:TEMP ("bookease-ftp-" + [guid]::NewGuid() + '.cfg')
try {
    # The password goes in a temporary curl config file, not on the command line.
    [IO.File]::WriteAllText($curlConfig, "user = `"${Account}:$($password -replace '\\','\\' -replace '"','\"')`"`n", (New-Object Text.UTF8Encoding $false))

    $uploads = @(Get-ChildItem $outbox -File | Sort-Object { $_.Name -eq $installer })   # installer last
    $i = 0
    foreach ($file in $uploads) {
        $i++
        Write-Host ("  [{0}/{1}] {2}" -f $i, $uploads.Count, $file.Name)
        $target = "ftp://$FtpHost/htdocs/$($file.Name)"
        for ($try = 1; $try -le 3; $try++) {
            & curl.exe --silent --show-error --fail --ftp-pasv --connect-timeout 30 --retry 2 -K $curlConfig -T $file.FullName $target
            if ($LASTEXITCODE -eq 0) { break }
            if ($try -eq 3) { throw "Upload of $($file.Name) failed (curl exit $LASTEXITCODE). Check the password and try again." }
            Start-Sleep -Seconds 5
        }
    }
}
finally {
    if (Test-Path $curlConfig) { Remove-Item -Force $curlConfig }
    # The .env holds secrets: keep it only on the server.
    if (Test-Path $envFile) { Remove-Item -Force $envFile }
}

Remove-Item -Recurse -Force $outbox
$installUrl = "$SiteUrl/$installer"
Set-Content -Path (Join-Path $deploy 'installer-url.txt') -Value $installUrl -Encoding ascii

Write-Host ''
Write-Host 'Uploaded.' -ForegroundColor Green
Write-Host "Now open: $installUrl"
Write-Host '(also saved in deploy\installer-url.txt). It extracts the parts, imports the database, then deletes itself.'
