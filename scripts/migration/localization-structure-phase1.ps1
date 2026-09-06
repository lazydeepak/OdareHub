# Localization File Structure Phase 1 Migration
# Migrates locale files from legacy lang/ to canonical Resources/lang/
# Preserves legacy lang/ files for backward compatibility

$ProjectRoot = "C:\Projects\Susankhya"
$Locales = @("en", "ja", "ne")

# Owners with their locale file status
$Owners = @(
    # 3-locale owners (en, ja, ne all present)
    @{Path="apps\Manufacturing\modules\AssemblyEntries"; HasEn=$true; HasJa=$true; HasNe=$true},
    @{Path="apps\Manufacturing\modules\AssemblyPlans"; HasEn=$true; HasJa=$true; HasNe=$true},
    @{Path="apps\Manufacturing\modules\Coverage"; HasEn=$true; HasJa=$true; HasNe=$true},
    @{Path="apps\Procurement"; HasEn=$true; HasJa=$true; HasNe=$true},
    @{Path="apps\Studio"; HasEn=$true; HasJa=$true; HasNe=$true},
    @{Path="apps\Studio\Tools\CssLiveEditor"; HasEn=$true; HasJa=$true; HasNe=$true},
    @{Path="apps\Studio\Tools\CssTokenEditor"; HasEn=$true; HasJa=$true; HasNe=$true},
    @{Path="apps\Studio\Tools\ThemeTool"; HasEn=$true; HasJa=$true; HasNe=$true},

    # en-only owners (Manufacturing modules)
    @{Path="apps\Manufacturing\modules\DailyOrders"; HasEn=$true; HasJa=$false; HasNe=$false},
    @{Path="apps\Manufacturing\modules\DispatchEntries"; HasEn=$true; HasJa=$false; HasNe=$false},
    @{Path="apps\Manufacturing\modules\Ledger"; HasEn=$true; HasJa=$false; HasNe=$false},
    @{Path="apps\Manufacturing\modules\Machines"; HasEn=$true; HasJa=$false; HasNe=$false},
    @{Path="apps\Manufacturing\modules\MaterialManagement"; HasEn=$true; HasJa=$false; HasNe=$false},
    @{Path="apps\Manufacturing\modules\PartMachineMap"; HasEn=$true; HasJa=$false; HasNe=$false},
    @{Path="apps\Manufacturing\modules\PreOrders"; HasEn=$true; HasJa=$false; HasNe=$false},
    @{Path="apps\Manufacturing\modules\ProductionEntries"; HasEn=$true; HasJa=$false; HasNe=$false},
    @{Path="apps\Manufacturing\modules\ProductionPlans"; HasEn=$true; HasJa=$false; HasNe=$false},
    @{Path="apps\Manufacturing\modules\Products"; HasEn=$true; HasJa=$false; HasNe=$false},
    @{Path="apps\Manufacturing\modules\QCEntries"; HasEn=$true; HasJa=$false; HasNe=$false},
    @{Path="apps\Manufacturing\modules\QCPlans"; HasEn=$true; HasJa=$false; HasNe=$false},

    # en-only owners (Platform modules)
    @{Path="apps\Platform\modules\Organization"; HasEn=$true; HasJa=$false; HasNe=$false},
    @{Path="apps\Platform\modules\QRCode"; HasEn=$true; HasJa=$false; HasNe=$false},

    # en-only owners (SBAIO modules)
    @{Path="apps\SBAIO\modules\Attendance"; HasEn=$true; HasJa=$false; HasNe=$false},
    @{Path="apps\SBAIO\modules\Customers"; HasEn=$true; HasJa=$false; HasNe=$false},
    @{Path="apps\SBAIO\modules\Expenses"; HasEn=$true; HasJa=$false; HasNe=$false},
    @{Path="apps\SBAIO\modules\Leave"; HasEn=$true; HasJa=$false; HasNe=$false},
    @{Path="apps\SBAIO\modules\Notices"; HasEn=$true; HasJa=$false; HasNe=$false},
    @{Path="apps\SBAIO\modules\Payroll"; HasEn=$true; HasJa=$false; HasNe=$false},
    @{Path="apps\SBAIO\modules\Sales"; HasEn=$true; HasJa=$false; HasNe=$false},
    @{Path="apps\SBAIO\modules\Schedules"; HasEn=$true; HasJa=$false; HasNe=$false},
    @{Path="apps\SBAIO\modules\Staff"; HasEn=$true; HasJa=$false; HasNe=$false},
    @{Path="apps\SBAIO\modules\Tasks"; HasEn=$true; HasJa=$false; HasNe=$false},
    @{Path="apps\SBAIO\modules\Timecards"; HasEn=$true; HasJa=$false; HasNe=$false}
)

$TotalCreated = 0
$TotalCopied = 0
$Errors = @()

foreach ($Owner in $Owners) {
    $OwnerRoot = Join-Path $ProjectRoot $Owner.Path
    $LegacyLangPath = Join-Path $OwnerRoot "lang"
    $ResLangPath = Join-Path $OwnerRoot "Resources\lang"

    # Create Resources/lang/ directory
    if (-not (Test-Path $ResLangPath)) {
        New-Item -ItemType Directory -Path $ResLangPath -Force | Out-Null
        Write-Host "  [DIR]  $($Owner.Path)\Resources\lang\"
        $TotalCreated++
    }

    # Copy/migrate locale files
    foreach ($Locale in $Locales) {
        $SourceFile = Join-Path $LegacyLangPath "$Locale.php"
        $TargetFile = Join-Path $ResLangPath "$Locale.php"

        if ($Locale -eq "en" -and $Owner.HasEn) {
            # Copy English file
            Copy-Item -Path $SourceFile -Destination $TargetFile -Force
            Write-Host "  [CP]   $($Owner.Path)\Resources\lang\$Locale.php"
            $TotalCopied++
        }
        elseif ($Locale -eq "ja" -and $Owner.HasJa) {
            Copy-Item -Path $SourceFile -Destination $TargetFile -Force
            Write-Host "  [CP]   $($Owner.Path)\Resources\lang\$Locale.php"
            $TotalCopied++
        }
        elseif ($Locale -eq "ne" -and $Owner.HasNe) {
            Copy-Item -Path $SourceFile -Destination $TargetFile -Force
            Write-Host "  [CP]   $($Owner.Path)\Resources\lang\$Locale.php"
            $TotalCopied++
        }
        elseif (($Locale -eq "ja" -and -not $Owner.HasJa) -or ($Locale -eq "ne" -and -not $Owner.HasNe)) {
            # Create empty locale file
            $SourceEn = Join-Path $LegacyLangPath "en.php"
            if (Test-Path $SourceEn) {
                # Extract only the keys from en.php, set values to empty string
                $EnContent = Get-Content $SourceEn -Raw
                if ($EnContent -match 'return\s*\[(.*?)\];' -or $EnContent -match "return\s*\[(.*?)\];" -or $true) {
                    # Simple approach: read en.php, extract keys, create ja/ne with empty values
                    $Keys = @()
                    foreach ($line in (Get-Content $SourceEn)) {
                        if ($line -match "^\s*'(.*?)'\s*=>") {
                            $Keys += $matches[1]
                        }
                    }

                    $Content = "<?php`n`nreturn [`n"
                    foreach ($Key in $Keys) {
                        $EscapedKey = $Key -replace "'", "\'"
                        $Content += "    '$EscapedKey' => '',`n"
                    }
                    $Content += "];`n"

                    Set-Content -Path $TargetFile -Value $Content -NoNewline
                    Write-Host "  [NEW]  $($Owner.Path)\Resources\lang\$Locale.php ($($Keys.Count) empty keys)"
                    $TotalCreated++
                }
            }
        }
    }
}

Write-Host "`n=== Migration Summary ==="
Write-Host "Owners processed: $($Owners.Count)"
Write-Host "Directories created: $TotalCreated"
Write-Host "Files copied: $TotalCopied"
if ($Errors.Count -gt 0) {
    Write-Host "Errors: $($Errors.Count)" -ForegroundColor Red
    foreach ($Err in $Errors) { Write-Host "  $Err" -ForegroundColor Red }
} else {
    Write-Host "Errors: 0" -ForegroundColor Green
}
