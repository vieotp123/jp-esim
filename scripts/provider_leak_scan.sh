#!/bin/bash
# Provider leak scan: grep source files for provider domain/name exposure.
# Checks public-facing PHP/JS/CSS/HTML for unsafe provider references.
# Internal service files (like CtvProviderClient) are expected to have these.
# Usage: bash scripts/provider_leak_scan.sh
#
# Exit code: 0 = clean, 1 = leaks found

SCAN_DIRS=(
    "public_html"
)
EXCLUDE_DIRS="vendor|node_modules|\.bak\.|\.log$"
# Provider domains/names that must never appear in user-facing output.
# esimsetup.apple.com is Apple's official URL (safe for install links).
# provider_order_no in admin-only SQL is internal, not user-facing.
LEAK_TERMS="qrsim\.net|simlessly|rsp-eu\."

# Files that are allowed to reference provider internals
ALLOW_LIST=(
    "public_html/r/qr.php"
    "public_html/r/install.php"
    "public_html/api/ctv/esim_qr.php"
)

FOUND=0

echo "=== Provider Leak Scan ==="
echo ""

for dir in "${SCAN_DIRS[@]}"; do
    if [ ! -d "$dir" ]; then
        echo "SKIP: $dir not found"
        continue
    fi

    while IFS= read -r file; do
        # Skip backup/log/vendor files
        if echo "$file" | grep -qE "$EXCLUDE_DIRS"; then
            continue
        fi

        # Skip allowlisted files
        skip=0
        for allow in "${ALLOW_LIST[@]}"; do
            if [ "$file" = "$allow" ]; then
                skip=1
                break
            fi
        done
        [ "$skip" -eq 1 ] && continue

        matches=$(grep -nEi "$LEAK_TERMS" "$file" 2>/dev/null)
        if [ -n "$matches" ]; then
            # Filter out safe patterns:
            # - PHP comments (lines starting with // or * or #)
            # - HTML comments
            # - Internal log calls (app_log)
            # - Variable assignments in service files
            unsafe=$(echo "$matches" | grep -viE '^\s*(//|#|\*)|app_log|<!--.*-->|\.log\b')
            if [ -n "$unsafe" ]; then
                echo "  LEAK  $file"
                echo "$unsafe" | while IFS= read -r line; do
                    echo "        $line"
                done
                FOUND=$((FOUND+1))
            fi
        fi
    done < <(find "$dir" -type f \( -name '*.php' -o -name '*.js' -o -name '*.css' -o -name '*.html' \) 2>/dev/null)
done

echo ""
# Also scan app services for UI-facing provider leaks (templates, email)
APP_TEMPLATES="/home/foamljf4kvet/app/templates"
if [ -d "$APP_TEMPLATES" ]; then
    echo "--- Email Templates ---"
    tmpl_leaks=$(grep -rlEi "$LEAK_TERMS" "$APP_TEMPLATES" 2>/dev/null)
    if [ -n "$tmpl_leaks" ]; then
        echo "$tmpl_leaks" | while IFS= read -r f; do
            echo "  LEAK  $f"
            grep -nEi "$LEAK_TERMS" "$f" 2>/dev/null | head -5 | while IFS= read -r line; do
                echo "        $line"
            done
            FOUND=$((FOUND+1))
        done
    else
        echo "  SAFE  Email templates clean"
    fi
    echo ""
fi

if [ "$FOUND" -eq 0 ]; then
    echo "=== CLEAN: No provider leaks found ==="
    exit 0
else
    echo "=== WARNING: $FOUND file(s) with potential leaks ==="
    exit 1
fi
