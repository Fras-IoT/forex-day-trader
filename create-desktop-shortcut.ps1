# Skrip untuk membuat shortcut desktop Forex Day Trader AI
$WshShell = New-Object -ComObject WScript.Shell
$DesktopPath = [Environment]::GetFolderPath('Desktop')

# 1. Shortcut Start Aplikasi
$ShortcutStart = $WshShell.CreateShortcut("$DesktopPath\Forex Day Trader AI.lnk")
$ShortcutStart.TargetPath = "$PSScriptRoot\start-local.bat"
$ShortcutStart.WorkingDirectory = "$PSScriptRoot"
$ShortcutStart.Description = "Jalankan & Buka Forex Day Trader AI Lokal (http://127.0.0.1:8000)"
$ShortcutStart.IconLocation = "$env:SystemRoot\System32\shell32.dll, 14"
$ShortcutStart.Save()

# 2. Shortcut Stop Aplikasi
$ShortcutStop = $WshShell.CreateShortcut("$DesktopPath\Hentikan Forex AI.lnk")
$ShortcutStop.TargetPath = "$PSScriptRoot\stop-local.bat"
$ShortcutStop.WorkingDirectory = "$PSScriptRoot"
$ShortcutStop.Description = "Hentikan Semua Background Process Forex Day Trader AI"
$ShortcutStop.IconLocation = "$env:SystemRoot\System32\shell32.dll, 27"
$ShortcutStop.Save()

Write-Host "Shortcut desktop berhasil dibuat di: $DesktopPath" -ForegroundColor Green

