<?php
/** Staff-area helpers: statistics, sample data, small view helpers. Loaded only by admin/index.php. */

const ADM_STATUSES = ['pending', 'approved', 'rejected'];
const ADM_EVENT_TYPES = ['Event', 'Workshop', 'Promotion', 'Announcement'];
const ADM_SOURCES  = ['visitor' => 'Visitor', 'staff' => 'Staff entry', 'sample' => 'Sample'];

/** slug => [name, cat, area] for every public place. */
function adm_places(): array
{
    static $m = null;
    if ($m === null) { $m = []; foreach (all_places() as $p) $m[$p['slug']] = ['name' => $p['name'], 'cat' => $p['cat'], 'area' => area_label($p)]; }
    return $m;
}

function adm_stars(?float $v, bool $text = true): string
{
    if ($v === null) return '<span class="muted">–</span>';
    $full = (int) round($v);
    return '<span class="adm-stars" aria-hidden="true">' . str_repeat('★', $full) . '<i>' . str_repeat('★', 5 - $full) . '</i></span>'
         . ($text ? ' <span class="adm-num">' . number_format($v, 1) . '</span>' : '') . '<span class="visually-hidden"> ' . number_format($v, 1) . ' out of 5</span>';
}

function adm_when(string $ts): string
{
    $t = strtotime($ts); if (!$t) return $ts;
    $d = time() - $t;
    if ($d < 90) return 'just now';
    if ($d < 3600) return floor($d / 60) . ' min ago';
    if ($d < 86400) return floor($d / 3600) . ' h ago';
    if ($d < 86400 * 7) return floor($d / 86400) . ' d ago';
    return date('j M Y', $t);
}

/** Everything the dashboard and the places tab need, in a handful of queries. */
function adm_stats(PDO $pdo): array
{
    $s = ['status' => ['pending' => 0, 'approved' => 0, 'rejected' => 0], 'kind' => ['Rating' => 0, 'Suggestion' => 0, 'Concern' => 0], 'source' => ['visitor' => 0, 'staff' => 0, 'sample' => 0]];
    foreach ($pdo->query('SELECT status, COUNT(*) n FROM feedback GROUP BY status') as $r) $s['status'][$r['status']] = (int) $r['n'];
    foreach ($pdo->query('SELECT kind, COUNT(*) n FROM feedback GROUP BY kind') as $r) $s['kind'][$r['kind']] = (int) $r['n'];
    foreach ($pdo->query('SELECT source, COUNT(*) n FROM feedback GROUP BY source') as $r) $s['source'][$r['source']] = (int) $r['n'];
    $s['total'] = array_sum($s['status']);
    $s['photos'] = (int) $pdo->query('SELECT COUNT(*) FROM feedback WHERE photo IS NOT NULL')->fetchColumn();

    $r = $pdo->query("SELECT AVG(rating) a, COUNT(rating) n FROM feedback WHERE status='approved' AND rating IS NOT NULL")->fetch();
    $s['avg'] = $r['n'] ? (float) $r['a'] : null; $s['rated'] = (int) $r['n'];

    $s['dist'] = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
    foreach ($pdo->query("SELECT rating, COUNT(*) n FROM feedback WHERE status='approved' AND rating BETWEEN 1 AND 5 GROUP BY rating") as $r) $s['dist'][(int) $r['rating']] = (int) $r['n'];

    $s['week'] = (int) $pdo->query('SELECT COUNT(*) FROM feedback WHERE created_at >= (NOW() - INTERVAL 7 DAY)')->fetchColumn();

    $days = [];
    for ($i = 13; $i >= 0; $i--) $days[date('Y-m-d', strtotime("-$i day"))] = 0;
    foreach ($pdo->query('SELECT DATE(created_at) d, COUNT(*) n FROM feedback WHERE created_at >= (CURDATE() - INTERVAL 13 DAY) GROUP BY DATE(created_at)') as $r) if (isset($days[$r['d']])) $days[$r['d']] = (int) $r['n'];
    $s['trend'] = $days;

    // Per place
    $s['places'] = [];
    $q = "SELECT place_slug, MAX(place_name) place_name,
            COUNT(*) total,
            SUM(status='pending') pend,
            SUM(kind='Concern') concerns, SUM(kind='Suggestion') sugg,
            SUM(status='approved' AND rating IS NOT NULL) n,
            SUM(CASE WHEN status='approved' THEN rating END) rs,
            SUM(status='approved' AND rating=1) r1, SUM(status='approved' AND rating=2) r2, SUM(status='approved' AND rating=3) r3,
            SUM(status='approved' AND rating=4) r4, SUM(status='approved' AND rating=5) r5
          FROM feedback GROUP BY place_slug";
    $pl = adm_places();
    foreach ($pdo->query($q) as $r) {
        $n = (int) $r['n'];
        $r['avg'] = $n ? ((float) $r['rs']) / $n : null;
        $r['cat'] = $pl[$r['place_slug']]['cat'] ?? null;
        if (($r['place_name'] ?? '') === '') $r['place_name'] = $pl[$r['place_slug']]['name'] ?? $r['place_slug'];
        $s['places'][$r['place_slug']] = $r;
    }

    // Per category (weighted by number of ratings)
    $s['cats'] = [];
    foreach (categories() as $k => $c) $s['cats'][$k] = ['label' => $c['label'], 'n' => 0, 'rs' => 0.0, 'total' => 0];
    foreach ($s['places'] as $r) {
        if (!$r['cat'] || !isset($s['cats'][$r['cat']])) continue;
        $s['cats'][$r['cat']]['n'] += (int) $r['n']; $s['cats'][$r['cat']]['rs'] += (float) $r['rs']; $s['cats'][$r['cat']]['total'] += (int) $r['total'];
    }
    foreach ($s['cats'] as &$c) $c['avg'] = $c['n'] ? $c['rs'] / $c['n'] : null;
    unset($c);

    $s['recent'] = fb_fix_names($pdo->query('SELECT id, place_slug, place_name, kind, rating, comment, nickname, photo, status, source, created_at FROM feedback ORDER BY created_at DESC, id DESC LIMIT 6')->fetchAll());
    return $s;
}

/** Bar-chart of the last 14 days as inline SVG (no JavaScript needed, CSP friendly). */
function adm_trend_svg(array $days): string
{
    $max = max(1, max($days)); $n = count($days); $w = 560; $h = 150; $pad = 22; $bw = ($w - 2 * $pad) / $n; $i = 0; $out = '';
    foreach ($days as $d => $v) {
        $bh = $v ? max(4, ($h - 40) * $v / $max) : 2;
        $x = $pad + $i * $bw + 4; $y = $h - 22 - $bh;
        $out .= '<g><title>' . e(date('j M', strtotime($d)) . ': ' . $v . ' submission' . ($v === 1 ? '' : 's')) . '</title>'
              . '<rect class="adm-bar' . ($v ? '' : ' adm-bar--zero') . '" x="' . round($x, 1) . '" y="' . round($y, 1) . '" width="' . round($bw - 8, 1) . '" height="' . round($bh, 1) . '" rx="5"/>'
              . ($v ? '<text class="adm-bar__v" x="' . round($x + ($bw - 8) / 2, 1) . '" y="' . round($y - 5, 1) . '" text-anchor="middle">' . $v . '</text>' : '') . '</g>';
        $i++;
    }
    $keys = array_keys($days);
    $out .= '<text class="adm-bar__l" x="' . $pad . '" y="' . ($h - 4) . '">' . e(date('j M', strtotime($keys[0]))) . '</text>'
          . '<text class="adm-bar__l" x="' . ($w - $pad) . '" y="' . ($h - 4) . '" text-anchor="end">Today</text>';
    return '<svg class="adm-trend" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="Submissions per day over the last 14 days">' . $out . '</svg>';
}

function adm_status_badge(string $s): string
{
    return '<span class="adm-badge adm-badge--' . e($s) . '">' . e(ucfirst($s)) . '</span>';
}

/** Build a LIKE pattern safely. */
function adm_like(string $s): string { return '%' . addcslashes($s, '%_\\') . '%'; }

/** Parse the feedback filters (GET or POST "back") into a clean array. */
function adm_filters(array $in): array
{
    $f = [
        'status' => in_array($in['status'] ?? '', ADM_STATUSES, true) ? $in['status'] : '',
        'kind'   => in_array($in['kind'] ?? '', FB_KINDS, true) ? $in['kind'] : '',
        'place'  => preg_match('/^[a-z0-9-]{1,120}$/', (string) ($in['place'] ?? '')) ? (string) $in['place'] : '',
        'min'    => (int) ($in['min'] ?? 0) >= 1 && (int) ($in['min'] ?? 0) <= 5 ? (int) $in['min'] : 0,
        'src'    => isset(ADM_SOURCES[$in['src'] ?? '']) ? $in['src'] : '',
        'photo'  => !empty($in['photo']) ? '1' : '',
        'q'      => mb_substr(trim((string) ($in['q'] ?? '')), 0, 80),
        'sort'   => in_array($in['sort'] ?? '', ['new', 'old', 'high', 'low'], true) ? $in['sort'] : 'new',
        'page'   => max(1, (int) ($in['page'] ?? 1)),
    ];
    return $f;
}

/** @return array{0:string,1:array} WHERE sql + params for the filters. */
function adm_where(array $f): array
{
    $w = []; $p = [];
    if ($f['status']) { $w[] = 'status = ?'; $p[] = $f['status']; }
    if ($f['kind'])   { $w[] = 'kind = ?'; $p[] = $f['kind']; }
    if ($f['place'])  { $w[] = 'place_slug = ?'; $p[] = $f['place']; }
    if ($f['min'])    { $w[] = 'rating >= ?'; $p[] = $f['min']; }
    if ($f['src'])    { $w[] = 'source = ?'; $p[] = $f['src']; }
    if ($f['photo'])  { $w[] = 'photo IS NOT NULL'; }
    if ($f['q'] !== '') { $w[] = '(comment LIKE ? OR place_name LIKE ? OR nickname LIKE ?)'; $l = adm_like($f['q']); array_push($p, $l, $l, $l); }
    return [$w ? 'WHERE ' . implode(' AND ', $w) : '', $p];
}
function adm_order(string $sort): string
{
    return match ($sort) {
        'old'  => 'created_at ASC, id ASC',
        'high' => 'rating IS NULL, rating DESC, created_at DESC',
        'low'  => 'rating IS NULL, rating ASC, created_at DESC',
        default => 'created_at DESC, id DESC',
    };
}

/* ------------------------------------------------------------------ sample data */
/** Make a sample "visitor photo" from the site's own photo of that place. Returns the stored file name, or null. */
function adm_sample_photo(string $slug): ?string
{
    if (!fb_photo_supported()) return null;
    $p = place_by_slug($slug); if (!$p || empty($p['photo']['src'])) return null;
    $src = ROOT_PATH . '/' . $p['photo']['src']; if (!is_file($src)) return null;
    $im = @imagecreatefromstring((string) file_get_contents($src)); if (!$im) return null;
    $w = imagesx($im); $h = imagesy($im); $s = max($w, $h) > 1200 ? 1200 / max($w, $h) : 1; $nw = (int) round($w * $s); $nh = (int) round($h * $s);
    $out = imagecreatetruecolor($nw, $nh); imagecopyresampled($out, $im, 0, 0, 0, 0, $nw, $nh, $w, $h); imagedestroy($im);
    $dir = fb_photo_dir(); if (!is_dir($dir) && !@mkdir($dir, 0775, true)) { imagedestroy($out); return null; }
    $name = bin2hex(random_bytes(16)) . '.jpg'; imageinterlace($out, true);
    $ok = @imagejpeg($out, $dir . '/' . $name, 82); imagedestroy($out);
    return $ok ? $name : null;
}

function adm_seed_samples(PDO $pdo, int $n = 60): int
{
    $places = array_values(array_filter(all_places(), fn($p) => !empty($p['published'])));
    if (!$places) return 0;
    $names = ['Ana', 'Miguel', 'Kyla', 'Jomar', 'Bea', 'Carlo', 'Tin', 'Rhea', 'Paolo', 'Lyn', 'Marco', 'Jessa', 'Rico', 'Mika', 'Dan', 'Joyce', 'Enzo', 'Nica', 'Gab', 'Trixie', 'Ian', 'Cathy'];
    $good = [
        'beach'   => ['Soft sand and calm water. We stayed until sunset and did not want to leave.', 'Clear water and a relaxed crowd. Great for a family swim.', 'Easy to reach and the view of the bay is gorgeous.', 'Loved the quiet morning here before the boats arrived.', 'Perfect for a lazy day. Bring snacks and sunscreen.', 'One of the prettiest spots we visited this trip.'],
        'diving'  => ['Great visibility and lots of colourful fish. Our guide was careful and friendly.', 'Beautiful reef, fantastic first dive for me.', 'Worth every peso. We saw so many corals and a few turtles.', 'Calm conditions and a well-organised briefing from the operator.', 'Easily the highlight of our weekend in Puerto Galera.'],
        'culture' => ['Fascinating bit of local history. Take time to read the markers.', 'A peaceful stop with lovely views. Good photo spot.', 'Staff were welcoming and happy to explain the story of the place.', 'A nice break from the beach, short visit but memorable.'],
        'nature'  => ['The trek was fun and the pool at the end was so refreshing.', 'Beautiful falls, bring water shoes. Our guide knew the trail well.', 'Lush and quiet. Go early to enjoy it without the crowd.', 'Worth the walk. The kids loved splashing around.'],
    ];
    $mid = ['Nice place, a bit crowded when we arrived but still enjoyable.', 'Good overall. Facilities could be a little cleaner.', 'Pretty, but go early because it gets busy by noon.', 'Decent visit. Signage to find it could be better.'];
    $sug = [
        'Please add clearer signs from the main road, we got lost for a bit.', 'It would help to show typical entrance fees and opening hours on the page.',
        'A few more trash bins near the entrance would keep the area clean.', 'Consider adding a simple map of the trail and rest areas.',
        'It would be great to list the nearest toilets and places to eat.', 'More shaded seating would make it easier for older visitors.',
    ];
    $con = [
        'Some litter was left on the shore when we visited. Please remind visitors to take their trash back.', 'The path was slippery after rain and there was no warning sign.',
        'Boats were too close to the swimming area, which felt unsafe for children.', 'Prices felt higher than posted, please remind operators to display fees.',
    ];

    mt_srand(20261025 + (int) $pdo->query("SELECT COUNT(*) FROM feedback WHERE source='sample'")->fetchColumn());
    $pick = fn(array $a) => $a[mt_rand(0, count($a) - 1)];
    // Weight the featured places so the rankings look realistic.
    $pool = [];
    foreach ($places as $p) { $w = !empty($p['featured']) ? 4 : 1; for ($i = 0; $i < $w; $i++) $pool[] = $p; }

    $ins = $pdo->prepare('INSERT INTO feedback (place_slug, place_name, kind, rating, comment, nickname, photo, source, status, visitor_hash, created_at, moderated_at, moderated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $made = 0; $photoBudget = 12;
    $pdo->beginTransaction();
    try {
        for ($i = 0; $i < $n; $i++) {
            $p = $pick($pool);
            $r = mt_rand(1, 100);
            $kind = $r <= 76 ? 'Rating' : ($r <= 90 ? 'Suggestion' : 'Concern');
            $rating = null; $comment = null;
            if ($kind === 'Rating') {
                $x = mt_rand(1, 100); $rating = $x <= 52 ? 5 : ($x <= 82 ? 4 : ($x <= 94 ? 3 : ($x <= 98 ? 2 : 1)));
                if (mt_rand(1, 100) <= 70) $comment = $rating >= 4 ? $pick($good[$p['cat']] ?? $good['beach']) : ($rating === 3 ? $pick($mid) : $pick($con));
            } elseif ($kind === 'Suggestion') {
                $comment = $pick($sug); if (mt_rand(1, 100) <= 40) $rating = mt_rand(3, 5);
            } else {
                $comment = $pick($con); if (mt_rand(1, 100) <= 40) $rating = mt_rand(1, 3);
            }
            $s = mt_rand(1, 100); $status = $s <= 70 ? 'approved' : ($s <= 90 ? 'pending' : 'rejected');
            $ago = mt_rand(0, 44 * 86400) + mt_rand(0, 86399) % 3600;
            $created = date('Y-m-d H:i:s', time() - $ago);
            $mod = $status === 'pending' ? null : date('Y-m-d H:i:s', strtotime($created) + mt_rand(1800, 86400));
            if ($mod && strtotime($mod) > time()) $mod = date('Y-m-d H:i:s');
            $nick = mt_rand(1, 100) <= 75 ? $pick($names) : null;
            $photo = null;
            if ($photoBudget > 0 && $kind === 'Rating' && $rating >= 4 && $status === 'approved' && $comment && mt_rand(1, 100) <= 55) { $photo = adm_sample_photo($p['slug']); if ($photo) $photoBudget--; }
            $ins->execute([$p['slug'], $p['name'], $kind, $rating, $comment, $nick, $photo, 'sample', $status, hash('sha256', 'sample-' . uniqid('', true) . $i), $created, $mod, $mod ? 'sample' : null]);
            $made++;
        }
        $pdo->commit();
    } catch (Throwable $t) { $pdo->rollBack(); throw $t; }
    return $made;
}
function adm_remove_samples(PDO $pdo): int
{
    adm_delete_photos($pdo, "source = 'sample'", []);
    $st = $pdo->prepare("DELETE FROM feedback WHERE source = 'sample'"); $st->execute(); return $st->rowCount();
}


/* ------------------------------------------------------------------ events */
/** Every event (hidden ones too), in the same order the public page uses. */
function adm_events(): array
{
    $ev = events_all(true);
    $w = ['on' => 0, 'up' => 1, 'tbc' => 2, 'past' => 3];
    usort($ev, function ($a, $b) use ($w) {
        $sa = event_status($a)['class']; $sb = event_status($b)['class'];
        if ($w[$sa] !== $w[$sb]) return $w[$sa] <=> $w[$sb];
        return $sa === 'past' ? strcmp((string) $b['date'], (string) $a['date']) : strcmp((string) $a['date'], (string) $b['date']);
    });
    return $ev;
}
function adm_event_counts(array $ev): array
{
    $c = ['total' => count($ev), 'live' => 0, 'unverified' => 0, 'hidden' => 0, 'past' => 0, 'tbc' => 0];
    foreach ($ev as $e) {
        $s = event_status($e)['class'];
        if ($s === 'on' || $s === 'up') $c['live']++; elseif ($s === 'past') $c['past']++; else $c['tbc']++;
        if (empty($e['verified'])) $c['unverified']++;
        if (empty($e['published'])) $c['hidden']++;
    }
    return $c;
}
/** Free the photo files that belong to these feedback rows (call before deleting the rows). */
function adm_delete_photos(PDO $pdo, string $whereSql, array $params): void
{
    $st = $pdo->prepare("SELECT photo FROM feedback WHERE photo IS NOT NULL AND $whereSql"); $st->execute($params);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $n) fb_photo_delete($n);
}

/** Sample events: add them again, or remove every event that came from the sample set. */
function adm_remove_sample_events(PDO $pdo): int
{
    $st = $pdo->prepare("DELETE FROM events WHERE source IN ('sample','starter')"); $st->execute(); return $st->rowCount();
}
