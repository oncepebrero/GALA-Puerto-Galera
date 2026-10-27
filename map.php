<?php
require __DIR__ . '/includes/bootstrap.php';
$cats = categories();
$all = all_places();
$sel = (string) ($_GET['place'] ?? '');
$data = array_map(fn($p) => [
    's' => $p['slug'], 'n' => $p['name'], 'c' => $p['cat'], 'cl' => $cats[$p['cat']]['label'], 'l' => $p['locality'], 'a' => $p['area'],
    'q' => map_query($p), 'v' => $p['has_coords'], 'ind' => ($p['sensitive'] ?? null) === 'indigenous', 'dive' => $p['cat'] === 'diving',
], $all);
page(['title' => 'Interactive map', 'path' => 'map.php', 'nav' => 'map', 'class' => 'page-map',
      'desc' => 'Find Puerto Galera beaches, dive areas, heritage places and waterfalls on a map, with links to directions in Google Maps and OpenStreetMap.']);
require ROOT_PATH . '/includes/header.php';
?>
<section class="xhead xhead--short">
  <div class="wrap xhead__inner"><div>
    <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <span>Map</span></p>
    <h1>Where things <span class="hand">are</span></h1>
    <p class="xhead__lede">Pick a place and the map jumps to it. Pins are the map service’s best match for each name.</p>
  </div></div>
  <svg class="xhead__wave" viewBox="0 0 1440 80" preserveAspectRatio="none" aria-hidden="true"><path d="M0 40C180 0 360 0 540 32s360 40 540 8 240-30 360-8V80H0Z"/></svg>
</section>
<section class="mapapp" data-map data-initial="<?= e($sel) ?>">
  <div class="wrap mapapp__grid">
    <div class="mapapp__list">
      <div class="mapapp__filters">
        <label class="visually-hidden" for="m-q">Search places on the map</label>
        <div class="filters__search"><?= icon('search', 20) ?><input id="m-q" type="search" placeholder="Find a place" autocomplete="off" maxlength="80"></div>
        <label class="visually-hidden" for="m-cat">Category</label>
        <select id="m-cat"><option value="">All categories</option><?php foreach ($cats as $k => $c): ?><option value="<?= e($k) ?>"><?= e($c['label']) ?></option><?php endforeach; ?></select>
      </div>
      <p class="results-count" id="m-count" role="status" aria-live="polite"></p>
      <ul class="maplist" id="m-list"></ul>
      <p class="empty" id="m-empty" hidden>No places match. Clear the search or choose another category.</p>
    </div>
    <div class="mapapp__stage">
      <div class="mapframe" id="m-frame">
        <div class="mapframe__loading" id="m-loading" role="status"><span class="spinner" aria-hidden="true"></span> Loading map…</div>
        <iframe id="m-iframe" title="Map of Puerto Galera" referrerpolicy="no-referrer-when-downgrade" loading="lazy"
                src="https://www.google.com/maps?q=<?= rawurlencode('Puerto Galera, Oriental Mindoro') ?>&amp;z=12&amp;output=embed"></iframe>
        <noscript><p class="notice notice--err">The map needs JavaScript to switch between places. Use the Google Maps links on each place page instead.</p></noscript>
      </div>
      <div class="mapinfo" id="m-info" aria-live="polite">
        <h2 id="m-title">Puerto Galera, Oriental Mindoro</h2>
        <p id="m-sub" class="muted">Showing the whole municipality. Choose a place from the list.</p>
        <p class="vtag" id="m-verify" hidden></p>
        <div class="mapinfo__links" id="m-links"></div>
        <p class="small muted" id="m-note"></p>
      </div>
    </div>
  </div>
</section>
<script type="application/json" id="gala-map"><?= json_embed($data) ?></script>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
