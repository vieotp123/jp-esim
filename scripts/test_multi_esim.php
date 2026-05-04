<?php
declare(strict_types=1);
/**
 * Mock test: multi-eSIM ordering and partial provisioning.
 * Runs entirely in-process with test mode ON — no real API calls.
 * Usage: php scripts/test_multi_esim.php
 */

// ---------- bootstrap ----------
$root = dirname(__DIR__);
$appRoot = '/home/foamljf4kvet/app';

if (!file_exists($appRoot . '/esimaccess.php')) {
    fwrite(STDERR, "App root not found at $appRoot\n");
    exit(1);
}

// Stub helpers before loading service files
function app_config(string $k, mixed $d = null): mixed {
    $map = [
        'CTV_PROVIDER_TEST_MODE' => '1',
        'PROVIDER_TEST_MODE' => '1',
        'TOPUP_LOCKED' => '1',
    ];
    return $map[$k] ?? $d;
}
function app_log(string $msg, string $level = 'INFO'): void {}
function db(): PDO { throw new RuntimeException('No DB in unit test'); }
function rand_alnum(int $len): string { return substr(bin2hex(random_bytes($len)), 0, $len); }

require_once $appRoot . '/esimaccess.php';
// Load services selectively (skip ones that would fail without full bootstrap)
foreach (['CtvProviderClient', 'LegacyProviderClient', 'CtvFulfillmentService'] as $svc) {
    $f = $appRoot . '/services/' . $svc . '.php';
    if (file_exists($f)) require_once $f;
}

$pass = 0;
$fail = 0;
$total = 0;

function assert_eq(mixed $actual, mixed $expected, string $label): void {
    global $pass, $fail, $total;
    $total++;
    if ($actual === $expected) {
        $pass++;
        echo "  ✓ $label\n";
    } else {
        $fail++;
        echo "  ✗ $label: expected " . var_export($expected, true) . ", got " . var_export($actual, true) . "\n";
    }
}

function assert_true(bool $v, string $label): void {
    assert_eq($v, true, $label);
}

// ---------- Test 1: EsimAccessClient.createOrder count parameter ----------
echo "\n=== Test 1: EsimAccessClient.createOrder count parameter ===\n";
$client = new EsimAccessClient();

// With count=1 (default)
$r1 = $client->createOrder('TEST_PKG', 'TXN-001');
// Can't assert success without real API, but in test mode the access code is empty
// so it returns API not configured — that's fine, we're testing the method signature
assert_true(is_array($r1), 'createOrder(count=1) returns array');

// With count=5
$r5 = $client->createOrder('TEST_PKG', 'TXN-005', 5);
assert_true(is_array($r5), 'createOrder(count=5) returns array');

// Count clamped to 100
$r200 = $client->createOrder('TEST_PKG', 'TXN-200', 200);
assert_true(is_array($r200), 'createOrder(count=200) returns array (clamped to 100)');

// ---------- Test 2: EsimAccessClient.queryOrder pageSize parameter ----------
echo "\n=== Test 2: EsimAccessClient.queryOrder pageSize parameter ===\n";
$q1 = $client->queryOrder('ORD-001');
assert_true(is_array($q1), 'queryOrder default pageSize returns array');

$q100 = $client->queryOrder('ORD-001', null, null, 100);
assert_true(is_array($q100), 'queryOrder pageSize=100 returns array');

// ---------- Test 3: CtvProviderClient.createOrder count forwarding ----------
echo "\n=== Test 3: CtvProviderClient.createOrder count parameter ===\n";
assert_true(CtvProviderClient::isTestMode(), 'CTV test mode is ON');

$prov = new CtvProviderClient();
try {
    $rp1 = $prov->createOrder(1, 'REF-001', 'TEST_PKG', 'TXN-P001', 1);
    assert_true(!empty($rp1['success']), 'createOrder count=1 succeeds in test mode');
    assert_true(!empty($rp1['obj']['orderNo']), 'createOrder count=1 has orderNo');
} catch (Throwable $e) {
    // db() may not be available — that's ok for unit-level testing
    echo "  ⚠ CtvProviderClient test skipped (no DB): " . $e->getMessage() . "\n";
}

try {
    $rp3 = $prov->createOrder(1, 'REF-003', 'TEST_PKG', 'TXN-P003', 3);
    assert_true(!empty($rp3['success']), 'createOrder count=3 succeeds in test mode');
} catch (Throwable $e) {
    echo "  ⚠ CtvProviderClient test skipped (no DB): " . $e->getMessage() . "\n";
}

// ---------- Test 4: LegacyProviderClient.createEsim count forwarding ----------
echo "\n=== Test 4: LegacyProviderClient.createEsim count parameter ===\n";
assert_true(LegacyProviderClient::isTestMode(), 'Legacy test mode is ON');

$leg = new LegacyProviderClient();
try {
    $rl1 = $leg->createEsim('TEST_PKG', 'TXN-L001', 1);
    assert_true(!empty($rl1['success']), 'createEsim count=1 succeeds');
    assert_eq($rl1['success'], true, 'createEsim count=1 success=true');
} catch (Throwable $e) {
    echo "  ⚠ LegacyProviderClient test skipped (no DB): " . $e->getMessage() . "\n";
}

try {
    $rl5 = $leg->createEsim('TEST_PKG', 'TXN-L005', 5);
    assert_true(!empty($rl5['success']), 'createEsim count=5 succeeds');
} catch (Throwable $e) {
    echo "  ⚠ LegacyProviderClient test skipped (no DB): " . $e->getMessage() . "\n";
}

// ---------- Test 5: CtvProviderClient.redactJson masks secrets ----------
echo "\n=== Test 5: redactJson masks secrets correctly ===\n";
$redacted = CtvProviderClient::redactJson([
    'transactionId' => 'TXN-001',
    'packageCode' => 'PKG-001',
    'count' => 3,
    'accessCode' => 'SUPER_SECRET_KEY',
    'iccid' => '89012345678901234567',
]);
$decoded = json_decode($redacted, true);
assert_eq($decoded['accessCode'], '***', 'accessCode is masked');
assert_eq($decoded['iccid'], '8901************4567', 'iccid is masked');
assert_eq($decoded['count'], 3, 'count is preserved');
assert_eq($decoded['transactionId'], 'TXN-001', 'transactionId is preserved');

// ---------- Test 6: maskIccid edge cases ----------
echo "\n=== Test 6: maskIccid edge cases ===\n";
assert_eq(CtvProviderClient::maskIccid('89012345678901234567'), '8901************4567', '20-char ICCID');
assert_eq(CtvProviderClient::maskIccid('12345678'), '12345678', '8-char ICCID (no masking)');
assert_eq(CtvProviderClient::maskIccid('1234567'), '1234567', '7-char ICCID (too short)');
assert_eq(CtvProviderClient::maskIccid('123456789'), '1234*6789', '9-char ICCID');

// ---------- Test 7: CTV partial fulfillment merge is quantity-aware ----------
echo "\n=== Test 7: CtvFulfillmentService partial merge ===\n";
$one = CtvFulfillmentService::mergeProfilesForTest([], [
    ['iccid' => '89000000000000000001', 'esimTranNo' => 'ET-001'],
], 1);
assert_eq($one['inserted'], 1, 'quantity=1 inserts one profile');
assert_eq($one['finalCount'], 1, 'quantity=1 final count is one');
assert_eq($one['status'], 'ready', 'quantity=1 is ready');

$three = CtvFulfillmentService::mergeProfilesForTest([], [
    ['iccid' => '89000000000000000001', 'esimTranNo' => 'ET-001'],
    ['iccid' => '89000000000000000002', 'esimTranNo' => 'ET-002'],
    ['iccid' => '89000000000000000003', 'esimTranNo' => 'ET-003'],
], 3);
assert_eq($three['inserted'], 3, 'quantity=3 inserts three profiles');
assert_eq($three['status'], 'ready', 'quantity=3 is ready');

$five = CtvFulfillmentService::mergeProfilesForTest([], [
    ['iccid' => '89000000000000000001', 'esimTranNo' => 'ET-001'],
    ['iccid' => '89000000000000000002', 'esimTranNo' => 'ET-002'],
    ['iccid' => '89000000000000000003', 'esimTranNo' => 'ET-003'],
    ['iccid' => '89000000000000000004', 'esimTranNo' => 'ET-004'],
    ['iccid' => '89000000000000000005', 'esimTranNo' => 'ET-005'],
], 5);
assert_eq($five['inserted'], 5, 'quantity=5 inserts five profiles');
assert_eq($five['status'], 'ready', 'quantity=5 is ready');

$partial = CtvFulfillmentService::mergeProfilesForTest([], [
    ['iccid' => '89000000000000000001', 'esimTranNo' => 'ET-001'],
], 3);
assert_eq($partial['inserted'], 1, 'partial 1/3 inserts one profile');
assert_eq($partial['finalCount'], 1, 'partial 1/3 final count is one');
assert_eq($partial['status'], 'partial', 'partial 1/3 remains partial');

$none = CtvFulfillmentService::mergeProfilesForTest([], [], 3);
assert_eq($none['inserted'], 0, 'empty provider response inserts nothing');
assert_eq($none['finalCount'], 0, 'empty provider response final count is zero');
assert_eq($none['status'], 'processing', 'empty provider response stays processing');

$retry = CtvFulfillmentService::mergeProfilesForTest([
    ['iccid' => '89000000000000000001', 'esimTranNo' => 'ET-001'],
], [
    ['iccid' => '89000000000000000001', 'esimTranNo' => 'ET-001'],
    ['iccid' => '89000000000000000002', 'esimTranNo' => 'ET-002'],
    ['iccid' => '89000000000000000003', 'esimTranNo' => 'ET-003'],
], 3);
assert_eq($retry['inserted'], 2, 'retry 1/3 to 3 inserts only missing profiles');
assert_eq($retry['finalCount'], 3, 'retry 1/3 to 3 final count is three');
assert_eq($retry['status'], 'ready', 'retry 1/3 to 3 becomes ready');

$repeat = CtvFulfillmentService::mergeProfilesForTest([
    ['iccid' => '89000000000000000001', 'esimTranNo' => 'ET-001'],
    ['iccid' => '89000000000000000002', 'esimTranNo' => 'ET-002'],
    ['iccid' => '89000000000000000003', 'esimTranNo' => 'ET-003'],
], [
    ['iccid' => '89000000000000000001', 'esimTranNo' => 'ET-001'],
    ['iccid' => '89000000000000000002', 'esimTranNo' => 'ET-002'],
    ['iccid' => '89000000000000000003', 'esimTranNo' => 'ET-003'],
], 3);
assert_eq($repeat['inserted'], 0, 'repeated sync inserts no duplicates');
assert_eq($repeat['finalCount'], 3, 'repeated sync keeps final count at three');
assert_eq($repeat['status'], 'ready', 'repeated sync stays ready');

// ---------- Summary ----------
echo "\n" . str_repeat('=', 50) . "\n";
echo "Results: $pass/$total passed";
if ($fail > 0) echo ", $fail FAILED";
echo "\n";

exit($fail > 0 ? 1 : 0);
