$server = Read-Host "Adresse IP du serveur"
$clientName = "client_$env:COMPUTERNAME"
$logFolder = "$env:TEMP\logs"
mkdir $logFolder -Force | Out-Null
$now = Get-Date -Format "yyyy-MM-dd_HH-mm-ss"
$outfile = "$logFolder\system_$now.log"

# Export des logs système
Get-WinEvent -LogName System -MaxEvents 1000 | ForEach-Object {
    "[{0}] {1} {2}" -f $_.TimeCreated.ToString("yyyy-MM-dd HH:mm:ss"), $_.LevelDisplayName, $_.Message
} > $outfile

# Upload vers le serveur
Invoke-WebRequest -Uri "http://$server/receive_logs.php" -Method POST -InFile $outfile -ContentType "multipart/form-data" -Form @{client=$clientName; type='system'; file=Get-Item $outfile}

# Suppression du log temporaire
Remove-Item $outfile
