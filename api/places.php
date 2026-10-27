<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');
$cat = (string) ($_GET['cat'] ?? ''); $q = mb_strtolower(trim((string) ($_GET['q'] ?? '')));
$out = [];
foreach (all_places() as $p) {
    if ($cat !== '' && $p['cat'] !== $cat) continue;
    if ($q !== '' && mb_strpos(mb_strtolower($p['name'] . ' ' . $p['locality'] . ' ' . $p['summary']), $q) === false) continue;
    $out[] = ['slug' => $p['slug'], 'name' => $p['name'], 'category' => $p['cat'], 'kind' => $p['kind'], 'locality' => $p['locality'], 'area' => $p['area'],
              'summary' => $p['summary'], 'url' => abs_url('place.php', ['slug' => $p['slug']]), 'verified' => !empty($p['verified']),
              'lat' => $p['has_coords'] ? $p['verified']['lat'] : null, 'lng' => $p['has_coords'] ? $p['verified']['lng'] : null];
}
echo json_encode(['source' => 'GALÁ: Explore. Enjoy. Tara Galera!', 'count' => count($out), 'places' => $out], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
