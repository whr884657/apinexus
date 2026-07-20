# ApiNexus release ZIP packer (tracked).
# Usage: .\pack-release.ps1 [-Version 5.3.0]
# Builds ZIP via PHP ZipArchive (tools/build-release-zip.php) for Updater compatibility.

param(
    [Parameter(Mandatory = $false)]
    [string]$Version = ''
)

$ErrorActionPreference = 'Stop'
$src = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location -LiteralPath $src

if ($Version -eq '') {
    $verLine = Select-String -LiteralPath (Join-Path $src 'core\version.php') -Pattern "define\('VS_VERSION',\s*'([^']+)'"
    if (-not $verLine) { throw 'Cannot read VS_VERSION from core/version.php' }
    $Version = $verLine.Matches[0].Groups[1].Value
}

$xd = New-Object 'System.Collections.Generic.List[string]'
[void]$xd.Add('.git')
[void]$xd.Add('release')

$gi = Join-Path $src '.gitignore'
Get-Content -LiteralPath $gi -Encoding UTF8 | ForEach-Object {
    $line = $_.Trim()
    if ($line -eq '' -or $line.StartsWith('#')) { return }
    if ($line.Contains('*') -or $line.Contains('!')) { return }
    if ($line.EndsWith('/')) {
        $name = $line.TrimEnd('/').TrimStart('/')
        if ($name -ne '' -and -not $name.Contains('/') -and -not $xd.Contains($name)) {
            [void]$xd.Add($name)
        }
    }
}

$cankao = [string]::Concat([char]0x53C2, [char]0x8003)
Get-ChildItem -LiteralPath $src -Directory -Force | ForEach-Object {
    if ($_.Name.Contains($cankao) -and -not $xd.Contains($_.Name)) {
        [void]$xd.Add($_.Name)
    }
}

$zipDir = Join-Path $src 'release'
$zip = Join-Path $zipDir ("apinexus{0}.zip" -f $Version)
if (-not (Test-Path -LiteralPath $zipDir)) {
    New-Item -ItemType Directory -Path $zipDir -Force | Out-Null
}
if (Test-Path -LiteralPath $zip) {
    Remove-Item -LiteralPath $zip -Force
}

$excludeCsv = ($xd -join ',')
$builder = Join-Path $src 'tools\build-release-zip.php'
& php $builder $src $zip $excludeCsv
if ($LASTEXITCODE -ne 0) {
    throw "build-release-zip.php failed with code $LASTEXITCODE"
}

$size = (Get-Item -LiteralPath $zip).Length
Write-Host ("OK zip={0} size={1}" -f $zip, $size)
Write-Host ("excluded dirs: {0}" -f ($xd -join ', '))
