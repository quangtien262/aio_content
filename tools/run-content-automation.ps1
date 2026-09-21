[CmdletBinding()]
param(
    [switch]$Force,
    [switch]$OneArticle
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$configPath = Join-Path $root 'automation\content-automation.json'
$promptPath = Join-Path $root 'automation\codex-publish-one.md'
$schemaPath = Join-Path $root 'automation\result-schema.json'
$stateDirectory = Join-Path $root 'automation\state'
$logDirectory = Join-Path $root 'automation\logs'
$temporaryDirectory = Join-Path $root 'automation\tmp'

foreach ($directory in @($stateDirectory, $logDirectory, $temporaryDirectory)) {
    if (-not (Test-Path -LiteralPath $directory)) {
        New-Item -ItemType Directory -Path $directory | Out-Null
    }
}

$lockPath = Join-Path $stateDirectory 'automation.lock'
$lock = $null
try {
    $lock = [System.IO.File]::Open(
        $lockPath,
        [System.IO.FileMode]::OpenOrCreate,
        [System.IO.FileAccess]::ReadWrite,
        [System.IO.FileShare]::None
    )
} catch {
    Write-Output 'Another content automation run is active; exiting.'
    exit 0
}

try {
    $config = Get-Content -Raw -LiteralPath $configPath -Encoding UTF8 | ConvertFrom-Json
    if (-not $config.enabled -and -not $Force) {
        Write-Output 'Content automation is disabled.'
        exit 0
    }

    $today = Get-Date -Format 'yyyy-MM-dd'
    $ledgerPath = Join-Path $stateDirectory ($today + '.json')
    if (Test-Path -LiteralPath $ledgerPath) {
        $ledger = Get-Content -Raw -LiteralPath $ledgerPath -Encoding UTF8 | ConvertFrom-Json
    } else {
        $ledger = [pscustomobject]@{
            date = $today
            published = [System.Collections.ArrayList]@()
        }
    }
    if ($null -eq $ledger.published) {
        $ledger | Add-Member -NotePropertyName published -NotePropertyValue ([System.Collections.ArrayList]@()) -Force
    }

    $completed = @($ledger.published).Count
    $remaining = [int]$config.daily_target - $completed
    if ($remaining -le 0) {
        Write-Output ("Daily target already reached: {0}/{1}." -f $completed, $config.daily_target)
        exit 0
    }

    $batchSize = if ($OneArticle) { 1 } else { [int]$config.normal_batch_size }
    $start = [datetime]::ParseExact($today + ' ' + $config.schedule.start_time, 'yyyy-MM-dd HH:mm', $null)
    $end = [datetime]::ParseExact($today + ' ' + $config.schedule.end_time, 'yyyy-MM-dd HH:mm', $null)
    $now = Get-Date
    if ($now -gt $start -and $end -gt $start) {
        $elapsed = [math]::Min(1, [math]::Max(0, ($now - $start).TotalMinutes / ($end - $start).TotalMinutes))
        $expectedByNow = [math]::Ceiling([int]$config.daily_target * $elapsed)
        $catchUp = [math]::Max(0, $expectedByNow - $completed)
        $batchSize = [math]::Max($batchSize, $catchUp)
    }
    $batchSize = [math]::Min($batchSize, [int]$config.maximum_batch_size)
    $batchSize = [math]::Min($batchSize, $remaining)

    $codexCommand = Get-Command codex -ErrorAction SilentlyContinue
    if ($null -ne $codexCommand) {
        $codexPath = $codexCommand.Source
    } else {
        $extensionRoot = Join-Path $env:USERPROFILE '.vscode\extensions'
        $candidate = Get-ChildItem -Path $extensionRoot -Directory -Filter 'openai.chatgpt-*-win32-x64' -ErrorAction SilentlyContinue |
            Sort-Object LastWriteTime -Descending |
            ForEach-Object { Join-Path $_.FullName 'bin\windows-x86_64\codex.exe' } |
            Where-Object { Test-Path -LiteralPath $_ } |
            Select-Object -First 1
        if (-not $candidate) {
            throw 'Codex CLI was not found. Install/sign in to Codex before running automation.'
        }
        $codexPath = $candidate
    }

    $promptTemplate = Get-Content -Raw -LiteralPath $promptPath -Encoding UTF8
    $logPath = Join-Path $logDirectory ($today + '.log')

    for ($slot = 1; $slot -le $batchSize; $slot++) {
        $success = $false
        for ($attempt = 1; $attempt -le [int]$config.maximum_attempts_per_article; $attempt++) {
            $jobId = '{0}-{1:HHmmss}-{2}-{3}' -f $today, (Get-Date), $slot, $attempt
            $resultPath = Join-Path $temporaryDirectory ($jobId + '-result.json')
            $externalIds = @($ledger.published | ForEach-Object { $_.external_id }) -join ', '
            $prompt = $promptTemplate.Replace('{{DATE}}', $today).
                Replace('{{JOB_ID}}', $jobId).
                Replace('{{ALLOWED_CATEGORIES}}', (@($config.allowed_categories) -join ', ')).
                Replace('{{TODAY_EXTERNAL_IDS}}', $externalIds).
                Replace('{{MIN_VI_BODY}}', [string]$config.minimum_vi_body_characters).
                Replace('{{MIN_EN_BODY}}', [string]$config.minimum_en_body_characters)

            Add-Content -LiteralPath $logPath -Encoding UTF8 -Value ("{0:o} START {1}" -f (Get-Date), $jobId)
            $prompt | & $codexPath exec - `
                --ephemeral `
                --cd $root `
                --sandbox $config.codex.sandbox `
                --approve-for-me `
                --output-schema $schemaPath `
                --output-last-message $resultPath `
                1>$null 2>$null
            $exitCode = $LASTEXITCODE

            if ($exitCode -ne 0 -or -not (Test-Path -LiteralPath $resultPath)) {
                Add-Content -LiteralPath $logPath -Encoding UTF8 -Value ("{0:o} FAIL {1} codex_exit={2}" -f (Get-Date), $jobId, $exitCode)
                continue
            }

            try {
                $result = Get-Content -Raw -LiteralPath $resultPath -Encoding UTF8 | ConvertFrom-Json
            } catch {
                Add-Content -LiteralPath $logPath -Encoding UTF8 -Value ("{0:o} FAIL {1} invalid_result_json" -f (Get-Date), $jobId)
                continue
            }

            if (-not $result.success) {
                $safeError = ([string]$result.error -replace '[\r\n]+', ' ').Trim()
                Add-Content -LiteralPath $logPath -Encoding UTF8 -Value ("{0:o} FAIL {1} agent={2}" -f (Get-Date), $jobId, $safeError)
                continue
            }

            if (@($ledger.published | Where-Object { $_.external_id -eq $result.external_id }).Count -gt 0) {
                Add-Content -LiteralPath $logPath -Encoding UTF8 -Value ("{0:o} FAIL {1} duplicate_external_id" -f (Get-Date), $jobId)
                continue
            }

            $urls = @($result.vi_url, $result.en_url, $result.image_url)
            if (@($urls | Where-Object { -not $_.StartsWith($config.production_base_url + '/') }).Count -gt 0) {
                Add-Content -LiteralPath $logPath -Encoding UTF8 -Value ("{0:o} FAIL {1} invalid_production_url" -f (Get-Date), $jobId)
                continue
            }

            try {
                $viResponse = Invoke-WebRequest -Uri $result.vi_url -Method Get -TimeoutSec 45
                $enResponse = Invoke-WebRequest -Uri $result.en_url -Method Get -TimeoutSec 45
                $imageResponse = Invoke-WebRequest -Uri $result.image_url -Method Get -TimeoutSec 45
                $imageType = [string]$imageResponse.Headers['Content-Type']
                if ($viResponse.StatusCode -ne 200 -or $enResponse.StatusCode -ne 200 -or
                    $imageResponse.StatusCode -ne 200 -or -not $imageType.StartsWith('image/') -or
                    $imageResponse.RawContentLength -ge 1MB) {
                    throw 'Production postcondition failed.'
                }
            } catch {
                Add-Content -LiteralPath $logPath -Encoding UTF8 -Value ("{0:o} FAIL {1} public_verification" -f (Get-Date), $jobId)
                continue
            }

            $entry = [pscustomobject]@{
                completed_at = (Get-Date).ToString('o')
                job_id = $jobId
                external_id = [string]$result.external_id
                post_id = [int]$result.post_id
                category_id = [int]$result.category_id
                vi_title = [string]$result.vi_title
                en_title = [string]$result.en_title
                vi_url = [string]$result.vi_url
                en_url = [string]$result.en_url
                image_url = [string]$result.image_url
            }
            $currentEntries = [System.Collections.ArrayList]@(@($ledger.published))
            [void]$currentEntries.Add($entry)
            $ledger.published = $currentEntries
            $temporaryLedger = $ledgerPath + '.tmp'
            [System.IO.File]::WriteAllText(
                $temporaryLedger,
                ($ledger | ConvertTo-Json -Depth 8),
                [System.Text.UTF8Encoding]::new($false)
            )
            Move-Item -LiteralPath $temporaryLedger -Destination $ledgerPath -Force
            Add-Content -LiteralPath $logPath -Encoding UTF8 -Value ("{0:o} OK {1} post_id={2} external_id={3}" -f (Get-Date), $jobId, $entry.post_id, $entry.external_id)
            $success = $true
            break
        }

        if (-not $success) {
            Add-Content -LiteralPath $logPath -Encoding UTF8 -Value ("{0:o} STOP slot={1} attempts_exhausted" -f (Get-Date), $slot)
            break
        }
    }

    $finalCount = @($ledger.published).Count
    Write-Output ("Automation run completed: {0}/{1} articles recorded today." -f $finalCount, $config.daily_target)
} finally {
    if ($null -ne $lock) {
        $lock.Dispose()
    }
}
