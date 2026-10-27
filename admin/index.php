<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require ROOT_PATH . '/includes/admin_lib.php';
page(['title' => 'Staff', 'path' => 'admin/', 'nav' => '', 'noindex' => true, 'class' => 'page-admin']);
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

$pdo = db();
$msg = flash();
$self = url('admin/');
$ip = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '0') . '|gala-admin');
$loopback = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$do = $isPost ? (string) ($_POST['do'] ?? '') : '';

function admin_user(): ?string { return $_SESSION['admin'] ?? null; }
function adm_redirect(string $to, ?array $m = null): never { if ($m) flash($m); redirect($to); }
/** Build a staff URL, dropping empty values and defaults. */
function adm_url(array $q = []): string
{
    global $self;
    $q = array_filter($q, fn($v) => $v !== '' && $v !== null && $v !== 0 && $v !== '0');
    if (($q['sort'] ?? '') === 'new') unset($q['sort']);
    if (($q['page'] ?? 0) == 1) unset($q['page']);
    return $self . ($q ? '?' . http_build_query($q) : '');
}
/** Where to go after an action: the page the staff member came from (whitelisted keys only). */
function adm_back(): string
{
    $q = []; parse_str((string) ($_POST['back'] ?? ''), $q);
    $ok = array_intersect_key($q, array_flip(['tab', 'status', 'kind', 'place', 'min', 'src', 'q', 'sort', 'page', 'cat', 'all', 'photo', 'ev', 'ver', 'etype', 'eq', 'edit', 'new']));
    return adm_url(array_map(fn($v) => is_array($v) ? '' : (string) $v, $ok));
}

$hasAdmin = false; $tab = 'dashboard';

if ($pdo) {
    $hasAdmin = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0;

    /* ---- first-run setup ---- */
    if (!$hasAdmin && $do === 'setup') {
        $key = cfg('setup_key', '');
        $u = trim((string) ($_POST['username'] ?? '')); $pw = (string) ($_POST['password'] ?? '');
        if (!csrf_ok()) adm_redirect($self, ['err' => 'Session expired. Try again.']);
        elseif ($key === '' && !$loopback) adm_redirect($self, ['err' => 'Set a setup key in config.local.php before creating an account on a public server.']);
        elseif ($key !== '' && !hash_equals($key, (string) ($_POST['setup_key'] ?? ''))) adm_redirect($self, ['err' => 'The setup key is not correct.']);
        elseif (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $u)) adm_redirect($self, ['err' => 'Username: 3 to 50 letters, numbers, dots, dashes or underscores.']);
        elseif (mb_strlen($pw) < 10) adm_redirect($self, ['err' => 'Use a password of at least 10 characters.']);
        $pdo->prepare('INSERT INTO admins (username, pass_hash) VALUES (?, ?)')->execute([$u, password_hash($pw, PASSWORD_DEFAULT)]);
        adm_redirect($self, ['ok' => 'Staff account created. Sign in below.']);
    }

    /* ---- login ---- */
    if ($hasAdmin && $do === 'login') {
        if (!csrf_ok()) adm_redirect($self, ['err' => 'Session expired. Try again.']);
        $st = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip_hash = ? AND attempted_at > (NOW() - INTERVAL 15 MINUTE)');
        $st->execute([$ip]);
        if ((int) $st->fetchColumn() >= 5) adm_redirect($self, ['err' => 'Too many failed attempts. Wait 15 minutes and try again.']);
        $st = $pdo->prepare('SELECT username, pass_hash FROM admins WHERE username = ?');
        $st->execute([trim((string) ($_POST['username'] ?? ''))]);
        $row = $st->fetch();
        if ($row && password_verify((string) ($_POST['password'] ?? ''), $row['pass_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin'] = $row['username']; $_SESSION['admin_at'] = time();
            $pdo->prepare('DELETE FROM login_attempts WHERE ip_hash = ?')->execute([$ip]);
            adm_redirect($self);
        }
        $pdo->prepare('INSERT INTO login_attempts (ip_hash) VALUES (?)')->execute([$ip]);
        adm_redirect($self, ['err' => 'Username or password is not correct.']);
    }
    if ($do === 'logout' && csrf_ok()) { $_SESSION = []; session_destroy(); redirect($self); }

    /* ---- idle timeout (2 hours) ---- */
    if (admin_user()) {
        if (time() - (int) ($_SESSION['admin_at'] ?? 0) > 7200) { unset($_SESSION['admin'], $_SESSION['admin_at']); adm_redirect($self, ['err' => 'You were signed out after a long break. Please sign in again.']); }
        $_SESSION['admin_at'] = time();
        // Make sure the account still exists (it may have been removed by another staff member).
        $st = $pdo->prepare('SELECT COUNT(*) FROM admins WHERE username = ?'); $st->execute([admin_user()]);
        if (!(int) $st->fetchColumn()) { $_SESSION = []; adm_redirect($self, ['err' => 'This staff account no longer exists.']); }
    }

    /* ---- actions for signed-in staff ---- */
    if (admin_user() && $isPost && $do !== '') {
        if (!csrf_ok()) adm_redirect(adm_back(), ['err' => 'Session expired. Reload the page and try again.']);
        $me = admin_user(); $back = adm_back();

        if ($do === 'moderate' || $do === 'bulk') {
            $act = (string) ($_POST['action'] ?? '');
            $ids = $do === 'bulk' ? array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])), fn($i) => $i > 0))) : [(int) ($_POST['id'] ?? 0)];
            $ids = array_filter($ids, fn($i) => $i > 0);
            if (!$ids) adm_redirect($back, ['err' => 'Select at least one item first.']);
            $in = implode(',', array_fill(0, count($ids), '?'));
            if (in_array($act, ADM_STATUSES, true)) {
                $st = $pdo->prepare("UPDATE feedback SET status = ?, moderated_at = NOW(), moderated_by = ? WHERE id IN ($in)");
                $st->execute(array_merge([$act, $me], $ids));
                adm_redirect($back, ['ok' => count($ids) === 1 ? 'Item #' . reset($ids) . ' marked ' . $act . '.' : $st->rowCount() . ' items marked ' . $act . '.']);
            } elseif ($act === 'drop_photo') {
                adm_delete_photos($pdo, "id IN ($in)", array_values($ids));
                $st = $pdo->prepare("UPDATE feedback SET photo = NULL WHERE id IN ($in)"); $st->execute(array_values($ids));
                adm_redirect($back, ['ok' => 'Photo removed. The rest of the feedback was kept.']);
            } elseif ($act === 'delete') {
                adm_delete_photos($pdo, "id IN ($in)", array_values($ids));
                $st = $pdo->prepare("DELETE FROM feedback WHERE id IN ($in)"); $st->execute(array_values($ids));
                adm_redirect($back, ['ok' => $st->rowCount() . ' item' . ($st->rowCount() === 1 ? '' : 's') . ' deleted.']);
            }
            adm_redirect($back, ['err' => 'Nothing was changed.']);
        }

        if ($do === 'add_entry') {
            $slug = trim((string) ($_POST['place'] ?? '')); $kind = (string) ($_POST['kind'] ?? 'Rating');
            $rating = (int) ($_POST['rating'] ?? 0); $comment = trim(preg_replace('/\s+/u', ' ', (string) ($_POST['comment'] ?? '')));
            $nick = trim((string) ($_POST['nickname'] ?? '')); $status = (string) ($_POST['status'] ?? 'approved');
            $date = trim((string) ($_POST['date'] ?? ''));
            $place = $slug !== '' ? place_by_slug($slug) : null; $err = [];
            if (!$place) $err[] = 'Choose a place.';
            if (!in_array($kind, FB_KINDS, true)) $kind = 'Rating';
            if (!in_array($status, ADM_STATUSES, true)) $status = 'approved';
            if ($kind === 'Rating' && ($rating < 1 || $rating > 5)) $err[] = 'Choose a rating from 1 to 5 stars.';
            if ($rating !== 0 && ($rating < 1 || $rating > 5)) $err[] = 'The rating must be between 1 and 5.';
            if ($kind !== 'Rating' && mb_strlen($comment) < 10) $err[] = 'Suggestions and concerns need at least 10 characters of comment.';
            if (mb_strlen($comment) > 600) $err[] = 'Keep the comment under 600 characters.';
            if (mb_strlen($nick) > 40) $err[] = 'Keep the nickname under 40 characters.';
            $created = date('Y-m-d H:i:s');
            if ($date !== '') {
                $d = DateTime::createFromFormat('Y-m-d', $date);
                if (!$d || $d->format('Y-m-d') !== $date || $date > date('Y-m-d')) $err[] = 'Use a valid date that is not in the future.';
                else $created = $date . ' ' . date('H:i:s');
            }
            $photoName = null;
            if (!$err && isset($_FILES['photo']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $ph = fb_save_photo($_FILES['photo']); if ($ph['ok']) $photoName = $ph['name']; else $err[] = $ph['error'];
            }
            if ($err) { fb_photo_delete($photoName); $_SESSION['adm_old'] = ['place' => $slug, 'kind' => $kind, 'rating' => $rating, 'comment' => $comment, 'nickname' => $nick, 'status' => $status, 'date' => $date]; adm_redirect(adm_url(['tab' => 'add']), ['err' => implode(' ', $err)]); }
            $pdo->prepare('INSERT INTO feedback (place_slug, place_name, kind, rating, comment, nickname, photo, source, status, visitor_hash, created_at, moderated_at, moderated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$slug, $place['name'], $kind, $rating ?: null, $comment !== '' ? $comment : null, $nick !== '' ? $nick : null, $photoName, 'staff', $status, hash('sha256', 'staff-' . $me . '-' . microtime(true)), $created, $status === 'pending' ? null : date('Y-m-d H:i:s'), $status === 'pending' ? null : $me]);
            adm_redirect(adm_url(['tab' => 'add']), ['ok' => 'Saved: ' . $kind . ' for ' . $place['name'] . ' (' . $status . '). You can add another below.']);
        }

        if ($do === 'seed') {
            $n = adm_seed_samples($pdo, 60);
            $ph = (int) $pdo->query("SELECT COUNT(*) FROM feedback WHERE source = 'sample' AND photo IS NOT NULL")->fetchColumn();
            adm_redirect(adm_url(), ['ok' => $n . ' sample entries were added' . ($ph ? ' (' . $ph . ' with photos)' : '') . '. Approved ones appear on public pages, including the home page, until you remove them under Tools.']);
        }
        if ($do === 'seed_events') adm_redirect(adm_url(['tab' => 'events']), ['ok' => db_seed_events($pdo, 'sample') . ' sample events were added. Edit, verify or delete them below.']);
        if ($do === 'unseed_events') adm_redirect(adm_url(['tab' => 'events']), ['ok' => adm_remove_sample_events($pdo) . ' sample events were removed. Your own events were kept.']);
        if ($do === 'unseed') adm_redirect(adm_url(['tab' => 'tools']), ['ok' => adm_remove_samples($pdo) . ' sample entries removed.']);
        if ($do === 'purge_rejected') {
            adm_delete_photos($pdo, "status = 'rejected'", []);
            $st = $pdo->prepare("DELETE FROM feedback WHERE status = 'rejected'"); $st->execute();
            adm_redirect(adm_url(['tab' => 'tools']), ['ok' => $st->rowCount() . ' rejected entries deleted.']);
        }

        if ($do === 'change_password') {
            $cur = (string) ($_POST['current'] ?? ''); $new = (string) ($_POST['new'] ?? ''); $new2 = (string) ($_POST['new2'] ?? '');
            $st = $pdo->prepare('SELECT pass_hash FROM admins WHERE username = ?'); $st->execute([$me]); $h = (string) $st->fetchColumn();
            if (!password_verify($cur, $h)) adm_redirect(adm_url(['tab' => 'staff']), ['err' => 'Your current password is not correct.']);
            if (mb_strlen($new) < 10) adm_redirect(adm_url(['tab' => 'staff']), ['err' => 'Use a new password of at least 10 characters.']);
            if (!hash_equals($new, $new2)) adm_redirect(adm_url(['tab' => 'staff']), ['err' => 'The two new passwords do not match.']);
            $pdo->prepare('UPDATE admins SET pass_hash = ? WHERE username = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $me]);
            session_regenerate_id(true);
            adm_redirect(adm_url(['tab' => 'staff']), ['ok' => 'Your password was changed.']);
        }
        if ($do === 'add_staff') {
            $u = trim((string) ($_POST['username'] ?? '')); $pw = (string) ($_POST['password'] ?? '');
            if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $u)) adm_redirect(adm_url(['tab' => 'staff']), ['err' => 'Username: 3 to 50 letters, numbers, dots, dashes or underscores.']);
            if (mb_strlen($pw) < 10) adm_redirect(adm_url(['tab' => 'staff']), ['err' => 'Use a password of at least 10 characters.']);
            $st = $pdo->prepare('SELECT COUNT(*) FROM admins WHERE username = ?'); $st->execute([$u]);
            if ((int) $st->fetchColumn()) adm_redirect(adm_url(['tab' => 'staff']), ['err' => 'That username is already taken.']);
            $pdo->prepare('INSERT INTO admins (username, pass_hash) VALUES (?, ?)')->execute([$u, password_hash($pw, PASSWORD_DEFAULT)]);
            adm_redirect(adm_url(['tab' => 'staff']), ['ok' => 'Staff account "' . $u . '" created.']);
        }
        if ($do === 'delete_staff') {
            $uid = (int) ($_POST['id'] ?? 0);
            $st = $pdo->prepare('SELECT username FROM admins WHERE id = ?'); $st->execute([$uid]); $un = $st->fetchColumn();
            if (!$un) adm_redirect(adm_url(['tab' => 'staff']), ['err' => 'That account was not found.']);
            if ($un === $me) adm_redirect(adm_url(['tab' => 'staff']), ['err' => 'You cannot remove the account you are signed in with.']);
            if ((int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn() <= 1) adm_redirect(adm_url(['tab' => 'staff']), ['err' => 'At least one staff account must remain.']);
            $pdo->prepare('DELETE FROM admins WHERE id = ?')->execute([$uid]);
            adm_redirect(adm_url(['tab' => 'staff']), ['ok' => 'Staff account "' . $un . '" removed.']);
        }
        /* ---- events ---- */
        if ($do === 'event_save') {
            $id = (int) ($_POST['id'] ?? 0);
            $title = trim(preg_replace('/\s+/u', ' ', (string) ($_POST['title'] ?? ''))); $type = (string) ($_POST['type'] ?? 'Event');
            $from = trim((string) ($_POST['starts_on'] ?? '')); $to = trim((string) ($_POST['ends_on'] ?? ''));
            $venue = trim((string) ($_POST['venue'] ?? '')); $org = trim((string) ($_POST['organizer'] ?? ''));
            $details = trim((string) ($_POST['details'] ?? '')); $link = trim((string) ($_POST['link'] ?? ''));
            $ver = !empty($_POST['verified']) ? 1 : 0; $pub = !empty($_POST['published']) ? 1 : 0; $err = [];
            if (mb_strlen($title) < 3 || mb_strlen($title) > 140) $err[] = 'The title needs 3 to 140 characters.';
            if (!in_array($type, ADM_EVENT_TYPES, true)) $err[] = 'Choose a type.';
            $dOk = fn($d) => ($x = DateTime::createFromFormat('Y-m-d', $d)) && $x->format('Y-m-d') === $d;
            if ($from !== '' && !$dOk($from)) $err[] = 'The start date is not valid.';
            if ($to !== '' && !$dOk($to)) $err[] = 'The end date is not valid.';
            if ($to !== '' && $from === '') $err[] = 'Add a start date before an end date, or clear the end date.';
            if ($from !== '' && $to !== '' && $to < $from) $err[] = 'The end date cannot be before the start date.';
            if (mb_strlen($venue) > 160 || mb_strlen($org) > 160) $err[] = 'Venue and organizer must be under 160 characters.';
            if (mb_strlen($details) > 900) $err[] = 'Keep the description under 900 characters.';
            if ($link !== '' && (mb_strlen($link) > 255 || !preg_match('~^https?://[^\s]+$~i', $link) || !filter_var($link, FILTER_VALIDATE_URL))) $err[] = 'The link must be a full web address starting with http:// or https://.';
            $dest = adm_url(['tab' => 'events'] + ($id ? ['edit' => $id] : ['new' => 1]));
            if ($err) { $_SESSION['adm_old_ev'] = compact('id', 'title', 'type', 'from', 'to', 'venue', 'org', 'details', 'link', 'ver', 'pub'); adm_redirect($dest, ['err' => implode(' ', $err)]); }
            if ($id) {
                $st = $pdo->prepare('UPDATE events SET title=?, type=?, starts_on=?, ends_on=?, venue=?, organizer=?, details=?, link=?, verified=?, published=?, updated_at=NOW(), updated_by=? WHERE id=?');
                $st->execute([$title, $type, $from ?: null, $to ?: null, $venue ?: null, $org ?: null, $details ?: null, $link ?: null, $ver, $pub, $me, $id]);
                adm_redirect(adm_url(['tab' => 'events']), ['ok' => 'Event “' . $title . '” was updated.']);
            }
            $pdo->prepare("INSERT INTO events (title, type, starts_on, ends_on, venue, organizer, details, link, verified, published, source, updated_at, updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,'staff',NOW(),?)")
                ->execute([$title, $type, $from ?: null, $to ?: null, $venue ?: null, $org ?: null, $details ?: null, $link ?: null, $ver, $pub, $me]);
            adm_redirect(adm_url(['tab' => 'events']), ['ok' => 'Event “' . $title . '” was added' . ($pub ? ' and is live on the Events page.' : ' (hidden from visitors).')]);
        }
        if ($do === 'event_toggle') {
            $id = (int) ($_POST['id'] ?? 0); $field = (string) ($_POST['field'] ?? '');
            if (!in_array($field, ['verified', 'published'], true) || $id < 1) adm_redirect($back, ['err' => 'Nothing was changed.']);
            $st = $pdo->prepare("UPDATE events SET $field = 1 - $field, updated_at = NOW(), updated_by = ? WHERE id = ?"); $st->execute([$me, $id]);
            $row = $pdo->prepare("SELECT title, $field v FROM events WHERE id = ?"); $row->execute([$id]); $r = $row->fetch();
            if (!$r) adm_redirect($back, ['err' => 'That event was not found.']);
            adm_redirect($back, ['ok' => '“' . $r['title'] . '” is now ' . ($field === 'verified' ? ($r['v'] ? 'marked as verified.' : 'marked as not verified.') : ($r['v'] ? 'visible to visitors.' : 'hidden from visitors.'))]);
        }
        if ($do === 'event_delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $st = $pdo->prepare('SELECT title FROM events WHERE id = ?'); $st->execute([$id]); $t = $st->fetchColumn();
            if ($t === false) adm_redirect($back, ['err' => 'That event was not found.']);
            $pdo->prepare('DELETE FROM events WHERE id = ?')->execute([$id]);
            adm_redirect(adm_url(['tab' => 'events']), ['ok' => 'Event “' . $t . '” was deleted.']);
        }
        adm_redirect($back, ['err' => 'That action was not recognised.']);
    }

    /* ---- CSV export (honours the current filters) ---- */
    if (admin_user() && isset($_GET['export'])) {
        $f = adm_filters($_GET); [$wsql, $wp] = adm_where($f);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="gala-feedback-' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['id', 'created_at', 'place', 'kind', 'rating', 'comment', 'nickname', 'status', 'source', 'photo'], ',', '"', '\\');
        $safe = fn($v) => is_string($v) && $v !== '' && strpos("=+-@\t\r", $v[0]) !== false ? "'" . $v : $v;     // block spreadsheet formula injection
        $st = $pdo->prepare("SELECT id, created_at, place_name, kind, rating, comment, nickname, status, source, photo FROM feedback $wsql ORDER BY " . adm_order($f['sort'])); $st->execute($wp);
        foreach ($st as $r) fputcsv($out, array_map($safe, array_values($r)), ',', '"', '\\');
        exit;
    }

    /* ---- which tab ---- */
    $tab = (string) ($_GET['tab'] ?? 'dashboard');
    if (in_array($tab, ADM_STATUSES, true)) { $_GET['status'] = $tab; $tab = 'feedback'; }       // old links: ?tab=pending
    if ($tab === 'summary') $tab = 'places';
    if (!in_array($tab, ['dashboard', 'feedback', 'places', 'add', 'events', 'content', 'tools', 'staff'], true)) $tab = 'dashboard';
}

require ROOT_PATH . '/includes/header.php';
?>
<link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
<section class="pagehead pagehead--sea pagehead--short"><div class="wrap"><h1>Staff area</h1><p class="pagehead__lede">Moderate feedback, watch the ratings and keep your content in order.</p></div></section>
<section class="section section--tight adm"><div class="wrap">
<?php if ($msg): ?><div class="notice <?= isset($msg['err']) ? 'notice--err' : 'notice--ok' ?>" role="<?= isset($msg['err']) ? 'alert' : 'status' ?>"><?= icon(isset($msg['err']) ? 'alert' : 'check', 22) ?><div><?= e($msg['err'] ?? $msg['ok'] ?? '') ?></div></div><?php endif; ?>

<?php if (!$pdo): ?>
  <div class="notice notice--err" role="alert"><?= icon('alert', 22) ?><div><strong>The database is not reachable.</strong>
    <?= !empty($GLOBALS['GALA_DB_ERROR']) ? '<br><code>' . e($GLOBALS['GALA_DB_ERROR']) . '</code>' : '' ?><br>
    Start MySQL (in Laragon press <em>Start All</em>) and check the <code>db</code> settings in <code>config/config.php</code> or <code>config/config.local.php</code>. The database and its tables are created automatically once MySQL is running.<br><a href="<?= e(url('admin/repair.php')) ?>">Open the database checkup and repair tool</a></div></div>

<?php elseif (!$hasAdmin): ?>
  <div class="adm-auth">
    <h2 class="h-sub">Create the first staff account</h2>
    <?php if (cfg('setup_key', '') === '' && !$loopback): ?><p class="notice notice--err">Account creation from another computer is blocked until you set <code>setup_key</code> in <code>config/config.local.php</code>.</p><?php endif; ?>
    <form class="fbform" method="post" action="<?= e($self) ?>" autocomplete="off">
      <?= csrf_field() ?><input type="hidden" name="do" value="setup">
      <div class="field"><label for="u">Username</label><input id="u" name="username" required minlength="3" maxlength="50" pattern="[A-Za-z0-9_.\-]{3,50}"></div>
      <div class="field"><label for="p">Password <small>(at least 10 characters)</small></label><input id="p" type="password" name="password" required minlength="10" autocomplete="new-password"></div>
      <?php if (cfg('setup_key', '') !== ''): ?><div class="field"><label for="k">Setup key</label><input id="k" type="password" name="setup_key" required></div><?php endif; ?>
      <button class="btn btn--ink" type="submit">Create account</button>
    </form>
  </div>

<?php elseif (!admin_user()): ?>
  <div class="adm-auth">
    <h2 class="h-sub">Sign in</h2>
    <form class="fbform" method="post" action="<?= e($self) ?>">
      <?= csrf_field() ?><input type="hidden" name="do" value="login">
      <div class="field"><label for="u">Username</label><input id="u" name="username" required autocomplete="username" autofocus></div>
      <div class="field"><label for="p">Password</label><input id="p" type="password" name="password" required autocomplete="current-password"></div>
      <button class="btn btn--ink" type="submit">Sign in</button>
    </form>
  </div>

<?php else:
    $st_counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
    foreach ($pdo->query('SELECT status, COUNT(*) n FROM feedback GROUP BY status') as $r) $st_counts[$r['status']] = (int) $r['n'];
    $evAll = adm_events(); $evC = adm_event_counts($evAll);
    $tabs = ['dashboard' => 'Dashboard', 'feedback' => 'Feedback', 'places' => 'Ratings by place', 'add' => 'Add entry', 'events' => 'Events', 'content' => 'Content', 'tools' => 'Tools', 'staff' => 'Staff'];
?>
  <div class="admin-bar">
    <nav class="tabs" aria-label="Staff sections">
      <?php foreach ($tabs as $k => $l): ?>
        <a href="<?= e(adm_url($k === 'dashboard' ? [] : ['tab' => $k])) ?>" <?= $tab === $k ? 'aria-current="page"' : '' ?>><?= e($l) ?><?php if ($k === 'feedback' && $st_counts['pending']): ?> <span class="badge-n" title="Waiting for review"><?= $st_counts['pending'] ?></span><?php endif; ?><?php if ($k === 'events' && $evC['unverified']): ?> <span class="badge-n" title="Not yet verified"><?= $evC['unverified'] ?></span><?php endif; ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="admin-bar__r">
      <form method="post" action="<?= e($self) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="do" value="logout"><button class="btn btn--line btn--sm" type="submit">Sign out (<?= e(admin_user()) ?>)</button></form>
    </div>
  </div>

<?php /* ================================================= DASHBOARD */ if ($tab === 'dashboard'):
    $S = adm_stats($pdo); $dmax = max(1, max($S['dist'])); ?>
  <?php if ($S['total'] === 0): ?>
  <div class="adm-hero">
    <div><h2>Welcome. There is no feedback yet.</h2><p>Visitors’ ratings and comments will show up here for review. To see how the dashboard looks with real-looking numbers, load some clearly-labelled sample data (you can remove it with one click), or add an entry yourself.</p></div>
    <div class="adm-hero__btns">
      <form method="post" action="<?= e($self) ?>"><?= csrf_field() ?><input type="hidden" name="do" value="seed"><button class="btn btn--sun btn--lg" type="submit">Load 60 sample entries</button></form>
      <a class="btn btn--line btn--lg" href="<?= e(adm_url(['tab' => 'add'])) ?>">Add an entry</a>
    </div>
  </div>
  <?php endif; ?>

  <div class="adm-kpis">
    <a class="adm-kpi" href="<?= e(adm_url(['tab' => 'feedback'])) ?>"><span class="adm-kpi__l">Total feedback</span><strong><?= $S['total'] ?></strong><small><?= $S['week'] ?> in the last 7 days</small></a>
    <a class="adm-kpi adm-kpi--warn" href="<?= e(adm_url(['tab' => 'feedback', 'status' => 'pending'])) ?>"><span class="adm-kpi__l">Waiting for review</span><strong><?= $S['status']['pending'] ?></strong><small><?= $S['status']['pending'] ? 'Review these first' : 'All caught up' ?></small></a>
    <div class="adm-kpi adm-kpi--star"><span class="adm-kpi__l">Average rating</span><strong><?= $S['avg'] !== null ? number_format($S['avg'], 2) : '–' ?></strong><small><?= $S['avg'] !== null ? adm_stars($S['avg'], false) : 'No approved ratings yet' ?></small></div>
    <a class="adm-kpi" href="<?= e(adm_url(['tab' => 'feedback', 'status' => 'approved', 'kind' => 'Rating'])) ?>"><span class="adm-kpi__l">Approved ratings</span><strong><?= $S['rated'] ?></strong><small><?= $S['status']['approved'] ?> approved in total</small></a>
  </div>

  <div class="adm-grid">
    <section class="adm-panel" aria-labelledby="d-dist">
      <h2 id="d-dist">Rating breakdown</h2>
      <?php if (!$S['rated']): ?><p class="muted">No approved ratings yet.</p><?php else: ?>
      <ul class="adm-dist"><?php for ($i = 5; $i >= 1; $i--): $pc = $S['rated'] ? round($S['dist'][$i] * 100 / $S['rated']) : 0; ?>
        <li><span class="adm-dist__k"><?= $i ?> ★</span><span class="adm-track"><i style="width:<?= round($S['dist'][$i] * 100 / $dmax) ?>%"></i></span><span class="adm-dist__v"><?= $S['dist'][$i] ?> <small>(<?= $pc ?>%)</small></span></li>
      <?php endfor; ?></ul><?php endif; ?>
    </section>

    <section class="adm-panel" aria-labelledby="d-trend">
      <h2 id="d-trend">Submissions, last 14 days</h2>
      <?= adm_trend_svg($S['trend']) ?>
    </section>

    <section class="adm-panel" aria-labelledby="d-cat">
      <h2 id="d-cat">Average rating by category</h2>
      <ul class="adm-dist"><?php foreach ($S['cats'] as $c): ?>
        <li><span class="adm-dist__k adm-dist__k--w"><?= e($c['label']) ?></span><span class="adm-track"><i class="adm-track--sun" style="width:<?= $c['avg'] ? round($c['avg'] * 20) : 0 ?>%"></i></span><span class="adm-dist__v"><?= $c['avg'] !== null ? number_format($c['avg'], 2) : '–' ?> <small>(<?= $c['n'] ?>)</small></span></li>
      <?php endforeach; ?></ul>
    </section>

    <section class="adm-panel" aria-labelledby="d-kind">
      <h2 id="d-kind">What visitors send</h2>
      <ul class="adm-chips">
        <?php foreach ($S['kind'] as $k => $n): ?><li><a href="<?= e(adm_url(['tab' => 'feedback', 'kind' => $k])) ?>"><strong><?= $n ?></strong> <?= e($k === 'Rating' ? 'Ratings' : $k . 's') ?></a></li><?php endforeach; ?>
        <?php foreach (['approved' => 'Approved', 'rejected' => 'Rejected'] as $k => $l): ?><li><a href="<?= e(adm_url(['tab' => 'feedback', 'status' => $k])) ?>"><strong><?= $S['status'][$k] ?></strong> <?= $l ?></a></li><?php endforeach; ?>
        <li><a href="<?= e(adm_url(['tab' => 'feedback', 'photo' => 1])) ?>"><strong><?= $S['photos'] ?></strong> With a photo</a></li>
      </ul>
    </section>

    <?php
      $ranked = array_filter($S['places'], fn($r) => $r['n'] > 0);
      $g = $S['avg'] ?? 4.0;      // rank with a weighted score so one lucky 5-star rating does not beat many good ones
      foreach ($ranked as $k => $r) $ranked[$k]['score'] = ($r['n'] * $r['avg'] + 2 * $g) / ($r['n'] + 2);
      uasort($ranked, fn($a, $b) => [$b['score'], $b['n']] <=> [$a['score'], $a['n']]);
      $top = array_slice($ranked, 0, 5, true);
      $low = array_slice(array_reverse($ranked, true), 0, 3, true);
      $conc = array_filter($S['places'], fn($r) => (int) $r['concerns'] > 0); uasort($conc, fn($a, $b) => $b['concerns'] <=> $a['concerns']); $conc = array_slice($conc, 0, 4, true);
    ?>
    <section class="adm-panel" aria-labelledby="d-top">
      <h2 id="d-top">Top rated places</h2><p class="muted small adm-note">Ranked by average, weighted by how many ratings a place has.</p>
      <?php if (!$top): ?><p class="muted">Approve some ratings to see rankings.</p><?php else: ?>
      <ol class="adm-rank"><?php foreach ($top as $r): ?>
        <li><a href="<?= e(adm_url(['tab' => 'feedback', 'place' => $r['place_slug']])) ?>"><span class="adm-rank__n"><?= e($r['place_name']) ?></span><span class="adm-rank__v"><?= adm_stars((float) $r['avg']) ?> <small><?= (int) $r['n'] ?> rating<?= (int) $r['n'] === 1 ? '' : 's' ?></small></span></a></li>
      <?php endforeach; ?></ol><?php endif; ?>
    </section>

    <section class="adm-panel" aria-labelledby="d-low">
      <h2 id="d-low">Needs attention</h2>
      <?php if (!$low && !$conc): ?><p class="muted">Nothing to flag yet.</p><?php else: ?>
      <?php if ($low): ?><h3>Lowest rated</h3><ol class="adm-rank"><?php foreach ($low as $r): ?>
        <li><a href="<?= e(adm_url(['tab' => 'feedback', 'place' => $r['place_slug']])) ?>"><span class="adm-rank__n"><?= e($r['place_name']) ?></span><span class="adm-rank__v"><?= adm_stars((float) $r['avg']) ?> <small><?= (int) $r['n'] ?></small></span></a></li>
      <?php endforeach; ?></ol><?php endif; ?>
      <?php if ($conc): ?><h3>Most concerns reported</h3><ol class="adm-rank"><?php foreach ($conc as $r): ?>
        <li><a href="<?= e(adm_url(['tab' => 'feedback', 'place' => $r['place_slug'], 'kind' => 'Concern'])) ?>"><span class="adm-rank__n"><?= e($r['place_name']) ?></span><span class="adm-rank__v"><span class="adm-badge adm-badge--rejected"><?= (int) $r['concerns'] ?> concern<?= (int) $r['concerns'] === 1 ? '' : 's' ?></span></span></a></li>
      <?php endforeach; ?></ol><?php endif; ?><?php endif; ?>
    </section>

    <section class="adm-panel adm-panel--wide" aria-labelledby="d-events">
      <div class="adm-panel__head"><h2 id="d-events">Events</h2><a class="textlink" href="<?= e(adm_url(['tab' => 'events'])) ?>">Manage events <?= icon('arrow', 16) ?></a></div>
      <ul class="adm-chips">
        <li><a href="<?= e(adm_url(['tab' => 'events'])) ?>"><strong><?= $evC['total'] ?></strong> Total</a></li>
        <li><a href="<?= e(adm_url(['tab' => 'events', 'ev' => 'upcoming'])) ?>"><strong><?= $evC['live'] ?></strong> Upcoming or ongoing</a></li>
        <li><a href="<?= e(adm_url(['tab' => 'events', 'ver' => 'no'])) ?>"><strong><?= $evC['unverified'] ?></strong> Not verified</a></li>
        <li><a href="<?= e(adm_url(['tab' => 'events', 'ev' => 'hidden'])) ?>"><strong><?= $evC['hidden'] ?></strong> Hidden</a></li>
      </ul>
    </section>

    <section class="adm-panel adm-panel--wide" aria-labelledby="d-recent">
      <div class="adm-panel__head"><h2 id="d-recent">Latest activity</h2><a class="textlink" href="<?= e(adm_url(['tab' => 'feedback'])) ?>">See all feedback <?= icon('arrow', 16) ?></a></div>
      <?php if (!$S['recent']): ?><p class="muted">Nothing yet.</p><?php else: ?>
      <ul class="adm-feed"><?php foreach ($S['recent'] as $r): ?>
        <li>
          <div class="adm-feed__top"><strong><?= e($r['place_name']) ?></strong> <span class="vtag"><?= e($r['kind']) ?></span> <?= $r['rating'] ? adm_stars((float) $r['rating'], false) : '' ?> <?= adm_status_badge($r['status']) ?><?= $r['source'] !== 'visitor' ? ' <span class="adm-badge adm-badge--src">' . e(ADM_SOURCES[$r['source']] ?? $r['source']) . '</span>' : '' ?><time class="muted small" datetime="<?= e($r['created_at']) ?>"><?= e(adm_when($r['created_at'])) ?></time></div>
          <p><?= $r['comment'] ? e($r['comment']) : '<em class="muted">Rating only, no comment.</em>' ?><?= $r['nickname'] ? ' <span class="muted">— ' . e($r['nickname']) . '</span>' : '' ?></p>
        </li>
      <?php endforeach; ?></ul><?php endif; ?>
    </section>
  </div>

<?php /* ================================================= FEEDBACK LIST */ elseif ($tab === 'feedback'):
    $f = adm_filters($_GET); [$wsql, $wp] = adm_where($f);
    $per = 15;
    $st = $pdo->prepare("SELECT COUNT(*) FROM feedback $wsql"); $st->execute($wp); $total = (int) $st->fetchColumn();
    $pages = max(1, (int) ceil($total / $per)); $f['page'] = min($f['page'], $pages);
    $st = $pdo->prepare("SELECT * FROM feedback $wsql ORDER BY " . adm_order($f['sort']) . ' LIMIT ' . $per . ' OFFSET ' . (($f['page'] - 1) * $per)); $st->execute($wp); $rows = fb_fix_names($st->fetchAll());
    $base = array_merge(['tab' => 'feedback'], $f); $backq = http_build_query(array_filter($base, fn($v) => $v !== '' && $v !== 0));
    $pl = adm_places();
?>
  <div class="adm-chipbar" role="group" aria-label="Filter by status">
    <a class="adm-pill<?= $f['status'] === '' ? ' is-on' : '' ?>" href="<?= e(adm_url(array_merge($base, ['status' => '', 'page' => 1]))) ?>">All <b><?= array_sum($st_counts) ?></b></a>
    <?php foreach (ADM_STATUSES as $s): ?><a class="adm-pill adm-pill--<?= $s ?><?= $f['status'] === $s ? ' is-on' : '' ?>" href="<?= e(adm_url(array_merge($base, ['status' => $s, 'page' => 1]))) ?>"><?= ucfirst($s) ?> <b><?= $st_counts[$s] ?></b></a><?php endforeach; ?>
  </div>

  <form class="adm-filters" method="get" action="<?= e($self) ?>">
    <input type="hidden" name="tab" value="feedback"><input type="hidden" name="status" value="<?= e($f['status']) ?>">
    <div class="field"><label for="f-q">Search</label><input id="f-q" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Comment, place or nickname"></div>
    <div class="field"><label for="f-place">Place</label><select id="f-place" name="place"><option value="">All places</option><?php foreach ($pl as $sl => $p): ?><option value="<?= e($sl) ?>"<?= $f['place'] === $sl ? ' selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="f-kind">Type</label><select id="f-kind" name="kind"><option value="">All types</option><?php foreach (FB_KINDS as $k): ?><option<?= $f['kind'] === $k ? ' selected' : '' ?>><?= e($k) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="f-min">Rating</label><select id="f-min" name="min"><option value="">Any</option><?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"<?= $f['min'] === $i ? ' selected' : '' ?>><?= $i ?>+ stars</option><?php endfor; ?></select></div>
    <div class="field"><label for="f-src">Source</label><select id="f-src" name="src"><option value="">Any</option><?php foreach (ADM_SOURCES as $k => $l): ?><option value="<?= $k ?>"<?= $f['src'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="f-photo">Photo</label><select id="f-photo" name="photo"><option value="">Any</option><option value="1"<?= $f['photo'] ? ' selected' : '' ?>>With a photo</option></select></div>
    <div class="field"><label for="f-sort">Sort</label><select id="f-sort" name="sort"><?php foreach (['new' => 'Newest first', 'old' => 'Oldest first', 'high' => 'Highest rating', 'low' => 'Lowest rating'] as $k => $l): ?><option value="<?= $k ?>"<?= $f['sort'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
    <div class="adm-filters__go"><button class="btn btn--ink btn--sm" type="submit">Apply</button><a class="btn btn--line btn--sm" href="<?= e(adm_url(['tab' => 'feedback'])) ?>">Reset</a><a class="btn btn--line btn--sm" href="<?= e(adm_url(array_merge($base, ['export' => 1, 'tab' => '']))) ?>">Export CSV</a></div>
  </form>

  <p class="muted adm-count"><?= $total ?> result<?= $total === 1 ? '' : 's' ?><?= $pages > 1 ? ' · page ' . $f['page'] . ' of ' . $pages : '' ?></p>

  <?php if (!$rows): ?>
    <div class="empty-state empty-state--left"><p>No feedback matches these filters.</p></div>
  <?php else: ?>
  <form method="post" action="<?= e($self) ?>" class="adm-bulkform" id="bulk">
    <?= csrf_field() ?><input type="hidden" name="do" value="bulk"><input type="hidden" name="back" value="<?= e($backq) ?>">
    <div class="adm-bulk">
      <label class="adm-check"><input type="checkbox" data-select-all> <span>Select all on this page</span></label>
      <span class="adm-bulk__btns">
        <button class="btn btn--ink btn--sm" name="action" value="approved">Approve selected</button>
        <button class="btn btn--line btn--sm" name="action" value="rejected">Reject</button>
        <button class="btn btn--line btn--sm" name="action" value="pending">Back to pending</button>
        <button class="btn btn--line btn--sm btn--danger" name="action" value="delete" data-confirm="Delete the selected feedback permanently?">Delete</button>
      </span>
    </div>
    <ul class="modlist">
    <?php foreach ($rows as $r): ?>
      <li class="mod mod--<?= e($r['status']) ?>">
        <div class="mod__head">
          <label class="adm-check"><input type="checkbox" name="ids[]" value="<?= (int) $r['id'] ?>"><span class="visually-hidden">Select #<?= (int) $r['id'] ?></span></label>
          <strong><?= e($r['place_name']) ?></strong> <span class="vtag"><?= e($r['kind']) ?></span>
          <?= $r['rating'] ? adm_stars((float) $r['rating'], false) : '' ?> <?= adm_status_badge($r['status']) ?>
          <?= $r['source'] !== 'visitor' ? '<span class="adm-badge adm-badge--src">' . e(ADM_SOURCES[$r['source']] ?? $r['source']) . '</span>' : '' ?>
          <time class="muted small" datetime="<?= e($r['created_at']) ?>" title="<?= e($r['created_at']) ?>"><?= e(adm_when($r['created_at'])) ?></time>
        </div>
        <?php if (!empty($r['photo']) && fb_photo_url($r['photo'])): ?><a class="adm-thumb" href="<?= e(fb_photo_url($r['photo'])) ?>" target="_blank" rel="noopener"><img src="<?= e(fb_photo_url($r['photo'])) ?>" alt="Photo shared with this feedback" loading="lazy" width="220" height="150"></a><?php endif; ?>
        <p class="mod__text"><?= $r['comment'] ? e($r['comment']) : '<em class="muted">No comment, rating only.</em>' ?></p>
        <p class="muted small"><?= !empty($r['photo']) ? 'Has a photo · ' : '' ?>Nickname: <?= e($r['nickname'] ?: 'none') ?> · #<?= (int) $r['id'] ?><?= $r['moderated_by'] ? ' · moderated by ' . e($r['moderated_by']) . ' ' . e(adm_when((string) $r['moderated_at'])) : '' ?></p>
        <div class="mod__actions">
          <?php if ($r['status'] !== 'approved'): ?><button class="btn btn--ink btn--sm" type="submit" form="one-<?= (int) $r['id'] ?>" name="action" value="approved">Approve</button><?php endif; ?>
          <?php if ($r['status'] !== 'rejected'): ?><button class="btn btn--line btn--sm" type="submit" form="one-<?= (int) $r['id'] ?>" name="action" value="rejected">Reject</button><?php endif; ?>
          <?php if ($r['status'] !== 'pending'): ?><button class="btn btn--line btn--sm" type="submit" form="one-<?= (int) $r['id'] ?>" name="action" value="pending">Back to pending</button><?php endif; ?>
          <?php if (!empty($r['photo'])): ?><button class="btn btn--line btn--sm" type="submit" form="one-<?= (int) $r['id'] ?>" name="action" value="drop_photo" data-confirm="Remove only the photo and keep the comment?">Remove photo</button><?php endif; ?>
          <button class="btn btn--line btn--sm btn--danger" type="submit" form="one-<?= (int) $r['id'] ?>" name="action" value="delete" data-confirm="Delete this feedback permanently?">Delete</button>
        </div>
      </li>
    <?php endforeach; ?>
    </ul>
  </form>
  <?php foreach ($rows as $r): ?>
  <form method="post" action="<?= e($self) ?>" id="one-<?= (int) $r['id'] ?>" hidden><?= csrf_field() ?><input type="hidden" name="do" value="moderate"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="back" value="<?= e($backq) ?>"></form>
  <?php endforeach; ?>

  <?php if ($pages > 1): ?>
  <nav class="adm-pager" aria-label="Pages">
    <?php if ($f['page'] > 1): ?><a class="btn btn--line btn--sm" href="<?= e(adm_url(array_merge($base, ['page' => $f['page'] - 1]))) ?>">← Newer</a><?php endif; ?>
    <?php for ($i = max(1, $f['page'] - 2); $i <= min($pages, $f['page'] + 2); $i++): ?><a class="adm-pill<?= $i === $f['page'] ? ' is-on' : '' ?>" href="<?= e(adm_url(array_merge($base, ['page' => $i]))) ?>" <?= $i === $f['page'] ? 'aria-current="page"' : '' ?>><?= $i ?></a><?php endfor; ?>
    <?php if ($f['page'] < $pages): ?><a class="btn btn--line btn--sm" href="<?= e(adm_url(array_merge($base, ['page' => $f['page'] + 1]))) ?>">Older →</a><?php endif; ?>
  </nav>
  <?php endif; endif; ?>

<?php /* ================================================= RATINGS BY PLACE */ elseif ($tab === 'places'):
    $S = adm_stats($pdo); $pl = adm_places(); $catF = (string) ($_GET['cat'] ?? ''); $all = !empty($_GET['all']); $sort = in_array($_GET['sort'] ?? '', ['avg', 'count', 'name', 'pending'], true) ? $_GET['sort'] : 'avg';
    $list = [];
    foreach ($pl as $sl => $p) {
        if ($catF !== '' && $p['cat'] !== $catF) continue;
        $r = $S['places'][$sl] ?? null;
        if (!$r && !$all) continue;
        $list[] = ['slug' => $sl, 'name' => $p['name'], 'cat' => $p['cat'], 'n' => (int) ($r['n'] ?? 0), 'avg' => $r['avg'] ?? null, 'total' => (int) ($r['total'] ?? 0), 'pend' => (int) ($r['pend'] ?? 0), 'concerns' => (int) ($r['concerns'] ?? 0), 'sugg' => (int) ($r['sugg'] ?? 0), 'd' => [1 => (int) ($r['r1'] ?? 0), 2 => (int) ($r['r2'] ?? 0), 3 => (int) ($r['r3'] ?? 0), 4 => (int) ($r['r4'] ?? 0), 5 => (int) ($r['r5'] ?? 0)]];
    }
    usort($list, fn($a, $b) => match ($sort) {
        'name' => strcasecmp($a['name'], $b['name']),
        'count' => [$b['n'], $b['avg']] <=> [$a['n'], $a['avg']],
        'pending' => [$b['pend'], $b['total']] <=> [$a['pend'], $a['total']],
        default => [$b['avg'] ?? -1, $b['n']] <=> [$a['avg'] ?? -1, $a['n']],
    });
    $q = ['tab' => 'places', 'cat' => $catF, 'all' => $all ? 1 : '', 'sort' => $sort];
?>
  <div class="adm-head"><div><h2 class="h-sub">Ratings by place</h2><p class="muted">Averages use approved ratings only. Click a place to see its feedback.</p></div></div>
  <form class="adm-filters adm-filters--inline" method="get" action="<?= e($self) ?>">
    <input type="hidden" name="tab" value="places">
    <div class="field"><label for="p-cat">Category</label><select id="p-cat" name="cat"><option value="">All categories</option><?php foreach (categories() as $k => $c): ?><option value="<?= e($k) ?>"<?= $catF === $k ? ' selected' : '' ?>><?= e($c['label']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="p-sort">Sort by</label><select id="p-sort" name="sort"><?php foreach (['avg' => 'Highest average', 'count' => 'Most ratings', 'pending' => 'Most waiting for review', 'name' => 'Name A–Z'] as $k => $l): ?><option value="<?= $k ?>"<?= $sort === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
    <label class="adm-check adm-check--box"><input type="checkbox" name="all" value="1"<?= $all ? ' checked' : '' ?>> <span>Include places with no feedback</span></label>
    <div class="adm-filters__go"><button class="btn btn--ink btn--sm" type="submit">Apply</button></div>
  </form>
  <?php if (!$list): ?><div class="empty-state empty-state--left"><p>No feedback has been received for these places yet.</p></div><?php else: ?>
  <div class="tablewrap"><table class="datatable adm-table"><thead><tr><th scope="col">Place</th><th scope="col">Average</th><th scope="col">Ratings</th><th scope="col" class="adm-th-dist">Distribution (1★ to 5★)</th><th scope="col">Suggestions</th><th scope="col">Concerns</th><th scope="col">Pending</th></tr></thead><tbody>
  <?php foreach ($list as $r): $mx = max(1, max($r['d'])); ?>
    <tr>
      <th scope="row"><a href="<?= e(adm_url(['tab' => 'feedback', 'place' => $r['slug']])) ?>"><?= e($r['name']) ?></a><small class="muted"><?= e(categories()[$r['cat']]['single'] ?? '') ?></small></th>
      <td><?= adm_stars($r['avg'] !== null ? (float) $r['avg'] : null) ?></td>
      <td><?= $r['n'] ?></td>
      <td><span class="adm-mini" aria-hidden="true"><?php for ($i = 1; $i <= 5; $i++): ?><i style="height:<?= $r['n'] ? max(6, round($r['d'][$i] * 100 / $mx)) : 6 ?>%" title="<?= $i ?> star: <?= $r['d'][$i] ?>"></i><?php endfor; ?></span></td>
      <td><?= $r['sugg'] ?></td><td><?= $r['concerns'] ? '<span class="adm-badge adm-badge--rejected">' . $r['concerns'] . '</span>' : '0' ?></td><td><?= $r['pend'] ? '<span class="adm-badge adm-badge--pending">' . $r['pend'] . '</span>' : '0' ?></td>
    </tr>
  <?php endforeach; ?></tbody></table></div><?php endif; ?>

<?php /* ================================================= ADD ENTRY */ elseif ($tab === 'add'):
    $old = $_SESSION['adm_old'] ?? []; unset($_SESSION['adm_old']);
    $v = fn($k, $d = '') => $old[$k] ?? $d; $groups = [];
    foreach (all_places() as $p) $groups[$p['cat']][] = $p; ?>
  <div class="adm-head"><div><h2 class="h-sub">Add an entry</h2><p class="muted">Record a rating, suggestion or concern yourself, for example from a paper survey or a message you received. Entries are marked “Staff entry”.</p></div></div>
  <form class="adm-form" method="post" action="<?= e($self) ?>" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?><input type="hidden" name="do" value="add_entry"><input type="hidden" name="back" value="tab=add">
    <div class="field"><label for="a-place">Place</label>
      <select id="a-place" name="place" required><option value="">Choose a place…</option>
        <?php foreach ($groups as $cat => $ps): ?><optgroup label="<?= e(categories()[$cat]['label'] ?? $cat) ?>"><?php foreach ($ps as $p): ?><option value="<?= e($p['slug']) ?>"<?= $v('place') === $p['slug'] ? ' selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?>
      </select></div>
    <div class="adm-form__row">
      <div class="field"><label for="a-kind">Type</label><select id="a-kind" name="kind"><?php foreach (FB_KINDS as $k): ?><option<?= $v('kind', 'Rating') === $k ? ' selected' : '' ?>><?= e($k) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="a-status">Status</label><select id="a-status" name="status"><?php foreach (ADM_STATUSES as $s): ?><option value="<?= $s ?>"<?= $v('status', 'approved') === $s ? ' selected' : '' ?>><?= ucfirst($s) ?><?= $s === 'approved' ? ' (shown publicly)' : '' ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="a-date">Date <small>(optional, today if empty)</small></label><input id="a-date" type="date" name="date" max="<?= date('Y-m-d') ?>" value="<?= e($v('date')) ?>"></div>
    </div>
    <fieldset class="field"><legend>Rating <small>(required for ratings)</small></legend>
      <div class="starpick" role="radiogroup" aria-label="Rating from 1 to 5 stars">
        <?php for ($i = 5; $i >= 1; $i--): ?><input type="radio" id="st<?= $i ?>" name="rating" value="<?= $i ?>"<?= (int) $v('rating', 0) === $i ? ' checked' : '' ?>><label for="st<?= $i ?>" title="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>"><span class="visually-hidden"><?= $i ?> star<?= $i > 1 ? 's' : '' ?></span>★</label><?php endfor; ?>
      </div></fieldset>
    <div class="field"><label for="a-comment">Comment <small>(max 600 characters)</small></label><textarea id="a-comment" name="comment" rows="4" maxlength="600"><?= e($v('comment')) ?></textarea></div>
    <?php if (fb_photo_supported()): ?><div class="field"><label for="a-photo">Photo <small>(optional, JPG, PNG or WebP up to <?= e(fb_photo_limit_label()) ?>)</small></label><input id="a-photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp"></div>
    <?php else: ?><p class="muted small">Photo upload needs the PHP <code>gd</code> extension. Enable it in php.ini (Laragon: Menu → PHP → Extensions → gd).</p><?php endif; ?>
    <div class="field"><label for="a-nick">Nickname <small>(optional)</small></label><input id="a-nick" name="nickname" maxlength="40" value="<?= e($v('nickname')) ?>"></div>
    <button class="btn btn--ink btn--lg" type="submit">Save entry</button>
  </form>

<?php /* ================================================= EVENTS */ elseif ($tab === 'events'):
    $edit = (int) ($_GET['edit'] ?? 0); $isNew = !empty($_GET['new']);
    $old = $_SESSION['adm_old_ev'] ?? null; unset($_SESSION['adm_old_ev']);
    $cur = null; if ($edit) foreach ($evAll as $x) if ($x['id'] === $edit) $cur = $x;
    if ($edit && !$cur && !$old) { $edit = 0; }
    $showForm = $isNew || $edit || $old;
    $fv = $old ? ['id' => $old['id'], 'title' => $old['title'], 'type' => $old['type'], 'from' => $old['from'], 'to' => $old['to'], 'venue' => $old['venue'], 'org' => $old['org'], 'details' => $old['details'], 'link' => $old['link'], 'ver' => $old['ver'], 'pub' => $old['pub']]
        : ($cur ? ['id' => $cur['id'], 'title' => $cur['title'], 'type' => $cur['type'], 'from' => (string) $cur['date'], 'to' => (string) $cur['end'], 'venue' => $cur['venue'], 'org' => $cur['organizer'], 'details' => $cur['text'], 'link' => (string) $cur['link'], 'ver' => (int) $cur['verified'], 'pub' => (int) $cur['published']]
        : ['id' => 0, 'title' => '', 'type' => 'Event', 'from' => '', 'to' => '', 'venue' => '', 'org' => '', 'details' => '', 'link' => '', 'ver' => 0, 'pub' => 1]);
    $fe = (string) ($_GET['ev'] ?? ''); $fver = (string) ($_GET['ver'] ?? ''); $ft = (string) ($_GET['etype'] ?? ''); $fq = mb_substr(trim((string) ($_GET['eq'] ?? '')), 0, 60);
    $list = array_values(array_filter($evAll, function ($e) use ($fe, $fver, $ft, $fq) {
        $s = event_status($e)['class'];
        if ($fe === 'upcoming' && !in_array($s, ['up', 'on'], true)) return false;
        if ($fe === 'past' && $s !== 'past') return false;
        if ($fe === 'tbc' && $s !== 'tbc') return false;
        if ($fe === 'hidden' && !empty($e['published'])) return false;
        if ($fver === 'no' && !empty($e['verified'])) return false;
        if ($fver === 'yes' && empty($e['verified'])) return false;
        if ($ft !== '' && $e['type'] !== $ft) return false;
        if ($fq !== '' && stripos($e['title'] . ' ' . $e['venue'] . ' ' . $e['organizer'], $fq) === false) return false;
        return true;
    }));
    $eb = ['tab' => 'events', 'ev' => $fe, 'ver' => $fver, 'etype' => $ft, 'eq' => $fq]; $ebq = http_build_query(array_filter($eb, fn($v) => $v !== ''));
?>
  <div class="adm-head adm-head--row">
    <div><h2 class="h-sub">Events and offers</h2><p class="muted">What you save here is what visitors see on the Events page and the home page. Tick <strong>Verified</strong> once you have confirmed the date and venue with the organizer.</p></div>
    <?php if (!$showForm): ?><div class="adm-actions adm-actions--top"><a class="btn btn--ink" href="<?= e(adm_url(['tab' => 'events', 'new' => 1])) ?>">+ Add an event</a>
      <form method="post" action="<?= e($self) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="do" value="seed_events"><button class="btn btn--line" type="submit">Add sample events</button></form>
      <form method="post" action="<?= e($self) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="do" value="unseed_events"><button class="btn btn--line btn--danger" type="submit" data-confirm="Remove all sample events? Events you added yourself are kept.">Remove sample events</button></form></div><?php endif; ?>
  </div>

  <?php if ($showForm): ?>
  <form class="adm-form adm-form--wide" method="post" action="<?= e($self) ?>" novalidate>
    <?= csrf_field() ?><input type="hidden" name="do" value="event_save"><input type="hidden" name="id" value="<?= (int) $fv['id'] ?>"><input type="hidden" name="back" value="tab=events">
    <h3 class="adm-form__title"><?= $fv['id'] ? 'Edit event' : 'New event' ?></h3>
    <div class="field"><label for="ev-title">Title</label><input id="ev-title" name="title" required maxlength="140" value="<?= e($fv['title']) ?>"></div>
    <div class="adm-form__row">
      <div class="field"><label for="ev-type">Type</label><select id="ev-type" name="type"><?php foreach (ADM_EVENT_TYPES as $t): ?><option<?= $fv['type'] === $t ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="ev-from">Starts <small>(leave empty if to be confirmed)</small></label><input id="ev-from" type="date" name="starts_on" value="<?= e($fv['from']) ?>"></div>
      <div class="field"><label for="ev-to">Ends <small>(optional)</small></label><input id="ev-to" type="date" name="ends_on" value="<?= e($fv['to']) ?>"></div>
    </div>
    <div class="adm-form__row adm-form__row--2">
      <div class="field"><label for="ev-venue">Venue</label><input id="ev-venue" name="venue" maxlength="160" value="<?= e($fv['venue']) ?>"></div>
      <div class="field"><label for="ev-org">Organizer</label><input id="ev-org" name="organizer" maxlength="160" value="<?= e($fv['org']) ?>"></div>
    </div>
    <div class="field"><label for="ev-details">Description <small>(max 900 characters)</small></label><textarea id="ev-details" name="details" rows="4" maxlength="900"><?= e($fv['details']) ?></textarea></div>
    <div class="field"><label for="ev-link">More information link <small>(optional, https://…)</small></label><input id="ev-link" type="url" name="link" maxlength="255" value="<?= e($fv['link']) ?>" placeholder="https://"></div>
    <div class="adm-form__checks">
      <label class="adm-check adm-check--box"><input type="checkbox" name="verified" value="1"<?= $fv['ver'] ? ' checked' : '' ?>> <span><strong>Verified</strong>: I confirmed these details with the organizer</span></label>
      <label class="adm-check adm-check--box"><input type="checkbox" name="published" value="1"<?= $fv['pub'] ? ' checked' : '' ?>> <span><strong>Show on the website</strong></span></label>
    </div>
    <div class="adm-actions"><button class="btn btn--ink btn--lg" type="submit"><?= $fv['id'] ? 'Save changes' : 'Add event' ?></button><a class="btn btn--line btn--lg" href="<?= e(adm_url(['tab' => 'events'])) ?>">Cancel</a></div>
  </form>
  <?php endif; ?>

  <div class="adm-chipbar" role="group" aria-label="Filter events">
    <?php foreach (['' => 'All', 'upcoming' => 'Upcoming and ongoing', 'tbc' => 'Date to be confirmed', 'past' => 'Past', 'hidden' => 'Hidden'] as $k => $l): ?><a class="adm-pill<?= $fe === $k ? ' is-on' : '' ?>" href="<?= e(adm_url(array_merge($eb, ['ev' => $k]))) ?>"><?= e($l) ?></a><?php endforeach; ?>
    <span class="adm-chipbar__sep" aria-hidden="true"></span>
    <?php foreach (['' => 'Any verification', 'no' => 'Not verified (' . $evC['unverified'] . ')', 'yes' => 'Verified'] as $k => $l): ?><a class="adm-pill<?= $fver === $k ? ' is-on' : '' ?>" href="<?= e(adm_url(array_merge($eb, ['ver' => $k]))) ?>"><?= e($l) ?></a><?php endforeach; ?>
  </div>
  <form class="adm-filters adm-filters--inline" method="get" action="<?= e($self) ?>">
    <input type="hidden" name="tab" value="events"><input type="hidden" name="ev" value="<?= e($fe) ?>"><input type="hidden" name="ver" value="<?= e($fver) ?>">
    <div class="field"><label for="e-q">Search</label><input id="e-q" type="search" name="eq" value="<?= e($fq) ?>" placeholder="Title, venue or organizer"></div>
    <div class="field"><label for="e-t">Type</label><select id="e-t" name="etype"><option value="">All types</option><?php foreach (ADM_EVENT_TYPES as $t): ?><option<?= $ft === $t ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?></select></div>
    <div class="adm-filters__go"><button class="btn btn--ink btn--sm" type="submit">Apply</button><a class="btn btn--line btn--sm" href="<?= e(adm_url(['tab' => 'events'])) ?>">Reset</a></div>
  </form>
  <p class="muted adm-count"><?= count($list) ?> of <?= count($evAll) ?> event<?= count($evAll) === 1 ? '' : 's' ?></p>

  <?php if (!$list): ?><div class="empty-state empty-state--left"><p><?= $evAll ? 'No events match these filters.' : 'No events yet. Add the first one.' ?></p></div>
  <?php else: ?>
  <ul class="adm-evlist">
    <?php foreach ($list as $ev): $es = event_status($ev); ?>
    <li class="adm-evitem<?= empty($ev['published']) ? ' is-hidden' : '' ?>">
      <div class="hm-date" aria-hidden="true"><?php if ($ev['date']): ?><b><?= e(date('j', strtotime($ev['date']))) ?></b><span><?= e(date('M', strtotime($ev['date']))) ?></span><?php else: ?><b>TBA</b><?php endif; ?></div>
      <div class="adm-evitem__body">
        <div class="adm-evitem__top"><strong><?= e($ev['title']) ?></strong>
          <span class="vtag"><?= e($ev['type']) ?></span>
          <span class="adm-badge adm-badge--<?= $es['class'] === 'past' ? 'rejected' : ($es['class'] === 'tbc' ? 'pending' : 'approved') ?>"><?= e($es['label']) ?></span>
          <?= !empty($ev['verified']) ? '<span class="adm-badge adm-badge--approved">✓ Verified</span>' : '<span class="adm-badge adm-badge--pending">Not verified</span>' ?>
          <?= empty($ev['published']) ? '<span class="adm-badge adm-badge--src">Hidden</span>' : '' ?></div>
        <p class="muted small"><?= $ev['date'] ? e(nice_date($ev['date'])) . ($ev['end'] && $ev['end'] !== $ev['date'] ? ' to ' . e(nice_date($ev['end'])) : '') : 'Date to be confirmed' ?> · <?= e($ev['venue'] ?: 'Venue to be announced') ?> · <?= e($ev['organizer'] ?: 'Organizer to be announced') ?></p>
        <?php if ($ev['text']): ?><p class="adm-evitem__text"><?= e($ev['text']) ?></p><?php endif; ?>
        <div class="mod__actions">
          <form method="post" action="<?= e($self) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="do" value="event_toggle"><input type="hidden" name="id" value="<?= (int) $ev['id'] ?>"><input type="hidden" name="field" value="verified"><input type="hidden" name="back" value="<?= e($ebq) ?>"><button class="btn <?= empty($ev['verified']) ? 'btn--ink' : 'btn--line' ?> btn--sm" type="submit"><?= empty($ev['verified']) ? 'Mark verified' : 'Remove verified' ?></button></form>
          <form method="post" action="<?= e($self) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="do" value="event_toggle"><input type="hidden" name="id" value="<?= (int) $ev['id'] ?>"><input type="hidden" name="field" value="published"><input type="hidden" name="back" value="<?= e($ebq) ?>"><button class="btn btn--line btn--sm" type="submit"><?= empty($ev['published']) ? 'Show on website' : 'Hide from website' ?></button></form>
          <a class="btn btn--line btn--sm" href="<?= e(adm_url(['tab' => 'events', 'edit' => (int) $ev['id']])) ?>">Edit</a>
          <form method="post" action="<?= e($self) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="do" value="event_delete"><input type="hidden" name="id" value="<?= (int) $ev['id'] ?>"><input type="hidden" name="back" value="<?= e($ebq) ?>"><button class="btn btn--line btn--sm btn--danger" type="submit" data-confirm="Delete “<?= e($ev['title']) ?>” permanently?">Delete</button></form>
        </div>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>

<?php /* ================================================= CONTENT */ elseif ($tab === 'content'):
    $allp = all_places(true); $held = array_filter($allp, fn($p) => !$p['published']);
    $withV = array_filter($allp, fn($p) => !empty($p['verified'])); $withPhoto = array_filter($allp, fn($p) => $p['photo']); ?>
  <h2 class="h-sub">Content status</h2>
  <dl class="deflist">
    <div><dt>Listings in data file</dt><dd><?= count($allp) ?> (<?= count($allp) - count($held) ?> public, <?= count($held) ?> held back)</dd></div>
    <div><dt>With any verified fact</dt><dd><?= count($withV) ?> of <?= count($allp) ?></dd></div>
    <div><dt>With a photograph</dt><dd><?= count($withPhoto) ?> of <?= count($allp) ?> (all marked unconfirmed unless you changed data/photo_credits.php)</dd></div>
    <div><dt>With verified coordinates</dt><dd><?= count(array_filter($allp, fn($p) => $p['has_coords'])) ?> of <?= count($allp) ?></dd></div>
  </dl>
  <?php if ($held): ?><h3 class="h-sub">Held back from public pages</h3><ul class="plain-list"><?php foreach ($held as $h): ?><li><strong><?= e($h['name']) ?></strong>: <?= e($h['note'] ?? '') ?></li><?php endforeach; ?></ul><?php endif; ?>
  <p class="muted">Edit <code>data/attractions.php</code> to add verified details, then reload this page.</p>

<?php /* ================================================= TOOLS */ elseif ($tab === 'tools'):
    $S = adm_stats($pdo); ?>
  <h2 class="h-sub">Data tools</h2>
  <div class="adm-grid adm-grid--tools">
    <section class="adm-panel">
      <h2>Sample data</h2>
      <p class="muted">Fills the dashboard with 60 realistic, clearly-labelled entries (ratings, suggestions and concerns across your places, spread over the last 6 weeks). About 70% are approved, so they <strong>also show on public pages</strong> until removed.</p>
      <p><strong><?= $S['source']['sample'] ?></strong> sample entr<?= $S['source']['sample'] === 1 ? 'y' : 'ies' ?> currently stored.</p>
      <div class="adm-actions">
        <form method="post" action="<?= e($self) ?>"><?= csrf_field() ?><input type="hidden" name="do" value="seed"><button class="btn btn--ink" type="submit">Add 60 sample entries</button></form>
        <form method="post" action="<?= e($self) ?>"><?= csrf_field() ?><input type="hidden" name="do" value="unseed"><button class="btn btn--line btn--danger" type="submit" data-confirm="Remove all sample entries? Real visitor and staff entries are kept.">Remove all sample entries</button></form>
      </div>
    </section>
    <section class="adm-panel">
      <h2>Export and clean up</h2>
      <p class="muted">Download everything as a spreadsheet (CSV), or clear out rejected items.</p>
      <div class="adm-actions">
        <a class="btn btn--ink" href="<?= e($self . '?export=1') ?>">Export all as CSV</a>
        <form method="post" action="<?= e($self) ?>"><?= csrf_field() ?><input type="hidden" name="do" value="purge_rejected"><button class="btn btn--line btn--danger" type="submit" data-confirm="Permanently delete all <?= (int) $S['status']['rejected'] ?> rejected entries?"<?= $S['status']['rejected'] ? '' : ' disabled' ?>>Delete <?= (int) $S['status']['rejected'] ?> rejected</button></form>
      </div>
    </section>
    <section class="adm-panel adm-panel--wide">
      <h2>What is stored</h2>
      <ul class="adm-chips">
        <li><span><strong><?= $S['source']['visitor'] ?></strong> from visitors</span></li><li><span><strong><?= $S['source']['staff'] ?></strong> staff entries</span></li><li><span><strong><?= $S['source']['sample'] ?></strong> sample</span></li>
        <li><span><strong><?= $S['total'] ?></strong> total</span></li><li><span><strong><?= $S['photos'] ?></strong> with a photo</span></li><li><span><strong><?= $evC['total'] ?></strong> events</span></li>
      </ul>
      <p class="muted small">Database: <code><?= e(cfg('db.name')) ?></code> on <code><?= e(cfg('db.host')) ?></code>. Tables are created automatically when missing. Something not working? <a href="<?= e(url('admin/repair.php')) ?>">Open the database checkup, repair or rebuild tool</a>.</p>
    </section>
  </div>

<?php /* ================================================= STAFF */ else:
    $staff = $pdo->query('SELECT id, username, created_at FROM admins ORDER BY username')->fetchAll(); ?>
  <h2 class="h-sub">Staff accounts</h2>
  <div class="adm-grid adm-grid--tools">
    <section class="adm-panel adm-panel--wide">
      <h2>Who can sign in</h2>
      <ul class="adm-staff"><?php foreach ($staff as $s): ?>
        <li><span><strong><?= e($s['username']) ?></strong><?= $s['username'] === admin_user() ? ' <span class="adm-badge adm-badge--approved">You</span>' : '' ?> <small class="muted">since <?= e(date('j M Y', strtotime($s['created_at']))) ?></small></span>
          <?php if ($s['username'] !== admin_user() && count($staff) > 1): ?><form method="post" action="<?= e($self) ?>"><?= csrf_field() ?><input type="hidden" name="do" value="delete_staff"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><input type="hidden" name="back" value="tab=staff"><button class="btn btn--line btn--sm btn--danger" type="submit" data-confirm="Remove <?= e($s['username']) ?>?">Remove</button></form><?php endif; ?></li>
      <?php endforeach; ?></ul>
    </section>
    <section class="adm-panel">
      <h2>Change my password</h2>
      <form class="adm-form adm-form--tight" method="post" action="<?= e($self) ?>" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="do" value="change_password"><input type="hidden" name="back" value="tab=staff">
        <div class="field"><label for="pw0">Current password</label><input id="pw0" type="password" name="current" required autocomplete="current-password"></div>
        <div class="field"><label for="pw1">New password <small>(10+ characters)</small></label><input id="pw1" type="password" name="new" required minlength="10" autocomplete="new-password"></div>
        <div class="field"><label for="pw2">Repeat new password</label><input id="pw2" type="password" name="new2" required minlength="10" autocomplete="new-password"></div>
        <button class="btn btn--ink" type="submit">Change password</button></form>
    </section>
    <section class="adm-panel">
      <h2>Add a staff account</h2>
      <form class="adm-form adm-form--tight" method="post" action="<?= e($self) ?>" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="do" value="add_staff"><input type="hidden" name="back" value="tab=staff">
        <div class="field"><label for="ns-u">Username</label><input id="ns-u" name="username" required minlength="3" maxlength="50" pattern="[A-Za-z0-9_.\-]{3,50}"></div>
        <div class="field"><label for="ns-p">Password <small>(10+ characters)</small></label><input id="ns-p" type="password" name="password" required minlength="10" autocomplete="new-password"></div>
        <button class="btn btn--ink" type="submit">Create account</button></form>
    </section>
  </div>
<?php endif; ?>
<?php endif; ?>
</div></section>
<script src="<?= e(asset('assets/js/admin.js')) ?>" defer></script>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
