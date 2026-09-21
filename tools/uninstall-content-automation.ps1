[CmdletBinding()]
param()

$taskName = 'HTVietnam-AIO-Content-Automation'
& schtasks.exe /Delete /TN $taskName /F
if ($LASTEXITCODE -ne 0) {
    throw 'Could not remove the scheduled task.'
}

Write-Output ("Removed scheduled task: {0}" -f $taskName)
