<?php
/**
 * Visitor feedback. Every submission is validated on the server, stored with a prepared
 * statement as "pending", and shown publicly only after staff approve it.
 * The functions NEVER report success unless the INSERT really happened.
 */
const FB_KINDS = ['Rating', 'Suggestion', 'Concern'];

function visitor_hash(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0';
    return hash('sha256', $ip . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|gala-salt-' . ROOT_PATH);
}

function feedback_form_token(): string
{
    $_SESSION['fb_form_at'] = $_SESSION['fb_form_at'] ?? time();
    return csrf_token();
}

/* ------------------------------------------------------------------ photos */
function fb_photo_supported(): bool { return extension_loaded('gd') && function_exists('imagecreatefromstring') && function_exists('imagejpeg') && function_exists('finfo_open'); }
function fb_ini_bytes(string $v): int
{
    $v = trim($v); if ($v === '') return 0; $n = (float) $v;
    return (int) match (strtolower(substr($v, -1))) { 'g' => $n * 1073741824, 'm' => $n * 1048576, 'k' => $n * 1024, default => $n };
}
/** Largest photo accepted, in bytes: the site setting, but never more than PHP allows. */
function fb_photo_limit(): int
{
    $lim = (int) cfg('feedback.max_photo_mb', 5) * 1048576;
    foreach (['upload_max_filesize', 'post_max_size'] as $k) { $b = fb_ini_bytes((string) ini_get($k)); if ($b > 0) $lim = min($lim, $b); }
    return max(0, $lim);
}
function fb_photo_limit_label(): string { $b = fb_photo_limit(); return $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : round($b / 1024) . ' KB'; }
function fb_photo_dir(): string { return ROOT_PATH . '/uploads/feedback'; }
function fb_photo_url(?string $name): string { return $name && preg_match('/^[a-f0-9]{32}\.jpg$/', $name) ? asset('uploads/feedback/' . $name) : ''; }
function fb_photo_delete(?string $name): void
{
    if ($name && preg_match('/^[a-f0-9]{32}\.jpg$/', $name)) { $p = fb_photo_dir() . '/' . $name; if (is_file($p)) @unlink($p); }
}

/**
 * Validate and store one uploaded photo. The file is decoded and re-encoded as a clean JPEG (max 1600 px),
 * which removes EXIF/GPS data and anything that is not really an image.
 * @return array{ok:bool,name:?string,error:?string}
 */
function fb_save_photo(?array $f): array
{
    $fail = fn(string $m) => ['ok' => false, 'name' => null, 'error' => $m];
    if (!$f || !isset($f['error']) || $f['error'] === UPLOAD_ERR_NO_FILE) return ['ok' => true, 'name' => null, 'error' => null];
    if (!fb_photo_supported()) return $fail('Photo upload is not available on this server right now. You can still send your feedback without a photo.');
    $max = fb_photo_limit();
    switch ($f['error']) {
        case UPLOAD_ERR_OK: break;
        case UPLOAD_ERR_INI_SIZE: case UPLOAD_ERR_FORM_SIZE: return $fail('That photo is too large. Please choose one under ' . fb_photo_limit_label() . '.');
        case UPLOAD_ERR_PARTIAL: return $fail('The photo upload was interrupted. Please try again.');
        default: return $fail('The photo could not be stored by the server. Please try again or send your feedback without it.');
    }
    $tmp = (string) ($f['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) return $fail('The photo upload was not valid. Please try again.');
    if (($f['size'] ?? 0) > $max || filesize($tmp) > $max) return $fail('That photo is too large. Please choose one under ' . fb_photo_limit_label() . '.');
    if (filesize($tmp) < 100) return $fail('That file is empty or not a photo.');

    $fi = finfo_open(FILEINFO_MIME_TYPE); $mime = (string) finfo_file($fi, $tmp); finfo_close($fi);
    $okTypes = ['image/jpeg', 'image/png']; if (function_exists('imagecreatefromwebp')) $okTypes[] = 'image/webp';
    if (!in_array($mime, $okTypes, true)) return $fail('Please upload a JPG, PNG or WebP photo.');
    $info = @getimagesize($tmp);
    if (!$info || $info[0] < 1 || $info[1] < 1) return $fail('That file does not look like a photo.');
    if ($info[0] < 200 || $info[1] < 200) return $fail('That photo is very small. Please choose one that is at least 200 pixels wide.');
    if ($info[0] * $info[1] > 50000000) return $fail('That photo has too many pixels to process. Please choose a smaller one.');

    @ini_set('memory_limit', '640M');
    $raw = @file_get_contents($tmp);
    $img = $raw !== false ? @imagecreatefromstring($raw) : false;
    unset($raw);
    if (!$img) return $fail('That photo could not be read. Please try a different one.');

    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {                 // phone photos: apply the camera rotation
        $ex = @exif_read_data($tmp); $o = (int) ($ex['Orientation'] ?? 1);
        $rot = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($rot) { $r = @imagerotate($img, $rot, 0); if ($r) { imagedestroy($img); $img = $r; } }
    }
    $w = imagesx($img); $h = imagesy($img); $long = max($w, $h); $scale = $long > 1600 ? 1600 / $long : 1;
    $nw = max(1, (int) round($w * $scale)); $nh = max(1, (int) round($h * $scale));
    $out = imagecreatetruecolor($nw, $nh);
    imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));                    // flatten transparency onto white
    imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($img);

    $dir = fb_photo_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) { imagedestroy($out); return $fail('The photo could not be stored by the server. Please try again later.'); }
    if (!is_file($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "# Uploaded photos are plain images. Never run scripts from here.\n<FilesMatch \"\\.(php|phtml|phar|pl|py|cgi|sh)$\">\n  Require all denied\n</FilesMatch>\n");
    @file_put_contents($dir . '/index.html', '');
    $name = bin2hex(random_bytes(16)) . '.jpg';
    imageinterlace($out, true);
    $saved = @imagejpeg($out, $dir . '/' . $name, 85);
    imagedestroy($out);
    if (!$saved) return $fail('The photo could not be stored by the server. Please try again later.');
    return ['ok' => true, 'name' => $name, 'error' => null];
}

function fb_contains_blocked(string $s): bool
{
    if ($s === '') return false;
    if (preg_match('~(https?://|www\.|\b[a-z0-9-]+\.(com|net|org|ph|ru|xyz|info|biz|io|me|co)\b)~i', $s)) return true;       // links
    if (preg_match('~[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}~i', $s)) return true;                                                   // e-mail
    if (preg_match('~(\+?\d[\d\s\-().]{8,}\d)~', $s)) return true;                                                                  // phone-like numbers
    if (preg_match('~(.)\1{7,}~u', $s)) return true;                                                                                // aaaaaaaa
    if (preg_match('~\b(fuck|shit|bitch|asshole|puta|putang|putangina|tangina|gago|gaga|bobo|ulol|tarantado|pakyu|pakshet)\b~iu', $s)) return true;
    return false;
}

/** @return array{ok:bool,message:string,errors:array<string,string>} */
function feedback_submit(array $in): array
{
    $cfg = cfg('feedback');
    $res = ['ok' => false, 'message' => '', 'errors' => []];

    if (!$in && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) { $res['message'] = 'That upload was too large for the server (limit ' . fb_photo_limit_label() . '). Please choose a smaller photo.'; return $res; }
    if (!csrf_ok()) { $res['message'] = 'Your session expired. Reload the page and send your feedback again.'; return $res; }
    if (!empty($in['website'])) { $res['message'] = 'Your feedback could not be accepted.'; return $res; }                            // honeypot
    $opened = (int) ($_SESSION['fb_form_at'] ?? 0);
    if ($opened && (time() - $opened) < (int) $cfg['min_seconds']) { $res['message'] = 'That was very quick. Please take a moment and send it again.'; return $res; }

    $slug = trim((string) ($in['place'] ?? ''));
    $kind = (string) ($in['kind'] ?? 'Rating');
    $rating = (int) ($in['rating'] ?? 0);
    $comment = trim(preg_replace('/\s+/u', ' ', (string) ($in['comment'] ?? '')));
    $nick = trim((string) ($in['nickname'] ?? ''));
    $consent = !empty($in['consent']);
    $photoName = null;

    $place = $slug !== '' ? place_by_slug($slug) : null;
    if (!$place) $res['errors']['place'] = 'Choose the place your feedback is about.';
    if (!in_array($kind, FB_KINDS, true)) $kind = 'Rating';
    if ($kind === 'Rating' && ($rating < 1 || $rating > 5)) $res['errors']['rating'] = 'Choose a rating from 1 to 5 stars.';
    if ($kind !== 'Rating' && $rating !== 0 && ($rating < 1 || $rating > 5)) $res['errors']['rating'] = 'The rating must be between 1 and 5.';
    $len = mb_strlen($comment);
    if ($len > (int) $cfg['max_comment']) $res['errors']['comment'] = 'Please keep your comment under ' . $cfg['max_comment'] . ' characters (it is ' . $len . ').';
    if ($kind !== 'Rating' && $len < 10) $res['errors']['comment'] = 'Please write at least a short sentence (10 characters) so staff can act on it.';
    if (mb_strlen($nick) > 40) $res['errors']['nickname'] = 'Keep the nickname under 40 characters.';
    if (fb_contains_blocked($comment)) $res['errors']['comment'] = 'Please remove links, email addresses, phone numbers or inappropriate words. Feedback is public after review.';
    if ($nick !== '' && fb_contains_blocked($nick)) $res['errors']['nickname'] = 'Please use a simple first name or nickname without links or contact details.';
    $hasPhoto = isset($_FILES['photo']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    if ($hasPhoto && $consent && !$res['errors']) { $ph = fb_save_photo($_FILES['photo']); if (!$ph['ok']) $res['errors']['photo'] = $ph['error']; else $photoName = $ph['name']; }
    if (!$consent) $res['errors']['consent'] = 'Please confirm that you have read the privacy note.';
    if ($res['errors']) { fb_photo_delete($photoName); $res['message'] = 'Please fix the highlighted fields.'; return $res; }

    $pdo = db();
    if (!$pdo) { fb_photo_delete($photoName); $res['message'] = 'Feedback could not be saved because the database is not available right now. Nothing was sent. Please try again later.'; return $res; }

    try {
        $h = visitor_hash();
        $st = $pdo->prepare('SELECT COUNT(*) FROM feedback WHERE visitor_hash = ? AND created_at > (NOW() - INTERVAL 1 HOUR)');
        $st->execute([$h]);
        if ((int) $st->fetchColumn() >= (int) $cfg['per_hour']) { $res['message'] = 'You have sent several messages in the last hour. Please try again a little later.'; fb_photo_delete($photoName); return $res; }
        $st = $pdo->prepare('SELECT COUNT(*) FROM feedback WHERE visitor_hash = ? AND place_slug = ? AND comment = ? AND created_at > (NOW() - INTERVAL 1 DAY)');
        $st->execute([$h, $slug, $comment]);
        if ((int) $st->fetchColumn() > 0) { $res['message'] = 'This looks like a message you already sent. It is waiting for review.'; fb_photo_delete($photoName); return $res; }

        $status = !empty($cfg['auto_publish']) ? 'approved' : 'pending';
        $ins = $pdo->prepare('INSERT INTO feedback (place_slug, place_name, kind, rating, comment, nickname, photo, status, visitor_hash) VALUES (?,?,?,?,?,?,?,?,?)');
        $ins->execute([$slug, $place['name'], $kind, $rating ?: null, $comment !== '' ? $comment : null, $nick !== '' ? $nick : null, $photoName, $status, $h]);
        if ($ins->rowCount() !== 1) throw new RuntimeException('Insert affected no rows');
    } catch (Throwable $t) {
        fb_photo_delete($photoName);
        error_log('[GALA] feedback insert failed: ' . $t->getMessage());
        $res['message'] = 'Your feedback could not be saved because of a server problem. Nothing was sent. Please try again later.';
        return $res;
    }
    unset($_SESSION['fb_form_at']);
    $res['ok'] = true;
    $res['message'] = 'Thank you. Your feedback was saved and will appear after staff review.';
    return $res;
}

/** Rows inserted by hand in SQL may have no place_name: fill it in from the place list. */
function fb_fix_names(array $rows): array
{
    foreach ($rows as &$r) {
        if (($r['place_name'] ?? '') === '' && !empty($r['place_slug'])) { $p = place_by_slug($r['place_slug']); $r['place_name'] = $p['name'] ?? ucwords(str_replace('-', ' ', (string) $r['place_slug'])); }
    }
    unset($r);
    return $rows;
}

/**
 * "Favorite moments": the best recent approved ratings (4 stars or more) that have a comment or a photo.
 * Entries with a photo are listed first, then the newest. Returns [] when there is nothing to show yet.
 */
function feedback_moments(int $n = 6): array
{
    $pdo = db(); if (!$pdo) return [];
    try {
        $rows = $pdo->query("SELECT id, place_slug, place_name, kind, rating, comment, nickname, photo, created_at FROM feedback
                             WHERE status = 'approved' AND kind = 'Rating' AND rating >= 4 AND ((comment IS NOT NULL AND comment <> '') OR photo IS NOT NULL)
                             ORDER BY created_at DESC LIMIT 40")->fetchAll();
    } catch (Throwable $t) { error_log('[GALA] moments: ' . $t->getMessage()); return []; }
    $rows = fb_fix_names($rows);
    $rows = array_values(array_filter($rows, fn($r) => place_by_slug($r['place_slug'])));          // only places that are public
    usort($rows, fn($a, $b) => [empty($a['photo']), $b['created_at']] <=> [empty($b['photo']), $a['created_at']]);
    return array_slice($rows, 0, $n);
}
/**
 * All public reviews for the "All reviews" page: approved ratings that have a comment or a photo.
 * $o: place (slug), min/max (stars), photos (bool), sort ('new' | 'top'), page (1-based), per (page size).
 * Returns ['rows' => [...], 'total' => n, 'pages' => n, 'page' => n].
 */
function feedback_reviews(array $o = []): array
{
    $out = ['rows' => [], 'total' => 0, 'pages' => 1, 'page' => 1];
    $pdo = db(); if (!$pdo) return $out;
    $per = max(1, min(60, (int) ($o['per'] ?? 21))); $page = max(1, (int) ($o['page'] ?? 1));
    $w = ["status = 'approved'", "kind = 'Rating'", 'rating IS NOT NULL', "((comment IS NOT NULL AND comment <> '') OR photo IS NOT NULL)"]; $args = [];
    if (!empty($o['place'])) { $w[] = 'place_slug = ?'; $args[] = (string) $o['place']; }
    if (!empty($o['min'])) { $w[] = 'rating >= ?'; $args[] = (int) $o['min']; }
    if (!empty($o['max'])) { $w[] = 'rating <= ?'; $args[] = (int) $o['max']; }
    if (!empty($o['photos'])) { $w[] = 'photo IS NOT NULL'; }
    $order = ($o['sort'] ?? 'new') === 'top' ? 'rating DESC, created_at DESC, id DESC' : 'created_at DESC, id DESC';
    try {
        $st = $pdo->prepare('SELECT id, place_slug, place_name, kind, rating, comment, nickname, photo, created_at FROM feedback WHERE ' . implode(' AND ', $w) . " ORDER BY $order");
        $st->execute($args);
        $rows = fb_fix_names($st->fetchAll());
    } catch (Throwable $t) { error_log('[GALA] reviews: ' . $t->getMessage()); return $out; }
    $rows = array_values(array_filter($rows, fn($r) => place_by_slug($r['place_slug'])));      // only places that are public
    $out['total'] = count($rows); $out['pages'] = max(1, (int) ceil($out['total'] / $per)); $out['page'] = min($page, $out['pages']);
    $out['rows'] = array_slice($rows, ($out['page'] - 1) * $per, $per);
    return $out;
}
/** How many approved reviews each star level has, plus the places that have reviews (slug => name). For the filter bar. */
function feedback_review_facets(): array
{
    $f = ['stars' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0], 'places' => []];
    $pdo = db(); if (!$pdo) return $f;
    try {
        $rows = $pdo->query("SELECT place_slug, place_name, rating FROM feedback WHERE status='approved' AND kind='Rating' AND rating IS NOT NULL AND ((comment IS NOT NULL AND comment <> '') OR photo IS NOT NULL)")->fetchAll();
    } catch (Throwable $t) { return $f; }
    foreach (fb_fix_names($rows) as $r) {
        if (!place_by_slug($r['place_slug'])) continue;
        $f['stars'][(int) $r['rating']] = ($f['stars'][(int) $r['rating']] ?? 0) + 1;
        $f['places'][$r['place_slug']] = $r['place_name'];
    }
    asort($f['places']);
    return $f;
}
/** Numbers for the moments header: average of approved ratings, how many, how many photos. */
function feedback_overview(): array
{
    $o = ['avg' => null, 'n' => 0, 'photos' => 0];
    $pdo = db(); if (!$pdo) return $o;
    try {
        $r = $pdo->query("SELECT AVG(rating) a, COUNT(rating) n FROM feedback WHERE status='approved' AND rating IS NOT NULL")->fetch();
        $o['avg'] = $r['n'] ? (float) $r['a'] : null; $o['n'] = (int) $r['n'];
        $o['photos'] = (int) $pdo->query("SELECT COUNT(*) FROM feedback WHERE status='approved' AND photo IS NOT NULL")->fetchColumn();
    } catch (Throwable $t) {}
    return $o;
}

/** Approved feedback for one place (or all), newest first. Empty array when DB is unavailable. */
function feedback_approved(?string $slug = null, int $limit = 6): array
{
    $pdo = db(); if (!$pdo) return [];
    try {
        if ($slug) { $st = $pdo->prepare("SELECT place_slug, place_name, kind, rating, comment, nickname, photo, created_at FROM feedback WHERE status='approved' AND place_slug = ? ORDER BY created_at DESC LIMIT " . (int) $limit); $st->execute([$slug]); }
        else { $st = $pdo->query("SELECT place_slug, place_name, kind, rating, comment, nickname, photo, created_at FROM feedback WHERE status='approved' AND (comment IS NOT NULL OR photo IS NOT NULL) ORDER BY created_at DESC LIMIT " . (int) $limit); }
        return fb_fix_names($st->fetchAll());
    } catch (Throwable $t) { error_log('[GALA] ' . $t->getMessage()); return []; }
}
function feedback_stats(string $slug): array
{
    $pdo = db(); if (!$pdo) return ['avg' => null, 'n' => 0];
    try {
        $st = $pdo->prepare("SELECT AVG(rating) a, COUNT(rating) n FROM feedback WHERE status='approved' AND place_slug = ? AND rating IS NOT NULL");
        $st->execute([$slug]); $r = $st->fetch();
        return ['avg' => $r['n'] ? (float) $r['a'] : null, 'n' => (int) $r['n']];
    } catch (Throwable $t) { return ['avg' => null, 'n' => 0]; }
}
