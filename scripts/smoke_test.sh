#!/bin/bash
# Smoke test: check public endpoints respond correctly.
# Does NOT require secrets, does NOT call provider/topup APIs.
# Usage: bash scripts/smoke_test.sh [base_url]

BASE="${1:-https://jp-esim.vip}"
PASS=0
FAIL=0

check() {
    local url="$1" expect="$2" label="$3"
    code=$(curl -sk "$url" -o /dev/null -w "%{http_code}" --max-time 10)
    if [ "$code" = "$expect" ]; then
        echo "  OK  $code $label"
        PASS=$((PASS+1))
    else
        echo "  FAIL $code (expected $expect) $label"
        FAIL=$((FAIL+1))
    fi
}

check_no_leak() {
    local url="$1" label="$2"
    body=$(curl -sk "$url" --max-time 10)
    if echo "$body" | grep -qi "qrsim\|simlessly\|rsp-eu\|provider_order_no\|shortUrl"; then
        echo "  LEAK $label"
        FAIL=$((FAIL+1))
    else
        echo "  SAFE $label (no provider leak)"
        PASS=$((PASS+1))
    fi
}

echo "=== JP-eSIM Smoke Test ==="
echo "Base: $BASE"
echo ""
echo "--- HTTP Status ---"
check "$BASE/" "200" "Homepage"
check "$BASE/ctv/login.php" "200" "CTV Login"
check "$BASE/robots.txt" "200" "robots.txt"
check "$BASE/sitemap.xml" "200" "sitemap.xml"
check "$BASE/tra-cuu.php" "200" "Tra cứu (no id)"
check "$BASE/api/plans.php?type=esim" "200" "API plans"
check "$BASE/ctv/dashboard.php" "302" "CTV Dashboard (redirect when not logged in)"
check "$BASE/api/topup.php" "400" "API topup (no params)"

echo ""
echo "--- Provider Leak Scan ---"
check_no_leak "$BASE/" "Homepage HTML"
check_no_leak "$BASE/ctv/login.php" "CTV Login HTML"
check_no_leak "$BASE/api/plans.php?type=esim" "Plans API JSON"

echo ""
echo "=== Results: $PASS passed, $FAIL failed ==="
[ "$FAIL" -eq 0 ] && exit 0 || exit 1
