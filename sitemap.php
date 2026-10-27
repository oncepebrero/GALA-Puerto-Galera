<?php
require __DIR__ . '/includes/bootstrap.php';
header('Content-Type: application/xml; charset=utf-8');
$u = ['', 'explore.php', 'map.php', 'events.php', 'travel.php', 'responsible.php', 'feedback.php', 'reviews.php', 'about.php'];
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($u as $x) echo '  <url><loc>' . e(abs_url($x)) . "</loc></url>\n";
foreach (all_places() as $p) echo '  <url><loc>' . e(abs_url('place.php', ['slug' => $p['slug']])) . "</loc></url>\n";
echo '</urlset>';
