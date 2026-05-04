#!/bin/bash
# Static audit for Admin/CTV panel polish after package hiding.
# Checks local panel links/actions resolve to PHP files and scans for public
# package/provider leaks. Does not call provider endpoints or require secrets.
# Usage: bash scripts/ctv_panel_static_audit.sh

set -u

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT" || exit 1

FAIL=0

echo "=== CTV/Admin Panel Static Audit ==="
echo ""

echo "--- Local Panel Routes ---"
routes=$(
  find public_html/admin/ctv public_html/ctv -type f -name '*.php' ! -name '*.bak.*' ! -name '*.disabled' -print0 |
  xargs -0 perl -ne '
    while (m{(?:href|action)=["'\''](/(?:admin/ctv|ctv)/[^"'\''\?#]+\.php)}g) { print "$ARGV\t$1\n"; }
    while (m{fetch\(["'\''](/(?:admin/ctv|ctv)/[^"'\''\?#]+\.php)}g) { print "$ARGV\t$1\n"; }
    while (m{Location:\s*(/(?:admin/ctv|ctv)/[^\s"'\''\?#]+\.php)}g) { print "$ARGV\t$1\n"; }
  ' | sort -t$'\t' -k2,2 -u
)

if [ -z "$routes" ]; then
  echo "  WARN no static local routes found"
else
  while IFS=$'\t' read -r source route; do
    [ -z "$route" ] && continue
    target="public_html${route}"
    if [ -f "$target" ]; then
      echo "  OK   $route"
    else
      echo "  FAIL $route referenced by $source"
      FAIL=$((FAIL + 1))
    fi
  done <<< "$routes"
fi

echo ""
echo "--- Package/Provider Leak Terms ---"
leaks=$(rg -n "htmlspecialchars\\([^\\n]*(pack_code|package_code|package_name)|\\b(Mã gói|Tên gói)\\b|qrsim\\.net|simlessly|rsp-eu\\." public_html/admin/ctv public_html/ctv --glob '*.php' --glob '!*.bak.*' 2>/dev/null || true)
if [ -n "$leaks" ]; then
  echo "  FAIL public panel leak candidates"
  echo "$leaks" | sed 's/^/        /'
  FAIL=$((FAIL + 1))
else
  echo "  OK   no package/provider leak candidates in Admin/CTV PHP output"
fi

echo ""
if [ "$FAIL" -eq 0 ]; then
  echo "=== CLEAN: Panel static audit passed ==="
  exit 0
fi

echo "=== FAILED: $FAIL issue(s) found ==="
exit 1
