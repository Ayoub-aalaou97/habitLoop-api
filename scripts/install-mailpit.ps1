# Download Mailpit (local SMTP catcher) into tools/mailpit/
$ErrorActionPreference = "Stop"
$dest = Join-Path $PSScriptRoot "..\tools\mailpit"
New-Item -ItemType Directory -Force -Path $dest | Out-Null
$exe = Join-Path $dest "mailpit.exe"
if (Test-Path $exe) {
  Write-Host "Mailpit already installed: $exe"
  & $exe version
  exit 0
}

$api = Invoke-RestMethod -Uri "https://api.github.com/repos/axllent/mailpit/releases/latest" -Headers @{ "User-Agent" = "habitloop" }
$asset = $api.assets | Where-Object { $_.name -eq "mailpit-windows-amd64.zip" } | Select-Object -First 1
if (-not $asset) { throw "Could not find mailpit-windows-amd64.zip release asset." }

$zip = Join-Path $dest "mailpit.zip"
Write-Host "Downloading $($asset.browser_download_url)"
Invoke-WebRequest -Uri $asset.browser_download_url -OutFile $zip -UseBasicParsing
Expand-Archive -Path $zip -DestinationPath $dest -Force
Remove-Item $zip -Force
Write-Host "Installed Mailpit:"
& $exe version
