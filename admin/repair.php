<?php
/**
 * Database checkup and repair. Works even when the rest of the site cannot reach the database.
 * Allowed from the computer running the server (Laragon), or with the setup key, or when signed in.
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
header('X-Robots-Tag: noindex, nofollow'); header('Cache-Control: no-store');

$allowed = gala_is_local() || !empty($_SESSION['admin']);
$key = (string) cfg('setup_key', '');
if (!$allowed && $key !== '' && hash_equals($key, (string) ($_POST['setup_key'] ?? $_GET['key'] ?? ''))) $allowed = true;
if (!$allowed) {
    http_response_code(403);
    echo '<!doctype html><meta charset="utf-8"><title>Not allowed</title><body style="font:18px/1.6 system-ui;max-width:38rem;margin:10vh auto;padding:0 1.5rem"><h1>Not allowed</h1><p>The checkup tool only opens on the computer that runs the site, or for a signed-in staff member. Open it from <code>localhost</code>, or add <code>?key=YOUR_SETUP_KEY</code> if you set a <code>setup_key</code> in <code>config/config.local.php</code>.</p>';
    exit;
}

$c = $GLOBALS['GALA']['db'];
$msgs = []; $pdo = null; $connErr = null; $serverVer = ''; $mode = '';
$self = url('admin/repair.php') . ($key !== '' && isset($_GET['key']) ? '?key=' . rawurlencode($key) : '');

function rp_connect(array $c, bool $withDb): PDO
{
    return new PDO(sprintf('mysql:host=%s;port=%d;%scharset=utf8mb4', $c['host'], $c['port'], $withDb ? 'dbname=' . $c['name'] . ';' : ''), $c['user'], $c['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_TIMEOUT => 4]);
}
function rp_try(array $c, ?string &$err): ?PDO
{
    try { return rp_connect($c, true); }
    catch (Throwable $t) {
        if (stripos($t->getMessage(), 'unknown database') !== false) {
            try { rp_connect($c, false)->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $c['name']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); return rp_connect($c, true); }
            catch (Throwable $t2) { $err = $t2->getMessage(); return null; }
        }
        $err = $t->getMessage(); return null;
    }
}

/* ------------ actions ------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) { $msgs[] = ['err', 'Session expired. Reload the page and try again.']; }
    else {
        $act = (string) ($_POST['act'] ?? '');
        $pdo = rp_try($c, $connErr);
        if (!$pdo) { $msgs[] = ['err', 'Cannot connect to the database: ' . $connErr]; }
        elseif ($act === 'repair') {
            $log = db_repair($pdo);
            $msgs[] = [$log && array_filter($log, fn($l) => str_starts_with($l, 'PROBLEM')) ? 'err' : 'ok', $log ? implode(' ', $log) : 'Everything was already in place. Nothing to repair.'];
        } elseif ($act === 'rebuild') {
            if (trim((string) ($_POST['confirm'] ?? '')) !== 'REBUILD') { $msgs[] = ['err', 'Type REBUILD in capital letters to confirm.']; }
            else {
                try {
                    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
                    foreach (array_keys(gala_ddl()) as $t) $pdo->exec("DROP TABLE IF EXISTS `$t`");
                    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
                    foreach (gala_ddl() as $sql) $pdo->exec($sql);
                    unset($_SESSION['admin'], $_SESSION['admin_at']);
                    $msgs[] = ['ok', 'The database tables were rebuilt from scratch. Next: open the staff area and create your staff account.'];
                } catch (Throwable $t) { $msgs[] = ['err', 'Rebuild failed: ' . $t->getMessage()]; }
            }
        }
    }
}

/* ------------ checks ------------ */
$checks = [];
$add = function (string $label, bool $ok, string $detail = '', bool $warn = false) use (&$checks) { $checks[] = [$label, $ok, $detail, $warn]; };
$add('PHP version', version_compare(PHP_VERSION, '8.1.0', '>='), PHP_VERSION . ' (8.1 or newer is required)');
foreach (['pdo_mysql', 'mbstring', 'json', 'session'] as $ext) $add("PHP extension: $ext", extension_loaded($ext), extension_loaded($ext) ? 'enabled' : 'missing, enable it in php.ini (Laragon: Menu → PHP → Extensions)');
$gdOk = extension_loaded('gd') && extension_loaded('fileinfo');
$add('Photo uploads (gd and fileinfo)', $gdOk, $gdOk ? 'ready, largest photo accepted: ' . fb_photo_limit_label() . ' (PHP upload_max_filesize ' . ini_get('upload_max_filesize') . ', post_max_size ' . ini_get('post_max_size') . ')' : 'enable the gd and fileinfo extensions in php.ini (Laragon: Menu → PHP → Extensions) to allow photos on the feedback form', true);
$upDir = ROOT_PATH . '/uploads/feedback'; if (!is_dir($upDir)) @mkdir($upDir, 0775, true);
$add('Photo folder writable', is_dir($upDir) && is_writable($upDir), $upDir, true);
$_SESSION['gala_probe'] = 1; $add('Sessions work', isset($_SESSION['gala_probe']), 'session id ' . substr(session_id(), 0, 6) . '…  save path: ' . (session_save_path() ?: 'default'));
$logDir = ROOT_PATH . '/logs'; if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
$add('Error log folder writable', is_dir($logDir) && is_writable($logDir), $logDir, true);
$add('Database settings', true, "host {$c['host']}, port {$c['port']}, user {$c['user']}, database {$c['name']}, password " . ($c['pass'] === '' ? '(empty)' : '(set)'));

$pdo = $pdo ?: rp_try($c, $connErr);
$tables = [];
if ($pdo) {
    $serverVer = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
    $mode = (string) $pdo->query('SELECT @@sql_mode')->fetchColumn();
    $add('Database connection', true, "connected to $serverVer, database “{$c['name']}” is ready");
    foreach (gala_columns() as $t => $cols) {
        $info = ['exists' => false, 'have' => [], 'missing' => [], 'extra' => [], 'rows' => null, 'extras_required' => []];
        try {
            foreach ($pdo->query("SHOW COLUMNS FROM `$t`") as $col) {
                $info['exists'] = true; $f = strtolower($col['Field']); $info['have'][] = $f;
                if (!isset($cols[$f])) { $info['extra'][] = $f; if ($col['Null'] === 'NO' && $col['Default'] === null && stripos($col['Extra'], 'auto_increment') === false) $info['extras_required'][] = $f; }
            }
            $info['missing'] = array_values(array_diff(array_keys($cols), $info['have']));
            $info['rows'] = (int) $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        } catch (Throwable $e) { /* table missing */ }
        $tables[$t] = $info;
        $ok = $info['exists'] && !$info['missing'] && !$info['extras_required'];
        $detail = !$info['exists'] ? 'table is missing' : ($info['missing'] ? 'missing columns: ' . implode(', ', $info['missing']) : ($info['extras_required'] ? 'has extra required columns that would block saving: ' . implode(', ', $info['extras_required']) . ' (click Repair now)' : $info['rows'] . ' row' . ($info['rows'] === 1 ? '' : 's')));
        $add("Table $t", $ok, $detail);
    }
} else {
    $add('Database connection', false, (string) $connErr . ' — start MySQL (Laragon: Start All) and check the settings above.');
}
$problems = count(array_filter($checks, fn($x) => !$x[1] && !$x[3]));
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title>Database checkup · GALÁ</title>
<style>
:root{--ink:#0b2236;--mut:#5a6b79;--line:#dde5ea;--ok:#146c43;--bad:#b3261e;--warn:#8a5a00;--bg:#f4f8fa}
*{box-sizing:border-box}body{margin:0;font:16px/1.55 system-ui,Segoe UI,Roboto,sans-serif;color:var(--ink);background:var(--bg)}
main{max-width:52rem;margin:0 auto;padding:2rem 1.2rem 4rem}h1{font-size:2rem;margin:.2rem 0 .3rem}h2{font-size:1.2rem;margin:2rem 0 .6rem}
.card{background:#fff;border:1px solid var(--line);border-radius:18px;padding:1.2rem 1.3rem;margin:1rem 0}
ul{list-style:none;margin:0;padding:0}li.row{display:flex;gap:.8rem;padding:.65rem 0;border-bottom:1px solid var(--line)}li.row:last-child{border:0}
.dot{flex:none;width:1.5rem;height:1.5rem;border-radius:50%;display:grid;place-items:center;color:#fff;font-weight:700;font-size:.9rem;margin-top:.1rem}.ok .dot{background:var(--ok)}.bad .dot{background:var(--bad)}.warn .dot{background:var(--warn)}
.row small{display:block;color:var(--mut);word-break:break-word}
.msg{padding:.9rem 1.1rem;border-radius:14px;margin:1rem 0;font-weight:600}.msg.ok{background:#e1f4e9;color:#0f4f31}.msg.err{background:#fbe4e2;color:#7d1b14}
button,.btn{font:inherit;font-weight:700;border:0;border-radius:999px;padding:.8rem 1.4rem;cursor:pointer;background:var(--ink);color:#fff;text-decoration:none;display:inline-block}
.btn2{background:#fff;color:var(--ink);border:1.5px solid var(--ink)}.danger{background:var(--bad)}
input[type=text]{font:inherit;padding:.7rem .9rem;border:1.5px solid var(--line);border-radius:12px;width:14rem}
code{background:#eef3f6;padding:.1rem .35rem;border-radius:6px}.sum{font-weight:700}.sum.ok{color:var(--ok)}.sum.bad{color:var(--bad)}
.actions{display:flex;gap:.7rem;flex-wrap:wrap;align-items:center}
</style></head><body><main>
<p><a href="<?= e(url('admin/')) ?>">← Staff area</a></p>
<h1>Database checkup</h1>
<p class="sum <?= $problems ? 'bad' : 'ok' ?>"><?= $problems ? $problems . ' problem' . ($problems === 1 ? '' : 's') . ' found' : 'Everything looks good' ?></p>
<?php foreach ($msgs as [$k, $m]): ?><div class="msg <?= $k ?>" role="status"><?= e($m) ?></div><?php endforeach; ?>

<div class="card"><ul>
<?php foreach ($checks as [$label, $ok, $detail, $warn]): ?>
  <li class="row <?= $ok ? 'ok' : ($warn ? 'warn' : 'bad') ?>"><span class="dot" aria-hidden="true"><?= $ok ? '✓' : ($warn ? '!' : '×') ?></span><div><strong><?= e($label) ?></strong><small><?= e($detail) ?></small></div></li>
<?php endforeach; ?></ul></div>

<?php if ($pdo): ?>
<h2>1. Repair</h2>
<div class="card"><p>Creates anything that is missing (database, tables, columns) and keeps all your data. Safe to run any time.</p>
<form method="post" action="<?= e($self) ?>" class="actions"><?= csrf_field() ?><input type="hidden" name="act" value="repair"><button type="submit">Repair now</button></form></div>

<h2>2. Rebuild from scratch</h2>
<div class="card"><p><strong>Deletes all feedback and all staff accounts</strong>, then creates fresh, empty tables. Use this if Repair does not fix the problem, or if you simply want a clean start. Afterwards you create a new staff account and (optionally) load sample data from the staff area.</p>
<form method="post" action="<?= e($self) ?>" class="actions"><?= csrf_field() ?><input type="hidden" name="act" value="rebuild">
<label>Type <code>REBUILD</code> to confirm <input type="text" name="confirm" autocomplete="off" required></label><button class="danger" type="submit">Rebuild database</button></form></div>
<p style="color:var(--mut);font-size:.9rem">Server: <?= e($serverVer) ?> · SQL mode: <code><?= e($mode ?: 'default') ?></code></p>
<?php endif; ?>
<p style="color:var(--mut);font-size:.9rem">Errors are also saved to <code>logs/error.log</code> inside the site folder.</p>
</main></body></html>
