param(
    [switch]$Draft,
    [switch]$DryRun
)

$root = Split-Path -Parent $PSScriptRoot
$publisher = Join-Path $PSScriptRoot 'publish-article.php'
$failed = @()

Get-ChildItem -LiteralPath (Join-Path $root 'articles') -Filter '*.html' | ForEach-Object {
    $arguments = @($publisher, $_.FullName)
    if ($Draft) { $arguments += '--draft' }
    if ($DryRun) { $arguments += '--dry-run' }

    & php @arguments
    if ($LASTEXITCODE -ne 0) {
        $failed += $_.Name
    }
}

if ($failed.Count -gt 0) {
    Write-Error ('Failed to process: ' + ($failed -join ', '))
    exit 1
}
