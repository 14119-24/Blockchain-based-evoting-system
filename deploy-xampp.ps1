param(
    [string]$TargetPath = 'C:\xampp\htdocs\voting_system',
    [switch]$SkipDocs,
    [switch]$SkipTests
)

$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$exclude = @('.vscode', 'tmp_admin_cookie.txt')

if ($SkipDocs) {
    $exclude += 'docs'
}

if ($SkipTests) {
    $exclude += 'tests'
}

New-Item -ItemType Directory -Path $TargetPath -Force | Out-Null

Get-ChildItem -LiteralPath $projectRoot -Force | Where-Object {
    $exclude -notcontains $_.Name
} | ForEach-Object {
    $destination = Join-Path $TargetPath $_.Name

    if ($_.PSIsContainer) {
        New-Item -ItemType Directory -Path $destination -Force | Out-Null

        Get-ChildItem -LiteralPath $_.FullName -Force | ForEach-Object {
            Copy-Item -LiteralPath $_.FullName -Destination $destination -Recurse -Force
        }
    } else {
        Copy-Item -LiteralPath $_.FullName -Destination $destination -Force
    }
}

Write-Host "Deployment sync completed."
Write-Host "Open: http://localhost/voting_system/public/"
Write-Host "Run setup: http://localhost/voting_system/public/setup.html"
