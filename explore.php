<?php
require __DIR__ . '/includes/bootstrap.php';
$cats = categories();
$all = all_places();

$q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
$cat = (string) ($_GET['cat'] ?? '');  if (!isset($cats[$cat])) $cat = '';
$area = mb_substr(trim((string) ($_GET['area'] ?? '')), 0, 60);
$feature = (string) ($_GET['feature'] ?? '');
$sort = ($_GET['sort'] ?? 'az') === 'cat' ? 'cat' : 'az';
$icons = ['beach' => 'wave', 'diving' => 'anchor', 'culture' => 'church', 'nature' => 'mountain'];

$areas = []; $features = [];
foreach ($all as $p) {
    if ($cat && $p['cat'] !== $cat) continue;
    if ($p['area'] !== 'To be confirmed') $areas[$p['area']] = ($areas[$p['area']] ?? 0) + 1;
}
ksort($areas);
foreach ($all as $p) foreach ($p['features'] as $f) $features[$f] = true;
ksort($features);

$needle = mb_strtolower($q);
$match = function (array $p) use ($needle, $cat, $area, $feature) {
    if ($cat && $p['cat'] !== $cat) return false;
    if ($area && $p['area'] !== $area) return false;
    if ($feature && !in_array($feature, $p['features'], true)) return false;
    if ($needle !== '') {
        $hay = mb_strtolower($p['name'] . ' ' . $p['locality'] . ' ' . $p['area'] . ' ' . $p['kind'] . ' ' . implode(' ', $p['tags']) . ' ' . $p['summary']);
        foreach (preg_split('/\s+/', $needle) as $w) if ($w !== '' && mb_strpos($hay, $w) === false) return false;
    }
    return true;
};
$list = $all;
usort($list, $sort === 'cat'
    ? fn($a, $b) => [array_search($a['cat'], array_keys($cats)), $a['name']] <=> [array_search($b['cat'], array_keys($cats)), $b['name']]
    : fn($a, $b) => strcasecmp($a['name'], $b['name']));
$shown = 0; foreach ($list as $p) if ($match($p)) $shown++;

page(['title' => $cat ? $cats[$cat]['label'] : 'Explore places', 'path' => 'explore.php', 'nav' => 'explore', 'class' => 'page-explore',
      'desc' => 'Find your Galera: filter ' . count($all) . ' Puerto Galera beaches, dive sites, heritage places, waterfalls and recreation spots by mood, locality and feature.']);
require ROOT_PATH . '/includes/header.php';
?>
<section class="xhead">
  <div class="wrap xhead__inner">
    <div>
      <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <span>Explore</span></p>
      <h1 id="xtitle">Where to, <span class="hand">kabayan?</span></h1>
      <p class="xhead__lede"><?= e($cat ? $cats[$cat]['intro'] : 'Tell us your mood. Filter by beach, reef, heritage or waterfall, or just type a name.') ?></p>
    </div>
    <button type="button" class="surprise" data-surprise><span class="surprise__ico"><?= icon('shuffle', 26) ?></span><span><b>Surprise me</b><small>Jump to a random place</small></span></button>
  </div>
  <svg class="xhead__wave" viewBox="0 0 1440 80" preserveAspectRatio="none" aria-hidden="true"><path d="M0 40C180 0 360 0 540 32s360 40 540 8 240-30 360-8V80H0Z"/></svg>
</section>

<section class="directory" aria-label="Attraction directory">
  <form class="xbar" id="filters" action="<?= e(url('explore.php')) ?>" method="get" role="search">
    <div class="wrap xbar__inner">
      <fieldset class="moods">
        <legend class="visually-hidden">Category</legend>
        <label class="mood"><input type="radio" name="cat" value="" <?= $cat === '' ? 'checked' : '' ?>><span><?= icon('sparkle', 18) ?> All <small><?= count($all) ?></small></span></label>
        <?php foreach ($cats as $k => $c): ?>
        <label class="mood"><input type="radio" name="cat" value="<?= e($k) ?>" <?= $cat === $k ? 'checked' : '' ?>><span><?= icon($icons[$k], 18) ?> <?= e($c['label']) ?> <small><?= count_in($k) ?></small></span></label>
        <?php endforeach; ?>
      </fieldset>
      <div class="xbar__row">
        <div class="filters__search">
          <label for="f-q" class="visually-hidden">Search places</label>
          <?= icon('search', 20) ?>
          <input id="f-q" type="search" name="q" value="<?= e($q) ?>" placeholder="Search by name, locality or activity" maxlength="80" autocomplete="off">
        </div>
        <div class="field">
          <label for="f-area">Locality</label>
          <select id="f-area" name="area">
            <option value="">Anywhere</option>
            <?php foreach ($areas as $a => $n): ?><option value="<?= e($a) ?>" <?= $area === $a ? 'selected' : '' ?>><?= e($a) ?> (<?= $n ?>)</option><?php endforeach; ?>
          </select>
        </div>
        <div class="field" id="f-feature-wrap" <?= $cat === 'diving' || $feature ? '' : 'hidden' ?>>
          <label for="f-feature">Dive type</label>
          <select id="f-feature" name="feature">
            <option value="">Any</option>
            <?php foreach (array_keys($features) as $f): ?><option value="<?= e($f) ?>" <?= $feature === $f ? 'selected' : '' ?>><?= e($f) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="f-sort">Sort</label>
          <select id="f-sort" name="sort">
            <option value="az" <?= $sort === 'az' ? 'selected' : '' ?>>A to Z</option>
            <option value="cat" <?= $sort === 'cat' ? 'selected' : '' ?>>By category</option>
          </select>
        </div>
        <div class="xbar__actions">
          <button class="btn btn--ink btn--sm" type="submit">Apply</button>
          <a class="btn btn--line btn--sm" id="f-reset" href="<?= e(url('explore.php')) ?>">Clear</a>
        </div>
      </div>
    </div>
  </form>

  <div class="wrap">
    <p class="results-count" id="results-count" role="status" aria-live="polite"><strong data-num><?= $shown ?></strong> of <?= count($all) ?> places</p>
    <div class="bento" id="grid">
      <?php foreach ($list as $p) echo place_card($p, ['hidden' => !$match($p), 'class' => 'tile--bento', 'feature' => true, 'large' => true]); ?>
    </div>
    <div class="empty-state empty-state--fun" id="empty" <?= $shown ? 'hidden' : '' ?>>
      <div class="empty-state__ico"><?= icon('compass', 44) ?></div>
      <h2>Wrong turn! Nothing matches that.</h2>
      <p>Try a shorter word, check the spelling, or search by locality such as Sabang or Aninuan.</p>
      <p><a class="btn btn--ink btn--sm" href="<?= e(url('explore.php')) ?>">Clear all filters</a> <button type="button" class="btn btn--sun btn--sm" data-surprise>Surprise me</button></p>
    </div>
  </div>
</section>
<script type="application/json" id="gala-suggest"><?= json_embed(array_map(fn($p) => ['s' => $p['slug'], 'n' => $p['name']], $all)) ?></script>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
