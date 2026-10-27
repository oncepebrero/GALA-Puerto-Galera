<?php
/**
 * "Ask Gali": a small rule-based guide. It answers ONLY from the site's own data
 * (places, travel notes, events, FAQs, approved ratings). It never invents fees, hours or coordinates.
 * GET /api/gali.php?q=... -> {"text": "...", "links": [{"label": "...", "url": "..."}]}
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$raw = trim((string) ($_GET['q'] ?? ''));
$q = mb_strtolower(mb_substr($raw, 0, 200));
$cats = categories();
$all = all_places();
$travel = require ROOT_PATH . '/data/travel.php';

function reply(string $text, array $links = []): never
{
    echo json_encode(['text' => $text, 'links' => array_values($links)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function has(string $q, string $re): bool { return preg_match('~' . $re . '~iu', $q) === 1; }
function plink(array $p): array { return ['label' => $p['name'], 'url' => url('place.php', ['slug' => $p['slug']])]; }

$HELP = [['label' => 'Getting there', 'url' => url('travel.php')], ['label' => 'FAQs', 'url' => url('travel.php#faq')]];

if ($q === '' || has($q, '^(hi|hello|hey|kumusta|musta|good (morning|afternoon|evening))\b.{0,15}$')) {
    reply("Kumusta! I'm Gali, your guide to Puerto Galera. Ask me about a beach, a dive site, a waterfall, how to get here, fares, events or contacts. Tara!");
}
if (has($q, '^(thanks|thank you|salamat|ty)\b')) reply("Walang anuman! Enjoy Puerto Galera, and tell the next visitor what you found.", [['label' => 'Rate a place', 'url' => url('feedback.php')]]);

/* ---- a specific place ---- */
$hit = null;
foreach ($all as $p) {
    $n = mb_strtolower($p['name']);
    $short = trim(preg_replace('~\b(beach|falls|cave|island|point|park|church|museum|club|inc\.?|the)\b~iu', '', $n));
    $generic = ['wreck', 'wrecks', 'atoll', 'wall', 'hill', 'coral', 'cove', 'garden', 'channel', 'rock', 'drift', 'sabang', 'marine', 'giant', 'boulders', 'fish bowl', 'sweetlips corner', 'verde island passage', 'point'];
    if (has($q, '\b' . preg_quote($n, '~') . '\b') || (mb_strlen($short) >= 6 && !in_array($short, $generic, true) && has($q, '\b' . preg_quote($short, '~') . '\b'))) {
        if (!$hit || mb_strlen($p['name']) > mb_strlen($hit['name'])) $hit = $p;
    }
}
if ($hit) {
    $p = $hit; $L = [plink($p)];
    $ind = ($p['sensitive'] ?? null) === 'indigenous';
    $contact = [['label' => 'Tourism contacts', 'url' => url('travel.php#contacts')]];
    if ($ind) {
        reply($p['name'] . ': ' . $p['summary'] . ' Visits are the community’s decision, so please arrange them through community representatives or the Puerto Galera Tourism Office, and ask before taking photographs.', array_merge($L, $contact));
    }
    if (has($q, 'fee|cost|price|how much|hour|open|schedule|entrance|rate\b')) {
        $v = $p['verified'] ?? [];
        $txt = !empty($v['fees']) || !empty($v['hours'])
            ? $p['name'] . ': ' . trim(($v['hours'] ?? '') . ' ' . ($v['fees'] ?? ''))
            : "I don't have a listed fee or schedule for " . $p['name'] . ', so I will not guess. The operator or the Tourism Office can tell you.';
        reply($txt, array_merge($L, $contact));
    }
    if (has($q, 'get to|how to get|direction|where|route|transport|commute|reach|map|located|location')) {
        $links = $L;
        if ($g = gmaps_directions($p)) $links[] = ['label' => 'Directions in Google Maps', 'url' => $g];
        $links[] = ['label' => 'Show on map', 'url' => url('map.php', ['place' => $p['slug']])];
        reply($p['name'] . ' is at ' . $p['locality'] . '.' . ($p['cat'] === 'diving' ? ' Dive sites are underwater, so the map shows the nearest landmark.' : '') . ' The map pin is a best match for the name.', $links);
    }
    if (has($q, 'safe|danger|risk|careful|safety|rules|guideline|tips')) {
        reply(trim(($p['note'] ?? '') . ' ' . implode(' ', array_slice(category_guidance($p['cat'], $p['type']), 0, 2))), $L);
    }
    $st = feedback_stats($p['slug']);
    $txt = $p['name'] . ' is ' . (preg_match('~^[aeiou]~i', $p['kind']) ? 'an ' : 'a ') . mb_strtolower($p['kind']) . ' at ' . $p['locality'] . '. ' . $p['summary'];
    $txt .= $st['n'] ? ' Visitors rate it ' . number_format($st['avg'], 1) . ' out of 5.' : ' No visitor ratings yet.';
    if (!empty($p['note'])) $txt .= ' ' . $p['note'];
    reply($txt, array_merge($L, [['label' => 'Show on map', 'url' => url('map.php', ['place' => $p['slug']])], ['label' => 'Rate it', 'url' => url('place.php', ['slug' => $p['slug']]) . '#feedback']]));
}

/* ---- travel, fares, registration ---- */
if (has($q, '\b(fares?|fees?|cost|price|how much|ferry|bus|terminal|pg ?pass|environmental|tricycles?|tuk|how to get|get to|go to|travel|route|manila|batangas|commute|transport|getting there)\b')) {
    $o = [];
    foreach ($travel['steps'] as $s) {
        $facts = implode(', ', array_map(fn($f) => $f[0] . ' ' . $f[1], $s['facts']));
        $o[] = '• ' . $s['title'] . ': ' . $facts;
    }
    reply("Here is the usual route:\n" . implode("\n", $o) . "\nThese are estimates and can change, so confirm with the operators or the Tourism Office before you travel.", [['label' => 'Full travel guide', 'url' => url('travel.php')], ['label' => 'Tourism contacts', 'url' => url('travel.php#contacts')]]);
}
if (has($q, 'contact|phone|number|email|tourism office|call|facebook|reach you')) {
    $c = $travel['contact'];
    reply("Tourism contacts:\nPhone: " . implode(' / ', $c['phones']) . "\nEmail: " . implode(' / ', $c['emails']) . "\nPlease confirm details before you visit.", [['label' => 'Contacts and help', 'url' => url('travel.php#contacts')]]);
}

/* ---- events and offers ---- */
if (has($q, 'event|festival|calendar|workshop|promo|offer|discount|deal|announce|news|what.?s on|happening')) {
    $ev = array_values(array_filter(events_sorted(), fn($e) => event_status($e)['class'] !== 'past'));
    $ev = array_slice($ev, 0, 4);
    if ($ev) reply("Here's what's coming up:\n" . implode("\n", array_map(fn($e) => '• ' . $e['title'] . ' (' . ($e['date'] ? date('M j', strtotime($e['date'])) : 'date to be announced') . ')', $ev)), [['label' => 'Events and offers', 'url' => url('events.php')]]);
    reply('Nothing is listed right now. Check the events page again soon.', [['label' => 'Events and offers', 'url' => url('events.php')]]);
}

/* ---- categories ---- */
$I = [
    'beach' => 'beach|swim|shore|picnic|sand|cove',
    'diving' => 'div(e|ing)|snorkel|reef|wreck|scuba|marine|underwater',
    'culture' => 'cultur|heritage|museum|church|mangyan|histor|lighthouse|grotto|art\b|gallery',
    'nature' => 'waterfall|falls|nature|trek|hik(e|ing)|cave|hot ?spring|trail|forest|adventure|golf|yacht|shooting|sports|recreation|zip',
];
foreach ($I as $c => $re) {
    if (has($q, $re)) {
        $rows = places_in($c);
        if ($c === 'nature') {
            if (has($q, 'golf|yacht|shooting|sports|recreation|adventure|zip')) $rows = array_values(array_filter($rows, fn($p) => $p['type'] === 'Recreation'));
            elseif (has($q, 'waterfall|falls')) $rows = array_values(array_filter($rows, fn($p) => $p['kind'] === 'Waterfall'));
            elseif (has($q, 'hot ?spring')) $rows = array_values(array_filter($rows, fn($p) => $p['kind'] === 'Hot spring'));
            elseif (has($q, 'cave')) $rows = array_values(array_filter($rows, fn($p) => $p['kind'] === 'Cave'));
        }
        if (!$rows) continue;
        usort($rows, fn($a, $b) => (int) $b['featured'] <=> (int) $a['featured']);
        $pick = array_slice($rows, 0, 5);
        $note = ['diving' => 'Dive details are for orientation only. A qualified local operator gives the real briefing.', 'culture' => 'Please be respectful, and ask before photographing people.', 'nature' => 'Check weather and access first.', 'beach' => 'Take your litter home and keep the reef healthy.'][$c];
        reply('I found ' . count($rows) . ' matching places. Here are a few to start with. ' . $note, array_merge(array_map('plink', $pick), [['label' => 'See all ' . mb_strtolower($cats[$c]['label']), 'url' => url('explore.php', ['cat' => $c])]]));
    }
}

/* ---- recommendations ---- */
if (has($q, 'best|top|recommend|popular|favorite|suggest|where should|surprise|anywhere|first time')) {
    $f = featured_places(); shuffle($f);
    reply("Good places to start:", array_merge(array_map('plink', array_slice($f, 0, 5)), [['label' => 'Browse everything', 'url' => url('explore.php')]]));
}
if (has($q, 'feedback|review|\brate\b|rating|complain|concern|suggestion')) reply('You can rate a place, leave a suggestion or report a concern. Staff read every message before it appears, and GALÁ does not ask for your email or phone number.', [['label' => 'Give feedback', 'url' => url('feedback.php')]]);
if (has($q, 'map|navigate')) reply('Pick a place on the map and it jumps there, with links to directions in Google Maps and OpenStreetMap.', [['label' => 'Open the map', 'url' => url('map.php')]]);
if (has($q, 'safe|weather|typhoon|rule|responsible|sustainab|eco|litter|trash')) reply('Keep off the coral, take your trash home, ask before photographing people and respect Indigenous communities. Check weather and sea conditions before boat trips or treks, and follow your guide’s instructions.', [['label' => 'Responsible travel', 'url' => url('responsible.php')]]);

/* ---- keyword fallback ---- */
$stop = ['what', 'where', 'when', 'which', 'there', 'about', 'tell', 'good', 'place', 'places', 'have', 'puerto', 'galera', 'with', 'that', 'this', 'from', 'your', 'near', 'best', 'show', 'give', 'find', 'want', 'like'];
$words = array_filter(preg_split('~[^\p{L}]+~u', $q), fn($x) => mb_strlen($x) >= 4 && !in_array($x, $stop, true));
$scored = [];
foreach ($all as $p) {
    $h = mb_strtolower($p['name'] . ' ' . $p['summary'] . ' ' . $p['locality'] . ' ' . implode(' ', $p['tags']));
    $n = 0; foreach ($words as $w) if (mb_strpos($h, $w) !== false) $n++;
    if ($n) $scored[] = [$n, $p];
}
if ($scored) {
    usort($scored, fn($a, $b) => $b[0] <=> $a[0]);
    reply('These might match what you are looking for:', array_merge(array_map(fn($s) => plink($s[1]), array_slice($scored, 0, 5)), [['label' => 'Search everything', 'url' => url('explore.php', ['q' => $raw])]]));
}
reply("I couldn't find that. Try a place name like Minolo Beach or Tukuran Falls, or ask about beaches, diving, waterfalls, fares or events.", array_merge($HELP, [['label' => 'Search all places', 'url' => url('explore.php')]]));
