[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$configPath = Join-Path $root 'automation\content-automation.json'
$workerPath = Join-Path $PSScriptRoot 'run-content-automation.ps1'
$config = Get-Content -Raw -LiteralPath $configPath -Encoding UTF8 | ConvertFrom-Json
$taskName = 'HTVietnam-AIO-Content-Automation'
Import-Module ScheduledTasks

$start = [datetime]::ParseExact([string]$config.schedule.start_time, 'HH:mm', $null)
$end = [datetime]::ParseExact([string]$config.schedule.end_time, 'HH:mm', $null)
$interval = [int]$config.schedule.interval_minutes
if ($interval -lt 1 -or $end -lt $start) {
    throw 'Invalid automation schedule.'
}

$triggers = [System.Collections.ArrayList]@()
$cursor = [datetime]::Today.Add($start.TimeOfDay)
$last = [datetime]::Today.Add($end.TimeOfDay)
while ($cursor -le $last) {
    [void]$triggers.Add((New-ScheduledTaskTrigger -Daily -At $cursor))
    $cursor = $cursor.AddMinutes($interval)
}

$actionArguments = '-NoProfile -NonInteractive -WindowStyle Hidden -ExecutionPolicy Bypass -File "' + $workerPath + '"'
$action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument $actionArguments -WorkingDirectory $root
$settings = New-ScheduledTaskSettingsSet `
    -MultipleInstances IgnoreNew `
    -StartWhenAvailable `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -ExecutionTimeLimit (New-TimeSpan -Hours 2)
$principal = New-ScheduledTaskPrincipal `
    -UserId ($env:USERDOMAIN + '\' + $env:USERNAME) `
    -LogonType Interactive `
    -RunLevel Limited

Register-ScheduledTask `
    -TaskName $taskName `
    -Action $action `
    -Trigger @($triggers) `
    -Settings $settings `
    -Principal $principal `
    -Description 'Generate, publish, and verify the configured daily AIO content target.' `
    -Force | Out-Null

Write-Output ("Registered {0}: every {1} minutes from {2} to {3}." -f `
    $taskName,
    $config.schedule.interval_minutes,
    $config.schedule.start_time,
    $config.schedule.end_time)
