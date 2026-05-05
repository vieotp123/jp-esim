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
check "$BASE/ctv/register.php" "200" "CTV Register"
check "$BASE/robots.txt" "200" "robots.txt"
check "$BASE/sitemap.xml" "200" "sitemap.xml"
check "$BASE/tra-cuu.php" "200" "Tra cứu (no id)"
check "$BASE/api/plans.php?type=esim" "200" "API plans (eSIM)"
check "$BASE/api/plans.php?type=topup" "200" "API plans (topup)"
check "$BASE/ctv/dashboard.php" "302" "CTV Dashboard (redirect)"
check "$BASE/ctv/security.php" "302" "CTV Security (redirect)"
check "$BASE/ctv/orders.php" "302" "CTV Orders (redirect)"
check "$BASE/api/topup.php" "400" "API topup (no params)"

echo ""
echo "--- Auth Boundaries ---"
check "$BASE/ctv/create-esim.php" "302" "CTV Create eSIM (redirect)"
check "$BASE/ctv/esims.php" "302" "CTV eSIMs (redirect)"
check "$BASE/ctv/api-keys.php" "302" "CTV API Keys (redirect)"
check "$BASE/ctv/topup-request.php" "302" "CTV Topup Request (redirect)"
check "$BASE/admin/ctv/dashboard-admin.php" "401" "Admin Dashboard (auth)"
check "$BASE/admin/ctv/order-view.php" "401" "Admin Order View (auth)"
check "$BASE/admin/ctv/topup-requests.php" "401" "Admin Topup Requests (auth)"
check "$BASE/admin/ctv/topup-orders.php" "401" "Admin Topup Orders (auth)"
check "$BASE/webhook/bank.php" "403" "Webhook (token required)"
check "$BASE/api/ctv/products.php" "401" "CTV API (key required)"

echo ""
echo "--- SEO/Meta ---"
check "$BASE/.env" "403" ".env blocked"

echo ""
echo "--- Provider Leak Scan ---"
check_no_leak "$BASE/" "Homepage HTML"
check_no_leak "$BASE/ctv/login.php" "CTV Login HTML"
check_no_leak "$BASE/ctv/register.php" "CTV Register HTML"
check_no_leak "$BASE/api/plans.php?type=esim" "Plans API JSON"
check_no_leak "$BASE/tra-cuu.php" "Tra cứu HTML"

echo ""
echo "=== Results: $PASS passed, $FAIL failed ==="
[ "$FAIL" -eq 0 ] && exit 0 || exit 1
