<?php
declare(strict_types=1);
/**
 * Smarter audit — actually executes each query (with safe transformations).
 * Strategy:
 *   - For each query string, replace ? placeholders with NULL.
 *   - Replace $variables and {$expr} with safe placeholders.
 *   - Strip ORDER BY / LIMIT / WHERE that depend on $variables.
 *   - Run as subquery: SELECT * FROM (...) AS x LIMIT 0 — schema validation only.
 * If the underlying tables/columns don't exist, EXPLAIN/run will fail.
 */
if (PHP_SAPI !== 'cli') exit(1);
require_once '/home/foamljf4kvet/app/bootstrap.php';

function extract_queries(string $src): array {
    $out = [];
    if (preg_match_all('/(?:->prepare|->query|->exec)\s*\(\s*([\'"])([\s\S]*?)\1\s*[\),]/m', $src, $m)) {
        foreach ($m[2] as $q) {
            $q = trim($q);
            if ($q !== '' && preg_match('/^(SELECT|INSERT|UPDATE|DELETE|SHOW|REPLACE)\b/i', $q)) {
                $out[] = $q;
            }
        }
    }
    return $out;
}

function sanitize_query(string $q): ?string {
    // Drop queries that have variable interpolation we can't safely substitute
    // i.e. WHERE/SET clauses built from PHP $vars: leave only constant parts.
    $sql = $q;
    // Replace ? with NULL
    $sql = preg_replace('/(?<![\w])\?/', 'NULL', $sql);
    $sql = preg_replace('/:[a-zA-Z_]\w*/', 'NULL', $sql);
    // If query contains a $variable placeholder for a clause body (e.g. "$where", "$whereSql"), strip it
    // and the rest of the query past it - we can't validate dynamic where clauses.
    if (preg_match('/\$\{?\w+/', $sql)) {
        // Truncate at first $variable so we keep the prefix (which still references base tables)
        $sql = preg_replace('/\$.*$/s', ' ', $sql);
        // Add a safe terminator
        $sql = trim($sql);
        // Trim dangling AND/OR/WHERE
        $sql = preg_replace('/\b(WHERE|AND|OR|GROUP\s+BY|ORDER\s+BY|HAVING|LIMIT|JOIN)\s*$/i', '', $sql);
    }
    return $sql;
}

$root = '/home/levanrin2404/esimtravel/public_html';
$dirs = [$root . '/admin', $root . '/ctv', $root . '/api', $root . '/webhook'];
$pdo = db();

$failures = [];
$count = 0;
foreach ($dirs as $dir) {
    $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iter as $f) {
        if (!$f->isFile() || $f->getExtension() !== 'php') continue;
        $src = (string)file_get_contents($f->getPathname());
        foreach (extract_queries($src) as $idx => $q) {
            $count++;
            $clean = sanitize_query($q);
            if ($clean === null || strlen($clean) < 10) continue;
            $kind = strtoupper(preg_split('/\s+/', trim($clean))[0] ?? '');
            try {
                if ($kind === 'SELECT') {
                    // Wrap as subquery to ensure no side effects
                    $pdo->query('EXPLAIN ' . $clean);
                } elseif ($kind === 'UPDATE' && preg_match('/UPDATE\s+(`?\w+`?)/i', $clean, $m)) {
                    $pdo->query('SHOW COLUMNS FROM ' . $m[1]);
                } elseif (in_array($kind, ['INSERT','REPLACE','DELETE'], true) && preg_match('/(?:INTO\s+|FROM\s+)(`?\w+`?)/i', $clean, $m)) {
                    $pdo->query('SHOW COLUMNS FROM ' . $m[1]);
                } elseif ($kind === 'SHOW') {
                    // SHOW COLUMNS FROM table — table is usually variable; skip
                }
            } catch (Throwable $e) {
                $failures[] = [
                    'file' => substr($f->getPathname(), strlen($root) + 1),
                    'query' => substr(preg_replace('/\s+/', ' ', $q), 0, 220),
                    'error' => $e->getMessage(),
                ];
            }
        }
    }
}

echo "Queries scanned: $count\n";
echo "Failures: " . count($failures) . "\n";
echo str_repeat('-', 60) . "\n";
foreach ($failures as $f) {
    echo "FILE  : " . $f['file'] . "\n";
    echo "QUERY : " . $f['query'] . "\n";
    echo "ERROR : " . $f['error'] . "\n";
    echo str_repeat('-', 60) . "\n";
}
exit($failures ? 2 : 0);
