<?php
/** Local setup check. Only answers on localhost. Delete this file before publishing. */
require __DIR__ . '/includes/bootstrap.php';
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) { http_response_code(403); exit('Setup check is only available on localhost.'); }
page(['title' => 'Setup check', 'noindex' => true, 'nav' => '']);
$checks = [];
$checks[] = ['PHP 8.1 or newer', version_compare(PHP_VERSION, '8.1.0', '>='), PHP_VERSION];
foreach (['pdo_mysql', 'mbstring', 'json', 'session'] as $x) $checks[] = ["PHP extension: $x", extension_loaded($x), extension_loaded($x) ? 'loaded' : 'missing. Enable it in php.ini (Laragon: Menu > PHP > Extensions)'];
$pdo = db();
$checks[] = ['MySQL connection', (bool) $pdo, $pdo ? 'connected to "' . cfg('db.name') . '"' : ($GLOBALS['GALA_DB_ERROR'] ?? 'failed') . ' — start MySQL in Laragon and import database/schema.sql'];
if ($pdo) foreach (['feedback', 'admins', 'login_attempts'] as $t) { try { $pdo->query("SELECT 1 FROM `$t` LIMIT 1"); $ok = true; } catch (Throwable $x) { $ok = false; } $checks[] = ["Table: $t", $ok, $ok ? 'present' : 'missing. Import database/schema.sql']; }
$checks[] = ['Hero image', is_file(ROOT_PATH . '/' . cfg('hero_image')), cfg('hero_image')];
$checks[] = ['Listings loaded', count(all_places()) > 0, count(all_places()) . ' public listings'];
require ROOT_PATH . '/includes/header.php';
?>
<section class="pagehead pagehead--sea pagehead--short"><div class="wrap"><h1>Setup check</h1></div></section>
<section class="section section--tight"><div class="wrap">
<ul class="plain-list"><?php foreach ($checks as [$l, $ok, $d]): ?>
  <li><?= $ok ? '✅' : '❌' ?> <strong><?= e($l) ?></strong> <span class="muted">— <?= e($d) ?></span></li><?php endforeach; ?></ul>
<p class="muted">Delete <code>check.php</code> before you publish the site.</p>
</div></section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
