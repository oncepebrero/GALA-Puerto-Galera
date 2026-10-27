<?php
/**
 * Lazy PDO connection. Browsing never needs the database; only feedback and staff tools do.
 * Self-healing: if the database or its tables are missing (for example schema.sql was never imported),
 * they are created automatically the first time the connection is used.
 */
function gala_ddl(): array
{
    return [
        'feedback' => "CREATE TABLE IF NOT EXISTS feedback (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  place_slug    VARCHAR(120) NOT NULL DEFAULT '',
  place_name    VARCHAR(160) NOT NULL DEFAULT '',
  kind          ENUM('Rating','Suggestion','Concern') NOT NULL DEFAULT 'Rating',
  rating        TINYINT UNSIGNED NULL,
  comment       VARCHAR(900) NULL,
  nickname      VARCHAR(40) NULL,
  photo         VARCHAR(80) NULL,
  source        VARCHAR(10) NOT NULL DEFAULT 'visitor',
  status        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  visitor_hash  CHAR(64) NOT NULL DEFAULT '',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  moderated_at  TIMESTAMP NULL DEFAULT NULL,
  moderated_by  VARCHAR(50) NULL,
  PRIMARY KEY (id),
  KEY idx_place_status (place_slug, status),
  KEY idx_status_created (status, created_at),
  KEY idx_visitor_time (visitor_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        'events' => "CREATE TABLE IF NOT EXISTS events (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title       VARCHAR(140) NOT NULL DEFAULT '',
  type        VARCHAR(20) NOT NULL DEFAULT 'Event',
  starts_on   DATE NULL,
  ends_on     DATE NULL,
  venue       VARCHAR(160) NULL,
  organizer   VARCHAR(160) NULL,
  details     VARCHAR(900) NULL,
  link        VARCHAR(255) NULL,
  verified    TINYINT(1) NOT NULL DEFAULT 0,
  published   TINYINT(1) NOT NULL DEFAULT 1,
  source      VARCHAR(10) NOT NULL DEFAULT 'staff',
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NULL DEFAULT NULL,
  updated_by  VARCHAR(50) NULL,
  PRIMARY KEY (id),
  KEY idx_dates (starts_on, ends_on),
  KEY idx_pub (published, verified)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        'admins' => "CREATE TABLE IF NOT EXISTS admins (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username   VARCHAR(50) NOT NULL,
  pass_hash  VARCHAR(255) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        'login_attempts' => "CREATE TABLE IF NOT EXISTS login_attempts (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip_hash      CHAR(64) NOT NULL DEFAULT '',
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ip_time (ip_hash, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
}

/** Columns every table must have: name => definition used when a column is missing from an older table. */
function gala_columns(): array
{
    return [
        'feedback' => [
            'id' => 'INT UNSIGNED NOT NULL AUTO_INCREMENT', 'place_slug' => "VARCHAR(120) NOT NULL DEFAULT ''", 'place_name' => "VARCHAR(160) NOT NULL DEFAULT ''",
            'kind' => "ENUM('Rating','Suggestion','Concern') NOT NULL DEFAULT 'Rating'", 'rating' => 'TINYINT UNSIGNED NULL', 'comment' => 'VARCHAR(900) NULL',
            'nickname' => 'VARCHAR(40) NULL', 'photo' => 'VARCHAR(80) NULL', 'source' => "VARCHAR(10) NOT NULL DEFAULT 'visitor'",
            'status' => "ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'", 'visitor_hash' => "CHAR(64) NOT NULL DEFAULT ''",
            'created_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP', 'moderated_at' => 'TIMESTAMP NULL DEFAULT NULL', 'moderated_by' => 'VARCHAR(50) NULL',
        ],
        'events' => [
            'id' => 'INT UNSIGNED NOT NULL AUTO_INCREMENT', 'title' => "VARCHAR(140) NOT NULL DEFAULT ''", 'type' => "VARCHAR(20) NOT NULL DEFAULT 'Event'", 'starts_on' => 'DATE NULL', 'ends_on' => 'DATE NULL',
            'venue' => 'VARCHAR(160) NULL', 'organizer' => 'VARCHAR(160) NULL', 'details' => 'VARCHAR(900) NULL', 'link' => 'VARCHAR(255) NULL', 'verified' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'published' => 'TINYINT(1) NOT NULL DEFAULT 1', 'source' => "VARCHAR(10) NOT NULL DEFAULT 'staff'", 'created_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP', 'updated_at' => 'TIMESTAMP NULL DEFAULT NULL', 'updated_by' => 'VARCHAR(50) NULL',
        ],
        'admins' => ['id' => 'INT UNSIGNED NOT NULL AUTO_INCREMENT', 'username' => "VARCHAR(50) NOT NULL DEFAULT ''", 'pass_hash' => "VARCHAR(255) NOT NULL DEFAULT ''", 'created_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'],
        'login_attempts' => ['id' => 'INT UNSIGNED NOT NULL AUTO_INCREMENT', 'ip_hash' => "CHAR(64) NOT NULL DEFAULT ''", 'attempted_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'],
    ];
}

/** Insert the sample events from data/events.php (dates are relative to today, so they never go stale). Returns how many were added. */
function db_seed_events(PDO $pdo, string $source = 'sample'): int
{
    $file = ROOT_PATH . '/data/events.php';
    if (!is_file($file)) return 0;
    $ins = $pdo->prepare('INSERT INTO events (title, type, starts_on, ends_on, venue, organizer, details, link, verified, published, source) VALUES (?,?,?,?,?,?,?,?,?,1,?)');
    $n = 0;
    foreach ((array) require $file as $e) {
        $ins->execute([$e['title'], $e['type'] ?? 'Event', $e['date'] ?: null, !empty($e['end']) ? $e['end'] : null, $e['venue'] ?? null, $e['organizer'] ?? null, $e['text'] ?? null, $e['link'] ?? null, !empty($e['verified']) ? 1 : 0, $source]);
        $n++;
    }
    return $n;
}

/**
 * Make sure every table and column exists. Returns a list of what was done / what went wrong
 * (empty when everything was already fine). Never throws.
 */
function db_repair(PDO $pdo): array
{
    $log = [];
    foreach (gala_ddl() as $table => $sql) {
        try {
            $have = [];
            try { foreach ($pdo->query("SHOW COLUMNS FROM `$table`") as $c) $have[strtolower($c['Field'])] = true; }
            catch (Throwable $e) {
                $pdo->exec($sql); $log[] = "Created table $table.";
                if ($table === 'events') { $n = db_seed_events($pdo); if ($n) $log[] = "Added $n sample events (edit or delete them in the staff area)."; }
                continue;
            }
            $cols = []; foreach ($pdo->query("SHOW COLUMNS FROM `$table`") as $c) $cols[strtolower($c['Field'])] = $c;
            foreach (gala_columns()[$table] as $col => $def) {
                // Older tables made some columns NOT NULL with no default, which makes hand-written INSERTs fail. Give them a default.
                if (isset($cols[$col]) && $cols[$col]['Null'] === 'NO' && $cols[$col]['Default'] === null && stripos($cols[$col]['Extra'], 'auto_increment') === false
                    && stripos($def, 'NOT NULL DEFAULT') !== false && stripos($def, 'CURRENT_TIMESTAMP') === false) {
                    $pdo->exec("ALTER TABLE `$table` MODIFY COLUMN `$col` $def"); $log[] = "Gave $table.$col a default value.";
                }
                if (isset($have[$col])) continue;
                if ($col === 'id') { $log[] = "Table $table has no id column. Use the rebuild tool."; continue; }
                $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` $def");
                $log[] = "Added missing column $table.$col.";
            }
            // Leftover columns from an older version (for example events.event_date) that are NOT NULL without a default
            // block every INSERT with error #1364. Keep the data, but make them optional.
            $cols = []; foreach ($pdo->query("SHOW COLUMNS FROM `$table`") as $c) $cols[strtolower($c['Field'])] = $c;
            if ($table === 'events' && isset($cols['event_date']) && isset($cols['starts_on']) && preg_match('/^(date|datetime|timestamp)/i', $cols['event_date']['Type'])) {
                $n = $pdo->exec("UPDATE `events` SET `starts_on` = DATE(`event_date`) WHERE `starts_on` IS NULL AND `event_date` IS NOT NULL AND `event_date` > '0000-00-00'");
                if ($n) $log[] = "Copied $n old event dates from events.event_date into events.starts_on.";
            }
            foreach ($cols as $name => $c) {
                if (isset(gala_columns()[$table][$name])) continue;
                if ($c['Null'] === 'NO' && $c['Default'] === null && stripos($c['Extra'], 'auto_increment') === false && stripos($c['Extra'], 'generated') === false) {
                    $pdo->exec("ALTER TABLE `$table` MODIFY COLUMN `{$c['Field']}` {$c['Type']} NULL DEFAULT NULL"); $log[] = "Made old column $table.{$c['Field']} optional (it blocked saving).";
                }
            }
        } catch (Throwable $t) {
            $log[] = "PROBLEM with table $table: " . $t->getMessage();
        }
    }
    return $log;
}

/** Cheap guard run once per request on the first connection (three SHOW COLUMNS queries). */
function db_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    $log = db_repair($pdo);
    $bad = array_filter($log, fn($l) => str_starts_with($l, 'PROBLEM') || str_contains($l, 'Use the rebuild'));
    if ($bad) { $GLOBALS['GALA_DB_ERROR'] = implode(' | ', $bad); error_log('[GALA] schema: ' . implode(' | ', $bad)); }
    if ($log) error_log('[GALA] schema repaired: ' . implode(' | ', $log));
}

function db(): ?PDO
{
    static $pdo = false;
    if ($pdo !== false) return $pdo;
    $c = $GLOBALS['GALA']['db'];
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 3,
    ];
    $dsn = fn(bool $withDb) => sprintf('mysql:host=%s;port=%d;%scharset=utf8mb4', $c['host'], $c['port'], $withDb ? 'dbname=' . $c['name'] . ';' : '');
    try {
        try {
            $pdo = new PDO($dsn(true), $c['user'], $c['pass'], $opts);
        } catch (PDOException $t) {
            if (stripos($t->getMessage(), 'unknown database') === false) throw $t;
            // The database itself does not exist yet: create it (works with the Laragon root account).
            $tmp = new PDO($dsn(false), $c['user'], $c['pass'], $opts);
            $tmp->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $c['name']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $pdo = new PDO($dsn(true), $c['user'], $c['pass'], $opts);
        }
        db_ensure_schema($pdo);
    } catch (Throwable $t) {
        error_log('[GALA] DB connect failed: ' . $t->getMessage());
        $GLOBALS['GALA_DB_ERROR'] = $t->getMessage();
        $pdo = null;
    }
    return $pdo;
}
function db_ok(): bool { return db() !== null; }
