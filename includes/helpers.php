<?php
/* ---------- basics ---------- */
function cfg(string $key, $default = null)
{
    $v = $GLOBALS['GALA'];
    foreach (explode('.', $key) as $k) {
        if (!is_array($v) || !array_key_exists($k, $v)) return $default;
        $v = $v[$k];
    }
    return $v;
}
function e($s): string { return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function slugify(string $s): string
{
    $s = mb_strtolower(trim($s));
    $s = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ', '&'], ['a', 'e', 'i', 'o', 'u', 'n', ' '], $s);
    $s = preg_replace('~[^a-z0-9]+~', '-', $s);
    return trim($s, '-');
}

/* ---------- URLs and assets ---------- */
function base_url(): string
{
    static $b = null;
    if ($b !== null) return $b;
    if (cfg('base_url') !== null) return $b = rtrim((string) cfg('base_url'), '/');
    $root = str_replace('\\', '/', realpath(ROOT_PATH) ?: ROOT_PATH);
    $doc = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
    if ($doc !== '' && strpos($root, $doc) === 0) return $b = rtrim(substr($root, strlen($doc)), '/');
    return $b = '';
}
function url(string $path = '', array $q = []): string
{
    $u = base_url() . '/' . ltrim($path, '/');
    $q = array_filter($q, fn($v) => $v !== null && $v !== '');
    return $u . ($q ? '?' . http_build_query($q) : '');
}
function asset(string $path): string
{
    $f = ROOT_PATH . '/' . ltrim($path, '/');
    return url($path) . (is_file($f) ? '?v=' . filemtime($f) : '');
}
function abs_url(string $path = '', array $q = []): string
{
    $site = rtrim((string) cfg('site_url', ''), '/');
    if ($site === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $site = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
    return $site . url($path, $q);
}
function redirect(string $to): never { header('Location: ' . $to); exit; }

/* ---------- CSRF + flash ---------- */
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(24)); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_ok(): bool { return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string) $_POST['csrf']); }
function flash(?array $m = null): ?array
{
    if ($m !== null) { $_SESSION['flash'] = $m; return null; }
    $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f;
}

/* ---------- data ---------- */
function categories(): array { static $c = null; return $c ??= require ROOT_PATH . '/data/categories.php'; }
function all_places(bool $includeHidden = false): array
{
    static $all = null;
    if ($all === null) {
        $all = require ROOT_PATH . '/data/attractions.php';
        $credits = require ROOT_PATH . '/data/photo_credits.php';
        foreach ($all as &$p) {
            $p['type'] = $p['type'] ?? ($p['cat'] === 'beach' ? 'Beach' : ($p['cat'] === 'diving' ? 'Diving' : 'Culture'));
            $v = $p['verified'] ?? [];
            $p['has_coords'] = isset($v['lat'], $v['lng']);
            $p['photo'] = null;
            $f = '/assets/img/places/' . $p['slug'] . '.jpg';
            if (is_file(ROOT_PATH . $f)) {
                $cr = $credits[$p['slug']] ?? $credits['_default'];
                $p['photo'] = ['src' => 'assets' . substr($f, 7), 'sm' => is_file(ROOT_PATH . '/assets/img/places/' . $p['slug'] . '-sm.jpg') ? 'assets/img/places/' . $p['slug'] . '-sm.jpg' : 'assets' . substr($f, 7),
                               'status' => $cr['status'], 'credit' => $cr['credit']];
            }
        }
        unset($p);
    }
    return $includeHidden ? $all : array_values(array_filter($all, fn($p) => !empty($p['published'])));
}
function place_by_slug(string $slug): ?array
{
    foreach (all_places() as $p) if ($p['slug'] === $slug) return $p;
    return null;
}
function places_in(string $cat): array { return array_values(array_filter(all_places(), fn($p) => $p['cat'] === $cat)); }
function count_in(string $cat): int { return count(places_in($cat)); }
function featured_places(): array { return array_values(array_filter(all_places(), fn($p) => !empty($p['featured']))); }

function place_image(array $p, string $size = 'src'): array
{
    if ($p['photo']) return ['src' => asset($p['photo'][$size]), 'placeholder' => false, 'credit' => $p['photo']['credit'], 'status' => $p['photo']['status']];
    $ph = categories()[$p['cat']]['ph'];
    return ['src' => asset('assets/img/placeholders/' . $ph), 'placeholder' => true, 'credit' => '', 'status' => 'art'];
}
function img_badge(array $img): string { return ''; }

/** Map links. Coordinates are used only if verified; otherwise the link is a text search. */
function map_query(array $p): ?string
{
    if (($p['sensitive'] ?? null) === 'indigenous') return null;
    if ($p['has_coords']) return $p['verified']['lat'] . ',' . $p['verified']['lng'];
    return $p['map'] ?: ($p['name'] . ', Puerto Galera, Oriental Mindoro');
}
function gmaps_link(array $p): ?string
{
    $q = map_query($p);
    return $q ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($q) : null;
}
function gmaps_directions(array $p): ?string
{
    $q = map_query($p);
    return $q ? 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($q) : null;
}
function osm_link(array $p): ?string
{
    $q = map_query($p);
    return $q ? 'https://www.openstreetmap.org/search?query=' . rawurlencode($q) : null;
}

/** Facts to show on a place page: only rows that have a value. */
function place_facts(array $p): array
{
    $v = $p['verified'] ?? [];
    $map = ['hours' => 'Opening hours', 'fees' => 'Fees', 'transport' => 'Getting there', 'facilities' => 'Facilities', 'accessibility' => 'Accessibility', 'contact' => 'Contact'];
    $rows = [];
    foreach ($map as $k => $label) if (!empty($v[$k])) $rows[] = [$label, $v[$k]];
    if ($p['has_coords']) $rows[] = ['Coordinates', $v['lat'] . ', ' . $v['lng']];
    return $rows;
}
function category_guidance(string $cat, ?string $type = null): array
{
    $g = [
        'beach' => ['Take your litter with you and keep sand, shells and coral where you found them.', 'Check sea conditions and any local advisories before swimming. Strong currents can appear without warning.', 'Ask before photographing local people, boats or fishing gear.'],
        'diving' => ['Dive only with a qualified local operator and follow the briefing for the day. Depth, current and visibility change.', 'Do not touch, stand on or collect coral, shells or marine life. Keep your fins and gauges off the reef.', 'Descriptions here come from a preliminary inventory and are not a substitute for a dive briefing.'],
        'culture' => ['Treat places of worship, memorials and heritage sites with respect. Follow any rules posted on site.', 'Ask before photographing people, rituals or objects.', 'Confirm opening times and access before you travel.'],
        'nature' => ['Check trail, river and weather conditions before setting out. Access can change after rain.', 'Use local guides where they are offered and stay on marked paths.', 'Carry out everything you carry in. Do not use soap or shampoo in rivers, pools or falls.'],
    ];
    $out = $g[$cat] ?? [];
    if ($cat === 'nature' && $type === 'Recreation') $out = ['Confirm that the venue or operator is open to visitors, and read their rules before you book.', 'Ask about age limits, safety briefings and insurance where relevant.', 'Follow the instructions of staff and guides at all times.'];
    return $out;
}

/* ---------- formatting ---------- */
function nice_date(?string $d): string { return $d ? date('j F Y', strtotime($d)) : ''; }
function event_status(array $ev): array
{
    if (empty($ev['date'])) return ['label' => 'Date to be confirmed', 'class' => 'tbc'];
    $today = date('Y-m-d'); $end = $ev['end'] ?? $ev['date'];
    if ($today < $ev['date']) return ['label' => 'Upcoming', 'class' => 'up'];
    if ($today <= $end) return ['label' => 'Ongoing', 'class' => 'on'];
    return ['label' => 'Past event', 'class' => 'past'];
}
/** All events. Reads the database; falls back to data/events.php only if the database cannot be reached. */
function events_all(bool $includeHidden = false): array
{
    static $cache = [];
    $k = $includeHidden ? 'all' : 'public';
    if (isset($cache[$k])) return $cache[$k];
    $out = null;
    if (function_exists('db') && ($pdo = db())) {
        try {
            $rows = $pdo->query('SELECT id, title, type, starts_on, ends_on, venue, organizer, details, link, verified, published FROM events' . ($includeHidden ? '' : ' WHERE published = 1'))->fetchAll();
            $out = array_map(fn($r) => ['id' => (int) $r['id'], 'title' => (string) $r['title'], 'type' => (string) $r['type'], 'date' => $r['starts_on'] ?: null, 'end' => $r['ends_on'] ?: null,
                'venue' => (string) $r['venue'], 'organizer' => (string) $r['organizer'], 'text' => (string) $r['details'], 'link' => $r['link'] ?: null, 'verified' => (bool) $r['verified'], 'published' => (bool) $r['published']], $rows);
        } catch (Throwable $t) { error_log('[GALA] events: ' . $t->getMessage()); }
    }
    if ($out === null) {
        $out = array_map(fn($e) => $e + ['id' => 0, 'verified' => false, 'published' => true, 'link' => null], (array) require ROOT_PATH . '/data/events.php');
    }
    return $cache[$k] = $out;
}
function events_sorted(): array
{
    $ev = events_all();
    $w = ['on' => 0, 'up' => 1, 'tbc' => 2, 'past' => 3];
    usort($ev, function ($a, $b) use ($w) {
        $sa = event_status($a)['class']; $sb = event_status($b)['class'];
        if ($w[$sa] !== $w[$sb]) return $w[$sa] <=> $w[$sb];
        return $sa === 'past' ? strcmp((string) $b['date'], (string) $a['date']) : strcmp((string) $a['date'], (string) $b['date']);
    });
    return $ev;
}
function stars_html(?float $r, int $n = 0): string
{
    if (!$r) return '<span class="stars stars--none">No ratings yet</span>';
    $full = (int) round($r);
    return '<span class="stars" role="img" aria-label="' . number_format($r, 1) . ' out of 5 from ' . $n . ' rating' . ($n === 1 ? '' : 's') . '"><span aria-hidden="true">' . str_repeat('★', $full) . str_repeat('☆', 5 - $full) . '</span> <span class="stars__n">' . number_format($r, 1) . ' (' . $n . ')</span></span>';
}
function json_embed($data): string
{
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PARTIAL_OUTPUT_ON_ERROR);
}

/* ---------- small inline icons (stroke, currentColor) ---------- */
function icon(string $n, int $s = 20): string
{
    $paths = [
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'pin' => '<path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5"/>',
        'menu' => '<path d="M4 8h16M4 16h16"/>',
        'close' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'bus' => '<rect x="4" y="4" width="16" height="13" rx="3"/><path d="M4 11h16M8 17v3M16 17v3"/><circle cx="8" cy="14" r=".6"/><circle cx="16" cy="14" r=".6"/>',
        'ferry' => '<path d="M3 16c2 0 2 1.2 4.5 1.2S10 16 12 16s2.5 1.2 4.5 1.2S19 16 21 16M5 13l1.5-6h11L19 13M12 7V3"/>',
        'ticket' => '<path d="M4 8a2 2 0 0 0 0 4v0a2 2 0 0 1 0 4v2h16v-2a2 2 0 0 1 0-4v0a2 2 0 0 0 0-4V6H4Z"/><path d="M14 6v12" stroke-dasharray="2 2"/>',
        'ride' => '<circle cx="7" cy="17" r="2.5"/><circle cx="17" cy="17" r="2.5"/><path d="M7 17h6l3-7h-5l-2 4M14 10l-1-3h-3"/>',
        'external' => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
        'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'alert' => '<path d="M12 4 3 20h18Z"/><path d="M12 10v4M12 17.2v.1"/>',
        'leaf' => '<path d="M5 19c0-8 5-13 14-14 0 9-5 14-13 14Z"/><path d="M5 19c3-4 6-6 9-8"/>',
        'wave' => '<path d="M3 9c2-2 3.5-2 5.5 0s3.5 2 5.5 0 3.5-2 5.5 0M3 15c2-2 3.5-2 5.5 0s3.5 2 5.5 0 3.5-2 5.5 0"/>',
        'phone' => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A15 15 0 0 1 3 6a2 2 0 0 1 2-2Z"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'sparkle' => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M18 6l-2.5 2.5M8.5 15.5 6 18"/>',
        'shuffle' => '<path d="M3 7h3c5 0 6 10 11 10h4M3 17h3c1.7 0 2.9-.9 3.9-2.2M21 7h-4c-1.7 0-2.9.9-3.9 2.2M18 4l3 3-3 3M18 14l3 3-3 3"/>',
        'send' => '<path d="m4 12 16-8-6 17-3-7Z"/><path d="m11 14 9-10"/>',
        'chat' => '<path d="M4 5h16v11H9l-5 4Z"/><path d="M8 9.5h8M8 12.5h5"/>',
        'star' => '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9Z"/>',
        'compass' => '<circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5Z"/>',
        'calendar' => '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/>',
        'anchor' => '<circle cx="12" cy="5" r="2"/><path d="M12 7v14M6 12h12M5 14c0 3.5 3 6 7 6s7-2.500 7-6"/>',
        'mountain' => '<path d="m3 19 6-11 4 7 3-4 5 8Z"/>',
        'church' => '<path d="M12 3v4M10 5h4M7 21V11l5-4 5 4v10M3 21h18M10 21v-5h4v5"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8v.1"/>',
    ];
    return '<svg class="ico" width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ($paths[$n] ?? '') . '</svg>';
}

/* ---------- reusable components ---------- */
function place_attrs(array $p): string
{
    $search = mb_strtolower($p['name'] . ' ' . $p['locality'] . ' ' . $p['area'] . ' ' . $p['kind'] . ' ' . implode(' ', $p['tags']) . ' ' . $p['summary']);
    return ' data-slug="' . e($p['slug']) . '" data-cat="' . e($p['cat']) . '" data-area="' . e($p['area']) . '" data-type="' . e($p['type']) . '" data-features="' . e(implode('|', $p['features'])) . '" data-search="' . e($search) . '" data-name="' . e(mb_strtolower($p['name'])) . '"';
}
function area_label(array $p): string { return $p['area'] === 'To be confirmed' ? 'Puerto Galera' : $p['area']; }

/** Image-first tile used on the directory, beaches and related lists. */
function place_card(array $p, array $o = []): string
{
    $img = place_image($p, !empty($o['large']) ? 'src' : 'sm');
    $cls = 'tile' . (!empty($o['class']) ? ' ' . $o['class'] : '') . (!empty($p['featured']) && !empty($o['feature']) ? ' tile--big' : '');
    $loading = !empty($o['eager']) ? 'eager' : 'lazy';
    $h = '<article class="' . $cls . '"' . place_attrs($p) . (!empty($o['hidden']) ? ' hidden' : '') . '>';
    $h .= '<a class="tile__link" href="' . e(url('place.php', ['slug' => $p['slug']])) . '" data-tilt>';
    $h .= '<img src="' . e($img['src']) . '" alt="' . e($p['name'] . ', ' . $p['locality']) . '" width="640" height="480" loading="' . $loading . '" decoding="async">';
    $h .= '<span class="tile__shade"></span>';
    $h .= '<span class="tile__kind">' . e($p['kind']) . '</span>';
    $h .= '<span class="tile__body"><span class="tile__name">' . e($p['name']) . '</span><span class="tile__loc">' . icon('pin', 14) . e(area_label($p)) . '</span>';
    if (empty($o['compact'])) $h .= '<span class="tile__sum">' . e($p['summary']) . '</span>';
    $h .= '</span></a></article>';
    return $h;
}
/** Compact list row with a thumbnail. */
function place_row(array $p, array $o = []): string
{
    $img = place_image($p, 'sm');
    $note = $p['sensitive'] === 'indigenous' ? ' · visit by arrangement' : '';
    return '<li class="prow"' . place_attrs($p) . '><a href="' . e(url('place.php', ['slug' => $p['slug']])) . '">'
        . '<img src="' . e($img['src']) . '" alt="" width="160" height="110" loading="lazy">'
        . '<span class="prow__txt"><span class="prow__name">' . e($p['name']) . '</span><span class="prow__meta">' . e($p['kind'] . ' · ' . area_label($p) . $note) . '</span><span class="prow__sum">' . e($p['summary']) . '</span></span>'
        . '<span class="prow__go">' . icon('arrow', 18) . '</span></a></li>';
}

/** Records the page meta used by header.php */
function page(array $m = []): array
{
    static $meta = ['title' => '', 'desc' => '', 'path' => '', 'class' => '', 'og' => null, 'schema' => null, 'noindex' => false, 'hero' => false];
    if ($m) $meta = array_replace($meta, $m);
    return $meta;
}
