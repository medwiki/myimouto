<?php
/** Non-destructive installer unit tests; no database connection required. */
declare(strict_types=1);
define('MYIMOUTO_INSTALL_LIBRARY_ONLY', true);
require __DIR__ . '/fresh-install.php';

function check(bool $ok, string $name): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $name);
    echo "PASS: {$name}\n";
}

$root = dirname(__DIR__);
$queries = packageSchema($root);
check(count($queries) === 68, '68 schema queries loaded');
check(isPlaceholder($root), 'Fresh Git checkout without private configuration is accepted');
check(validUrl('https://example.com') === null, 'HTTPS base URL accepted');
check(validUrl('http://localhost:8080/') === null, 'host and port accepted');
check(validUrl('https://example.com/post/show/1') !== null, 'URL with path rejected immediately');
check(validUrl('https://example.com?x=1') !== null, 'URL with query rejected immediately');
check(validUrl('https://user:password@example.com') !== null, 'URL credentials rejected');

$site = ['url'=>'https://example.com', 'title'=>'Test', 'contact'=>'admin@example.com',
    'admin'=>'admin', 'salt'=>str_repeat('a',64), 'adminHash'=>str_repeat('b',40), 'group'=>'apache'];
$state = ['installerVersion'=>2, 'schemaHash'=>hash('sha256', serialize($queries)),
    'token'=>bin2hex(random_bytes(24)),
    'database'=>['host'=>'localhost','port'=>'3306','name'=>'test_db','user'=>'test_user'],
    'site'=>$site];
$pair = generatedConfig($state, "pa'ss:#word");
check(str_contains($pair['config/config.php'], "'https://example.com'"), 'Site URL in generated config');
check(str_contains($pair['config/config.php'], $site['salt']), 'Unique password salt in generated config');
check(str_contains($pair['config/database.yml'], "password: 'pa''ss:#word'"), 'DB password YAML escaped');
check(!str_contains(json_encode($state), "pa'ss:#word"), 'DB password not present in resume state');
try {
    token_get_all($pair['config/config.php'], TOKEN_PARSE);
    check(true, 'Generated PHP config parses');
} catch (ParseError $e) {
    check(false, 'Generated PHP config parses');
}

require $root . '/vendor/autoload.php';
$yaml = Symfony\Component\Yaml\Yaml::parse($pair['config/database.yml']);
check($yaml['production']['password'] === "pa'ss:#word", 'YAML password round-trips');
check($yaml['production']['database'] === 'test_db', 'YAML production DB round-trips');

$tmpDir = sys_get_temp_dir() . '/myimouto-test-' . bin2hex(random_bytes(8));
mkdir($tmpDir);
mkdir($tmpDir . '/config');
try {
    writeState($tmpDir, $state);
    check(is_file(stateFile($tmpDir)), 'Resume checkpoint exists');
    check((fileperms(stateFile($tmpDir)) & 077) === 0, 'Resume checkpoint is owner-only');
    $actual = readState($tmpDir);
    check(hash_equals($state['token'], $actual['token']), 'Checkpoint nonce round-trips');
    check(!array_key_exists('password', $actual['database']), 'Checkpoint excludes database password');
} finally {
    @unlink(stateFile($tmpDir));
    @rmdir($tmpDir . '/config');
    @rmdir($tmpDir);
}

// Verify every non-idempotent ALTER uses an ADD CONSTRAINT pattern handled by the resume logic.
$constraints = 0;
foreach ($queries as $query) {
    if (preg_match('/^\s*ALTER\s+TABLE/i', $query)) {
        check((bool)preg_match('/^\s*ALTER\s+TABLE\s+`([^`]+)`\s+ADD\s+CONSTRAINT\s+`([^`]+)`/i', $query), 'ALTER is resumable: ' . substr(trim($query), 0, 44));
        $constraints++;
    }
}
check($constraints > 10, 'ALTER constraints covered');

// No PDO MySQL server is needed to test the resume guard's non-idempotent FK probe.
class FakeConstraintStatement extends PDOStatement {
    public function __construct(private int $existing) {}
    public function execute(?array $params = null): bool { return true; }
    public function fetchColumn(int $column = 0): mixed { return $this->existing; }
}
class FakeConstraintPDO extends PDO {
    public function __construct(private int $existing) {}
    public function prepare(string $query, array $options = []): PDOStatement|false {
        return new FakeConstraintStatement($this->existing);
    }
}
$alter = null;
foreach ($queries as $q) {
    if (str_starts_with(trim($q), 'ALTER TABLE')) { $alter = $q; break; }
}
check($alter !== null, 'Foreign-key test query present');
check(schemaStatementAlreadyDone(new FakeConstraintPDO(1), $alter), 'Resume skips already-created FK');
check(!schemaStatementAlreadyDone(new FakeConstraintPDO(0), $alter), 'Resume runs missing FK');
check(!schemaStatementAlreadyDone(new FakeConstraintPDO(1), 'CREATE TABLE IF NOT EXISTS `test` (`id` int)'), 'CREATE TABLE stays idempotent');
echo "ALL INSTALLER UNIT TESTS PASSED\n";
