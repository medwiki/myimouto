<?php
/**
 * MyImouto PHP 8.5 interactive, resumable fresh installer (CLI only).
 * All changes are scoped to a NEW empty database and this application directory.
 * No Composer, network access, or server configuration is installed.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

function tell(string $message = ''): void { fwrite(STDOUT, $message . "\n"); }
function errorLine(string $message): void { fwrite(STDERR, "[ERROR] " . $message . "\n"); }
function stopInstall(string $message, int $status = 1): never { errorLine($message); exit($status); }
function yamlQuote(string $text): string { return "'" . str_replace("'", "''", $text) . "'"; }
function entry(string $label, ?string $default = null, bool $secret = false): string {
    $def = $default === null || $secret ? '' : " [{$default}]";
    fwrite(STDOUT, "{$label}{$def}: ");
    $previous = null;
    if ($secret && stream_isatty(STDIN)) {
        if (!function_exists('exec')) stopInstall('The terminal requires exec() and stty for hidden password entry.');
        $parts = [];
        @exec('stty -g 2>/dev/null', $parts, $code);
        if ($code !== 0 || !$parts) stopInstall('Cannot read terminal echo settings. Use a normal interactive SSH terminal.');
        $previous = trim($parts[0]);
        @exec('stty -echo 2>/dev/null', $unused, $code);
        if ($code !== 0) stopInstall('Cannot hide password entry; refusing to echo a password.');
    } elseif ($secret) {
        tell("\n[WARNING] Standard input is not a TTY. Secret entry may be visible in the terminal or input source.");
    }
    try {
        $line = fgets(STDIN);
    } finally {
        if ($previous !== null) {
            @exec('stty ' . escapeshellarg($previous) . ' 2>/dev/null');
            fwrite(STDOUT, "\n");
        }
    }
    if ($line === false) stopInstall('No more input. Nothing further was changed; see --resume if setup had begun.');
    $value = rtrim($line, "\r\n");
    return $value === '' && $default !== null ? $default : $value;
}
/** Validator returns null when valid or an immediately displayed error message. */
function validated(string $label, ?string $default, callable $validator, bool $secret = false): string {
    while (true) {
        $value = entry($label, $default, $secret);
        $problem = $validator($value);
        if ($problem === null) return $value;
        errorLine($problem . ' Please enter a corrected value.');
    }
}
function choose(string $question, array $options): string {
    $options = array_map('strtolower', $options);
    while (true) {
        $value = strtolower(trim(entry($question)));
        if (in_array($value, $options, true)) return $value;
        errorLine('Choose one of: ' . implode(', ', $options));
    }
}
function packageSchema(string $root): array {
    foreach (['install/structure.php', 'public/index.php', 'vendor/autoload.php',
        'config/default_config.php', 'public/assets/manifest.yml', 'app/models/User.php',
        'app/models/HistoryChange.php'] as $item) {
        if (!is_file($root . '/' . $item)) stopInstall('Incomplete source package: ' . $item);
    }
    $schema = require $root . '/install/structure.php';
    if (!is_array($schema) || count($schema) < 60) stopInstall('Database schema missing or incomplete.');
    foreach ($schema as $index => $sql) {
        if (!is_string($sql) || trim($sql) === '') stopInstall('Invalid schema statement #' . ($index + 1));
    }
    return array_values($schema);
}
function requirements(): array {
    tell('Running PHP ' . PHP_VERSION . ' (' . PHP_SAPI . ')');
    $issues = [];
    if (PHP_VERSION_ID < 80500) $issues[] = 'PHP 8.5 required: use /opt/remi/php85/root/usr/bin/php';
    $groups = [
        'pdo_mysql' => 'php85-php-mysqlnd', 'gd' => 'php85-php-gd',
        'mbstring' => 'php85-php-mbstring', 'curl' => 'php85-php-common',
        'dom' => 'php85-php-xml', 'SimpleXML' => 'php85-php-xml',
        'posix' => 'php85-php-process',
    ];
    foreach (['PDO','pdo_mysql','gd','mbstring','fileinfo','curl','dom','SimpleXML',
        'iconv','ctype','json','openssl','posix'] as $extension) {
        if (!extension_loaded($extension)) {
            $issues[] = 'Missing extension ' . $extension . (isset($groups[$extension]) ? ' (install ' . $groups[$extension] . ')' : '');
        }
    }
    if (!function_exists('chgrp')) $issues[] = 'The PHP chgrp() function must be enabled.';
    if (!function_exists('exec')) $issues[] = 'The PHP exec() function is needed for secure terminal password input.';
    if (!$issues) tell('[OK] All PHP prerequisites are present.');
    else foreach ($issues as $problem) errorLine($problem);
    return $issues;
}
function validUrl(string $url): ?string {
    $parts = parse_url(rtrim($url, '/'));
    if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['http','https'], true)
        || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
        || !empty($parts['path']) || isset($parts['query']) || isset($parts['fragment'])) {
        return 'Use a base URL such as https://example.com (no path, credentials, query, or fragment).';
    }
    return null;
}
function freshInputs(): array {
    tell("\n[STEP 1] Enter the database connection. Every field is checked immediately.");
    $details = [];
    $details['host'] = validated('MySQL hostname', 'localhost', fn($v) => preg_match('/^[a-zA-Z0-9._:-]+$/D', $v) ? null : 'Invalid MySQL hostname.');
    $details['port'] = validated('MySQL port', '3306', fn($v) => ctype_digit($v) && (int)$v >= 1 && (int)$v <= 65535 ? null : 'Port must be between 1 and 65535.');
    $details['name'] = validated('Name of a NEW, EMPTY database', 'myimouto', fn($v) => preg_match('/^[a-zA-Z0-9_]{1,64}$/D', $v) ? null : 'Database name allows only letters, digits, underscores (max 64).');
    $details['user'] = validated('MySQL application username', 'myimouto_app', fn($v) => preg_match('/^[a-zA-Z0-9_]{1,32}$/D', $v) ? null : 'Database username allows only letters, digits, underscores (max 32).');
    $details['password'] = validated('MySQL application password', null, fn($v) => $v !== '' && !preg_match('/[\x00-\x1f]/', $v) ? null : 'Database password must be nonempty without control characters.', true);
    return $details;
}
function askSiteInputs(): array {
    tell("\n[STEP 2] Site and administrator settings.");
    $values = [];
    $values['url'] = rtrim(validated('Public site URL', 'https://example.com', 'validUrl'), '/');
    $values['title'] = validated('Site name', 'MyImouto', fn($v) => $v !== '' && mb_strlen($v) < 120 ? null : 'Site name must contain 1-119 characters.');
    $values['contact'] = validated('Admin contact email (optional)', '', fn($v) => $v === '' || filter_var($v, FILTER_VALIDATE_EMAIL) ? null : 'Email is invalid.');
    $values['admin'] = validated('Initial admin username', 'admin', fn($v) => preg_match('/^[A-Za-z0-9_-]{3,32}$/D', $v) ? null : 'Username must have 3-32 letters, digits, hyphens or underscores.');
    while (true) {
        $pass = validated('Initial admin password (12+ characters)', null, fn($v) => strlen($v) >= 12 ? null : 'Administrator password must contain at least 12 characters.', true);
        $again = entry('Repeat initial admin password', null, true);
        if (hash_equals($pass, $again)) break;
        errorLine('Administrator passwords do not match. Please enter both passwords again.');
    }
    $values['salt'] = bin2hex(random_bytes(32));
    $values['adminHash'] = sha1($values['salt'] . '--' . $pass . '--');
    unset($pass, $again);
    $values['group'] = validated('PHP-FPM worker group', 'apache', function($v) {
        if (!preg_match('/^[a-z_][a-z0-9_-]*$/D', $v)) return 'Invalid Linux group name.';
        if (!function_exists('posix_getgrnam') || posix_getgrnam($v) === false) return 'Group does not exist on this server.';
        return null;
    });
    return $values;
}
function assertWritable(string $root, string $group): void {
    tell("\n[STEP 3] Verifying file permissions BEFORE creating database tables...");
    foreach (['config', 'tmp', 'log', 'public/data'] as $dir) {
        $path = $root . '/' . $dir;
        if (!is_dir($path) || !is_writable($path)) throw new RuntimeException('Directory not writable: ' . $path);
        $tmp = @tempnam($path, '.imouto-preflight-');
        if ($tmp === false) throw new RuntimeException('Cannot create files in ' . $path);
        try {
            if (!@chgrp($tmp, $group) || !@chmod($tmp, 0640)) {
                throw new RuntimeException('Cannot assign group ' . $group . ' in ' . $dir . '. Run setup with appropriate permissions.');
            }
        } finally { @unlink($tmp); }
    }
    tell('[OK] Installer can create group-readable private configuration and upload files.');
}
function connectDatabase(array &$db, bool $allowIdentityChange = true): PDO {
    while (true) {
        tell('Connecting to ' . $db['host'] . ':' . $db['port'] . '/' . $db['name'] . ' as ' . $db['user'] . ' ...');
        try {
            $pdo = new PDO('mysql:host=' . $db['host'] . ';port=' . $db['port'] . ';dbname=' . $db['name'] . ';charset=utf8',
                $db['user'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
            $pdo->exec("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");
            tell('[OK] MySQL connection and session SQL mode.');
            return $pdo;
        } catch (Throwable $e) {
            errorLine('MySQL connection failed immediately: ' . $e->getMessage());
            $decision = $allowIdentityChange
                ? choose('Retry [r], change password [p], edit connection [e], or quit [q]', ['r','p','e','q'])
                : choose('Retry [r], change password [p], or quit [q]', ['r','p','q']);
            if ($decision === 'q') stopInstall('Stopped before running any database schema statements.');
            if ($decision === 'p') {
                $db['password'] = validated('MySQL password', null, fn($v) => $v !== '' ? null : 'Password cannot be empty.', true);
            } elseif ($decision === 'e') {
                $db = freshInputs();
            }
        }
    }
}
function stateFile(string $root): string { return $root . '/config/.fresh-install-state.json'; }
function atomicFile(string $target, string $content, string $group, int $mode): void {
    $tmp = @tempnam(dirname($target), '.myimouto-install-');
    if ($tmp === false) throw new RuntimeException('Cannot create temporary file in ' . dirname($target));
    try {
        if (@file_put_contents($tmp, $content, LOCK_EX) === false) throw new RuntimeException('Write failed: ' . $target);
        if ($group !== '' && !@chgrp($tmp, $group)) throw new RuntimeException('Cannot chgrp to ' . $group . ': ' . $target);
        if (!@chmod($tmp, $mode)) throw new RuntimeException('Cannot chmod: ' . $target);
        if (!@rename($tmp, $target)) throw new RuntimeException('Cannot rename into place: ' . $target);
    } finally { if (file_exists($tmp)) @unlink($tmp); }
}
function writeState(string $root, array $state): void {
    $data = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    atomicFile(stateFile($root), $data . "\n", '', 0600);
}
function readState(string $root): array {
    $file = stateFile($root);
    if (!is_file($file)) stopInstall('No incomplete installation found here. Start with --install.');
    if ((fileperms($file) & 077) !== 0) stopInstall('Installer checkpoint permissions are too open. Require chmod 600 ' . $file);
    $state = json_decode((string) file_get_contents($file), true);
    if (!is_array($state) || empty($state['token']) || empty($state['schemaHash']) || empty($state['site'])) {
        stopInstall('Installer checkpoint is damaged. Do not manually rerun schema on an unknown database.');
    }
    return $state;
}
function installGuard(PDO $pdo, array $state, bool $resume): void {
    $rows = $pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM);
    $tables = array_map(static fn($r) => $r[0], $rows);
    $guardName = '__myimouto_fresh_install_guard';
    if (!in_array($guardName, $tables, true)) {
        if ($tables !== []) stopInstall('Database already contains tables but no matching installer guard. Refusing to touch existing data.');
        $pdo->exec('CREATE TABLE `__myimouto_fresh_install_guard` (`token` varchar(64) NOT NULL PRIMARY KEY, `schema_hash` char(64) NOT NULL, `created_at` datetime NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8');
        $stmt = $pdo->prepare('INSERT INTO `__myimouto_fresh_install_guard` (`token`,`schema_hash`,`created_at`) VALUES (?, ?, NOW())');
        $stmt->execute([$state['token'], $state['schemaHash']]);
        tell('[OK] New empty database claimed for this installation.');
    } else {
        if (!$resume) stopInstall('Database contains an installation guard. Use --resume only with the original installer directory.');
        $guard = $pdo->query('SELECT `token`, `schema_hash` FROM `__myimouto_fresh_install_guard`')->fetchAll();
        // A disconnect can occur after CREATE TABLE but before INSERT. Recover only
        // when that empty guard is the ONLY table, and the private checkpoint matches.
        if (count($guard) === 0 && count($tables) === 1) {
            $stmt = $pdo->prepare('INSERT INTO `__myimouto_fresh_install_guard` (`token`,`schema_hash`,`created_at`) VALUES (?, ?, NOW())');
            $stmt->execute([$state['token'], $state['schemaHash']]);
            tell('[OK] Recovered interrupted initial database guard creation.');
            return;
        }
        if (count($guard) !== 1 || !hash_equals((string)$state['token'], (string)$guard[0]['token'])
            || !hash_equals((string)$state['schemaHash'], (string)$guard[0]['schema_hash'])) {
            stopInstall('Installation checkpoint does not match this database. No schema changes were made.');
        }
        tell('[OK] Database guard matches this installation checkpoint.');
    }
}
/** Handles the only non-idempotent schema statements (ADD CONSTRAINT). */
function schemaStatementAlreadyDone(PDO $pdo, string $sql): bool {
    if (preg_match('/^\s*ALTER\s+TABLE\s+`([^`]+)`\s+ADD\s+CONSTRAINT\s+`([^`]+)`/i', $sql, $match)) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?');
        $stmt->execute([$match[1], $match[2]]);
        return (int) $stmt->fetchColumn() > 0;
    }
    return false;
}
function runSchema(PDO $pdo, array $schema): void {
    $count = count($schema);
    tell("\n[STEP 4] Installing {$count} schema statements (safe to resume after interruption)...");
    foreach ($schema as $index => $sql) {
        $label = 'Schema ' . ($index + 1) . '/' . $count;
        while (true) {
            try {
                if (schemaStatementAlreadyDone($pdo, $sql)) {
                    tell("  {$label}: already present; skipped");
                } else {
                    $pdo->exec($sql);
                    tell("  {$label}: OK");
                }
                break;
            } catch (Throwable $e) {
                errorLine("{$label} failed: " . $e->getMessage());
                tell('  Statement: ' . substr(preg_replace('/\s+/', ' ', trim($sql)), 0, 180));
                $decision = choose('Retry THIS statement [r], or stop safely for --resume [q]', ['r','q']);
                if ($decision === 'q') stopInstall('Incomplete installation safely stopped. Correct the database issue, then run --resume.');
            }
        }
    }
    tell('[OK] Database schema is installed.');
}
function seedRecords(PDO $pdo, array $site): void {
    tell("\n[STEP 5] Creating initial administrator, counters, and recurring jobs...");
    while (true) {
        try {
            $admin = $pdo->prepare('SELECT id, password_hash FROM users WHERE name = ?');
            $admin->execute([$site['admin']]);
            $record = $admin->fetch();
            if (!$record) {
                $pdo->prepare('INSERT INTO users (created_at, name, password_hash, level, show_advanced_editing) VALUES (?, ?, ?, 50, 1)')
                    ->execute([date('Y-m-d H:i:s'), $site['admin'], $site['adminHash']]);
                $adminId = (int)$pdo->lastInsertId();
            } else {
                if (!hash_equals($site['adminHash'], (string) $record['password_hash'])) {
                    throw new RuntimeException('Existing admin username has a different password hash. Refusing to overwrite a user.');
                }
                $adminId = (int)$record['id'];
            }
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM user_blacklisted_tags WHERE user_id = ?');
            $stmt->execute([$adminId]);
            if (!$stmt->fetchColumn()) {
                $pdo->prepare('INSERT INTO user_blacklisted_tags (user_id, tags) VALUES (?, ?)')
                    ->execute([$adminId, "rating:q\r\nrating:e"]);
            }
            foreach ([['posts',0],['users',1],['non-explicit_posts',0]] as [$key, $value]) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM table_data WHERE name = ?');
                $stmt->execute([$key]);
                if (!$stmt->fetchColumn()) {
                    $pdo->prepare('INSERT INTO table_data (`name`, `row_count`) VALUES (?, ?)')->execute([$key, $value]);
                }
            }
            foreach (['external_data_search','upload_batch_posts','periodic_maintenance'] as $type) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM job_tasks WHERE task_type = ?');
                $stmt->execute([$type]);
                if (!$stmt->fetchColumn()) {
                    $pdo->prepare('INSERT INTO job_tasks (task_type, data_as_json, status, repeat_count) VALUES (?, ?, ?, ?)')
                        ->execute([$type, '{}', 'pending', -1]);
                }
            }
            tell('[OK] Initial database records are installed.');
            return;
        } catch (Throwable $e) {
            errorLine('Database initialization failed: ' . $e->getMessage());
            if (choose('Retry record setup [r], or stop safely for --resume [q]', ['r','q']) === 'q') {
                stopInstall('Incomplete setup. The seed step can be resumed without duplicating the admin.');
            }
        }
    }
}
function generatedConfig(array $state, string $dbPassword): array {
    $site = $state['site'];
    $siteParts = parse_url($site['url']);
    $host = $siteParts['host'] . (isset($siteParts['port']) ? ':' . $siteParts['port'] : '');
    $config = "<?php\nnamespace MyImouto;\n\nclass LocalConfig extends DefaultConfig\n{\n"
        . '    public $app_name = ' . var_export($site['title'], true) . ";\n"
        . '    public $server_host = ' . var_export($host, true) . ";\n"
        . '    public $url_base = ' . var_export($site['url'], true) . ";\n"
        . '    public $admin_contact = ' . var_export($site['contact'], true) . ";\n"
        . '    public $download_filename_prefix = ' . var_export('myimouto', true) . ";\n"
        . '    public $user_password_salt = ' . var_export($site['salt'], true) . ";\n}\n";
    $db = $state['database'];
    $yaml = "# Generated by MyImouto fresh installer. Keep private.\nlogin: &login\n"
        . '  adapter: mysql' . "\n  host: " . yamlQuote($db['host']) . "\n  port: " . (int)$db['port'] . "\n"
        . '  username: ' . yamlQuote($db['user']) . "\n  password: " . yamlQuote($dbPassword) . "\n"
        . "  driver_options:\n    1002: \"SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'\"\n"
        . "development:\n  <<: *login\n  database: " . yamlQuote($db['name']) . "\n"
        . "production:\n  <<: *login\n  database: " . yamlQuote($db['name']) . "\n";
    return ['config/config.php' => $config, 'config/database.yml' => $yaml];
}
function finishFiles(string $root, array $state, string $dbPassword): void {
    tell("\n[STEP 6] Writing protected configuration and storage directory permissions...");
    foreach (generatedConfig($state, $dbPassword) as $relative => $content) {
        $target = $root . '/' . $relative;
        while (true) {
            try {
                if (is_file($target) && file_get_contents($target) === $content) {
                    tell("  {$relative}: already correct");
                } else {
                    atomicFile($target, $content, $state['site']['group'], 0640);
                    tell("  {$relative}: saved");
                }
                break;
            } catch (Throwable $e) {
                errorLine($relative . ': ' . $e->getMessage());
                if (choose('Retry writing this file [r], or stop for --resume [q]', ['r','q']) === 'q') {
                    stopInstall('Configuration incomplete. Correct permissions, then run --resume.');
                }
            }
        }
    }
    $dirs = ['log','tmp','public/data','public/data/avatars','public/data/export','public/data/image',
        'public/data/import','public/data/jpeg','public/data/preview','public/data/sample'];
    foreach ($dirs as $dir) {
        $path = $root . '/' . $dir;
        while (true) {
            try {
                if (!is_dir($path) && !@mkdir($path, 0770, true) && !is_dir($path)) {
                    throw new RuntimeException('Cannot create directory ' . $path);
                }
                if (!@chgrp($path, $state['site']['group']) || !@chmod($path, 0770)) {
                    throw new RuntimeException('Cannot grant PHP-FPM group access to ' . $path);
                }
                tell("  {$dir}: OK");
                break;
            } catch (Throwable $e) {
                errorLine($e->getMessage());
                if (choose('Retry directory permission [r], or stop for --resume [q]', ['r','q']) === 'q') {
                    stopInstall('Storage setup incomplete. Correct permissions, then run --resume.');
                }
            }
        }
    }
}
function isPlaceholder(string $root): bool {
    $configPath = $root . '/config/config.php';
    $databasePath = $root . '/config/database.yml';
    // GitHub checkouts intentionally omit private configuration files.
    // The installer generates both during STEP 6 after all validation succeeds.
    if (!file_exists($configPath) && !file_exists($databasePath)) return true;
    // Retain support for previously distributed ZIP placeholders.
    $a = @file_get_contents($configPath);
    $b = @file_get_contents($databasePath);
    return is_string($a) && str_contains($a, 'not installed')
        && is_string($b) && str_contains($b, 'No database credentials are bundled');
}
function runInstaller(array $args): int {
    $root = dirname(__DIR__);
    $mode = $args[0] ?? '--help';
    if ($mode === '--help' || $mode === '-h') {
        tell('MyImouto PHP 8.5 fresh installer (v2)');
        tell('  --check     Validate PHP and source files (nonzero exit on failure)');
        tell('  --install   Start an interactive clean installation');
        tell('  --resume    Continue this installer’s interrupted fresh installation');
        tell('Exit with Ctrl+C before installing; after schema begins use --resume.');
        return 0;
    }
    if (!in_array($mode, ['--check','--install','--resume'], true) || count($args) !== 1) {
        stopInstall('Use --check, --install, or --resume.');
    }
    $schema = packageSchema($root);
    tell('[OK] Application package (' . count($schema) . ' SQL schema statements).');
    $issues = requirements();
    if ($issues) {
        errorLine('Fix the prerequisites listed above and rerun ' . $mode . '. No database changes were made.');
        return 1;
    }
    if ($mode === '--check') return 0;
    if (is_file($root . '/.fresh-install-complete')) stopInstall('Installation already finished. Refusing to alter an installed website.');
    $lock = @fopen($root . '/tmp/.fresh-install.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) stopInstall('Another installer is running or tmp/ is not writable.');
    $resume = $mode === '--resume';
    if ($resume) {
        $state = readState($root);
        if (!hash_equals((string)$state['schemaHash'], hash('sha256', serialize($schema)))) {
            stopInstall('Schema has changed since installation began. Restore the same release before --resume.');
        }
        tell('[RESUME] A protected local checkpoint was found. Database identity is locked.');
        $db = $state['database'];
        $db['password'] = validated('MySQL application password', null,
            fn($v) => $v !== '' ? null : 'Password cannot be empty.', true);
        $pdo = connectDatabase($db, false);
    } else {
        if (is_file(stateFile($root))) stopInstall('Incomplete setup already exists. Run --resume instead of --install.');
        if (!isPlaceholder($root)) stopInstall('Existing configuration detected; refusing to overwrite. Use a new application directory.');
        $db = freshInputs();
        while (true) {
            $pdo = connectDatabase($db, true);
            $tables = $pdo->query('SHOW FULL TABLES')->fetchAll();
            if (!$tables) { tell('[OK] Selected database is empty.'); break; }
            errorLine('The selected database contains ' . count($tables) . ' table(s). An EMPTY database is required. No changes made.');
            if (choose('Enter a different NEW database [e], or quit [q]', ['e','q']) === 'q') {
                stopInstall('Existing database protected: no schema changes were made.');
            }
            $db = freshInputs();
        }
        $site = askSiteInputs();
        while (true) {
            try {
                assertWritable($root, $site['group']);
                break;
            } catch (Throwable $e) {
                errorLine('Filesystem preflight: ' . $e->getMessage());
                $decision = choose('Retry permissions [r], choose another FPM group [g], or quit [q]', ['r','g','q']);
                if ($decision === 'q') stopInstall('Stopped before creating database tables.');
                if ($decision === 'g') {
                    $site['group'] = validated('PHP-FPM worker group', $site['group'], function ($v) {
                        if (!preg_match('/^[a-z_][a-z0-9_-]*$/D', $v)) return 'Invalid Linux group name.';
                        if (!function_exists('posix_getgrnam') || posix_getgrnam($v) === false) return 'Group does not exist on this server.';
                        return null;
                    });
                }
            }
        }
        $state = [
            'installerVersion' => 2, 'schemaHash' => hash('sha256', serialize($schema)),
            'token' => bin2hex(random_bytes(24)),
            'database' => array_diff_key($db, ['password'=>true]), 'site' => $site,
        ];
        // Private recovery checkpoint must exist before any MySQL DDL.
        try {
            writeState($root, $state);
        } catch (Throwable $e) {
            stopInstall('Cannot write protected installer checkpoint: ' . $e->getMessage() . '. No schema changes were made.');
        }
        tell('[OK] Protected resume checkpoint saved (no DB password stored).');
    }
    if ($resume) {
        foreach (['host','port','name','user'] as $key) {
            if ((string)$db[$key] !== (string)$state['database'][$key]) {
                stopInstall('Resume cannot change the database identity. Restore the original connection and rerun --resume.');
            }
        }
    }
    try {
        installGuard($pdo, $state, $resume);
    } catch (Throwable $e) {
        stopInstall('Could not establish protected database installation marker: ' . $e->getMessage() . '. You may retry with --resume.');
    }
    runSchema($pdo, $schema);
    seedRecords($pdo, $state['site']);
    finishFiles($root, $state, $db['password']);
    try {
        atomicFile($root . '/.fresh-install-complete', 'Completed: ' . date(DATE_ATOM) . "\n", '', 0644);
    } catch (Throwable $e) {
        stopInstall('Cannot create final completion marker: ' . $e->getMessage() . '. Retry with --resume.');
    }
    @unlink(stateFile($root));
    tell("\n[SUCCESS] Fresh installation finished. All 6 steps completed.");
    tell('Document root: ' . $root . '/public');
    tell('Verify PHP-FPM upload limits and image URL rewrite rules in the existing server.');
    tell('Do not run --install or --resume again on this site.');
    return 0;
}

if (!defined('MYIMOUTO_INSTALL_LIBRARY_ONLY')) {
    exit(runInstaller(array_slice($argv, 1)));
}
