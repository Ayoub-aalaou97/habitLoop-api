# Start Mailpit (SMTP :1025, UI :8025) + Laravel reminder scheduler.
# Usage (from habitLoop root):  powershell -File scripts/start-mail-stack.ps1
$ErrorActionPreference = "Stop"
$root = Resolve-Path (Join-Path $PSScriptRoot "..")
Set-Location $root

$mailpit = Join-Path $root "tools\mailpit\mailpit.exe"
if (-not (Test-Path $mailpit)) {
  Write-Host "Mailpit missing — installing..."
  & powershell -NoProfile -File (Join-Path $PSScriptRoot "install-mailpit.ps1")
}

function Test-PortOpen([int]$Port) {
  try {
    $c = New-Object System.Net.Sockets.TcpClient
    $c.Connect("127.0.0.1", $Port)
    $c.Close()
    return $true
  } catch {
    return $false
  }
}

if (-not (Test-PortOpen 1025)) {
  Write-Host "Starting Mailpit on SMTP 1025 / UI 8025..."
  Start-Process -FilePath $mailpit -WorkingDirectory (Split-Path $mailpit) -WindowStyle Minimized
  Start-Sleep -Seconds 1
} else {
  Write-Host "Mailpit already listening on :1025"
}

Write-Host "Mail inbox UI: http://127.0.0.1:8025"
Write-Host "Starting reminder scheduler (Ctrl+C to stop)..."
php artisan schedule:work
