#!/bin/bash
# Package leak scan: focused checks for CTV/admin/API output that exposes
# provider package codes or provider package names.
# Usage: bash scripts/package_leak_scan.sh

set -u

FOUND=0

check_rg() {
    local label="$1"
    local pattern="$2"
    shift 2
    local out
    out=$(rg -n "$pattern" "$@" 2>/dev/null || true)
    if [ -n "$out" ]; then
        echo "  LEAK  $label"
        echo "$out" | sed 's/^/        /'
        FOUND=$((FOUND + 1))
    else
        echo "  OK    $label"
    fi
}

echo "=== Package Leak Scan ==="
echo ""

check_rg "CTV/admin HTML echoes package fields" "htmlspecialchars\\([^\\n]*(pack_code|package_code|package_name)" public_html/ctv public_html/admin/ctv --glob '*.php'
check_rg "CTV/admin labels package code" "Mã gói" public_html/ctv public_html/admin/ctv --glob '*.php'
check_rg "CSV package/plan headers" "fputcsv\\([^\\n]*('package'|'plan'|\"package\"|\"plan\"|package_code|pack_code|package_name)" public_html/ctv/export.php
check_rg "CTV API package fields" "SELECT [^\\n]*package_name|'packCode'\\s*=>|\"packCode\"\\s*:|'packageName'\\s*=>|\"packageName\"\\s*:|'topupPackCode'\\s*=>" public_html/api/ctv home/foamljf4kvet/app/services/CtvOrderService.php home/foamljf4kvet/app/services/CtvPricingService.php home/foamljf4kvet/app/services/CtvTopupService.php --glob '*.php'

echo ""
if [ "$FOUND" -eq 0 ]; then
    echo "=== CLEAN: No public package leaks found ==="
    exit 0
fi

echo "=== WARNING: $FOUND package leak check(s) failed ==="
exit 1
