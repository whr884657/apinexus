# ApiNexus release ZIP packer (tracked). Prefer this over hand-written robocopy.
# Usage: .\pack-release.ps1 [-Version 5.2.0]
# Excludes: .gitignore directory rules + any root dir whose name contains U+53C2 U+8003 (can-kao).

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

# Parse directory rules from .gitignore (UTF-8)
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

# Any root directory whose name contains "参考" (can-kao)
$cankao = [string]::Concat([char]0x53C2, [char]0x8003)
Get-ChildItem -LiteralPath $src -Directory -Force | ForEach-Object {
    if ($_.Name.Contains($cankao) -and -not $xd.Contains($_.Name)) {
        [void]$xd.Add($_.Name)
    }
}

$tmp = Join-Path $env:TEMP ("apinexus{0}_build" -f $Version)
$zipDir = Join-Path $src 'release'
$zip = Join-Path $zipDir ("apinexus{0}.zip" -f $Version)

if (-not (Test-Path -LiteralPath $zipDir)) {
    New-Item -ItemType Directory -Path $zipDir -Force | Out-Null
}
if (Test-Path -LiteralPath $tmp) {
    Remove-Item -LiteralPath $tmp -Recurse -Force
}
New-Item -ItemType Directory -Path $tmp -Force | Out-Null

$robocopyArgs = @($src, $tmp, '/E', '/XD') + $xd.ToArray() + @('/XF', '*.zip', '/NFL', '/NDL', '/NJH', '/NJS', '/nc', '/ns', '/np')
& robocopy @robocopyArgs | Out-Null
if ($LASTEXITCODE -ge 8) {
    throw "robocopy failed with code $LASTEXITCODE"
}

if (Test-Path -LiteralPath $zip) {
    Remove-Item -LiteralPath $zip -Force
}
Compress-Archive -Path (Join-Path $tmp '*') -DestinationPath $zip -Force
Remove-Item -LiteralPath $tmp -Recurse -Force

Add-Type -AssemblyName System.IO.Compression.FileSystem
$za = [System.IO.Compression.ZipFile]::OpenRead($zip)
try {
    $bad = New-Object 'System.Collections.Generic.List[string]'
    foreach ($e in $za.Entries) {
        $n = $e.FullName
        if ($n.Contains($cankao)) { [void]$bad.Add($n); continue }
        foreach ($ex in $xd) {
            if ($ex -eq '.git' -or $ex -eq 'release') { continue }
            if ($n.StartsWith($ex + '/') -or $n.StartsWith($ex + '\')) {
                [void]$bad.Add($n)
                break
            }
        }
    }
    if ($bad.Count -gt 0) {
        $preview = ($bad | Select-Object -First 20) -join "`n"
        throw "ZIP contains forbidden paths:`n$preview"
    }
} finally {
    $za.Dispose()
}

$size = (Get-Item -LiteralPath $zip).Length
Write-Host ("OK zip={0} size={1}" -f $zip, $size)
Write-Host ("excluded dirs: {0}" -f ($xd -join ', '))
