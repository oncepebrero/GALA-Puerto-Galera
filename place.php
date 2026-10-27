<?php
require __DIR__ . '/includes/bootstrap.php';

$slug = (string) ($_GET['slug'] ?? '');
$p = place_by_slug($slug);
if (!$p) { http_response_code(404); require ROOT_PATH . '/404.php'; exit; }

$cats = categories();
$cat = $cats[$p['cat']];
$img = place_image($p, 'src');
$stats = feedback_stats($p['slug']);
$reviews = feedback_approved($p['slug'], 8);
$indigenous = ($p['sensitive'] ?? null) === 'indigenous';
$rows = place_facts($p);
$verified = $p['verified'] ?? [];
$guidance = category_guidance($p['cat'], $p['type']);
$gm = gmaps_link($p); $gd = gmaps_directions($p); $osm = osm_link($p);

/* related: same area first, then same category */
$related = array_values(array_filter(all_places(), fn($o) => $o['slug'] !== $p['slug'] && $o['cat'] === $p['cat'] && $o['area'] === $p['area']));
if (count($related) < 3) {
    foreach (all_places() as $o) {
        if ($o['slug'] !== $p['slug'] && $o['cat'] === $p['cat'] && !in_array($o, $related, true)) $related[] = $o;
        if (count($related) >= 3) break;
    }
}
$related = array_slice($related, 0, 3);

$siblings = places_in($p['cat']);
$idx = array_search($p['slug'], array_column($siblings, 'slug'));
$prev = $siblings[($idx - 1 + count($siblings)) % count($siblings)];
$next = $siblings[($idx + 1) % count($siblings)];

$schema = [
    '@context' => 'https://schema.org', '@type' => 'TouristAttraction', 'name' => $p['name'], 'description' => $p['summary'],
    'touristType' => $cat['label'],
    'containedInPlace' => ['@type' => 'Place', 'name' => 'Puerto Galera, Oriental Mindoro, Philippines'],
    'url' => abs_url('place.php', ['slug' => $p['slug']]),
];
if ($p['has_coords']) $schema['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => $verified['lat'], 'longitude' => $verified['lng']];
if ($stats['n'] >= 3) $schema['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => round($stats['avg'], 1), 'reviewCount' => $stats['n'], 'bestRating' => 5, 'worstRating' => 1];

page(['title' => $p['name'], 'nav' => 'explore', 'class' => 'page-place',
      'path' => 'place.php?slug=' . $p['slug'], 'canonical' => abs_url('place.php', ['slug' => $p['slug']]),
      'desc' => $p['name'] . ' (' . $p['locality'] . '): ' . $p['summary'] . '',
      'og' => abs_url(ltrim(parse_url($img['src'], PHP_URL_PATH), '/')), 'schema' => $schema]);
require ROOT_PATH . '/includes/header.php';
?>
<section class="phead">
  <div class="phead__media">
    <img src="<?= e($img['src']) ?>" alt="<?= e($p['name'] . ', ' . $p['locality']) ?>" width="1400" height="875" fetchpriority="high">
    <div class="phead__shade" aria-hidden="true"></div>
  </div>
  <div class="wrap phead__inner">
    <p class="crumbs crumbs--light"><a href="<?= e(url()) ?>">Home</a> / <a href="<?= e(url('explore.php', ['cat' => $p['cat']])) ?>"><?= e($cat['label']) ?></a> / <span><?= e($p['name']) ?></span></p>
    <p class="phead__kind"><?= e($p['kind']) ?><?= $p['type'] === 'Recreation' ? ' · Recreation' : '' ?></p>
    <h1><?= e($p['name']) ?></h1>
    <p class="phead__loc"><?= icon('pin', 18) ?> <?= e($p['locality']) ?></p>
  </div>
</section>

<section class="section section--tight">
  <div class="wrap pgrid">
    <article class="pgrid__main">
      <h2 class="visually-hidden">About <?= e($p['name']) ?></h2>
      <p class="plead"><?= e($p['summary']) ?></p>
      <?php if (!empty($p['aka'])): ?><p class="muted">Also listed as: <?= e($p['aka']) ?></p><?php endif; ?>

      <?php if ($p['tags']): ?>
      <ul class="taglist" aria-label="Described as">
        <?php foreach ($p['tags'] as $t): ?><li><?= e($t) ?></li><?php endforeach; ?>
      </ul>
      <?php endif; ?>
      

      <?php if ($p['note']): ?>
      <aside class="callout <?= $indigenous ? 'callout--respect' : 'callout--care' ?>" role="note">
        <?= icon($indigenous ? 'leaf' : 'alert', 22) ?>
        <div><strong><?= $indigenous ? 'Visit by arrangement only' : 'Please check first' ?></strong><p><?= e($p['note']) ?></p></div>
      </aside>
      <?php endif; ?>

      <h2 class="h-sub">Good to know</h2>
      <dl class="goodtoknow">
        <div><dt>Where</dt><dd><?= e($p['locality']) ?></dd></div>
        <div><dt>Type</dt><dd><?= e($p['kind']) ?><?= $p['type'] === 'Recreation' ? ' (recreation)' : '' ?></dd></div>
        <?php foreach ($rows as [$label, $value]): ?><div><dt><?= e($label) ?></dt><dd><?= e($value) ?></dd></div><?php endforeach; ?>
        <?php if (!$rows): ?><div><dt>Hours, fees, access</dt><dd><?= $indigenous ? 'Arranged with community representatives or the' : 'Please ask the operator or the' ?> <a href="<?= e(url('travel.php#contacts')) ?>">Puerto Galera Tourism Office</a> before you go.</dd></div><?php endif; ?>
      </dl>

      <h2 class="h-sub"><?= $p['cat'] === 'diving' ? 'Before you dive' : 'Visiting well' ?></h2>
      <p class="muted small">General guidance, not specific to this listing.</p>
      <ul class="ticks">
        <?php foreach ($guidance as $g): ?><li><?= icon('check', 18) ?> <span><?= e($g) ?></span></li><?php endforeach; ?>
      </ul>

      <h2 class="h-sub" id="feedback">Ratings and feedback</h2>
      <div class="ratingline"><?= stars_html($stats['avg'], $stats['n']) ?></div>
      <?php if ($reviews): ?>
        <div class="reviews">
        <?php foreach ($reviews as $r): ?>
          <blockquote class="voice voice--small">
            <?php if ($r['rating']): ?><p class="voice__stars" aria-label="<?= (int) $r['rating'] ?> out of 5"><?= str_repeat('★', (int) $r['rating']) . str_repeat('☆', 5 - (int) $r['rating']) ?></p><?php endif; ?>
            <?php if (!empty($r['photo']) && fb_photo_url($r['photo'])): ?><img class="voice__photo" src="<?= e(fb_photo_url($r['photo'])) ?>" alt="Photo shared by <?= e($r['nickname'] ?: 'a visitor') ?> at <?= e($p['name']) ?>" loading="lazy"><?php endif; ?>
            <?php if ($r['comment']): ?><p><?= e($r['comment']) ?></p><?php endif; ?>
            <footer><?= e($r['kind']) ?> · <?= e($r['nickname'] ?: 'A visitor') ?> · <?= e(nice_date(substr($r['created_at'], 0, 10))) ?></footer>
          </blockquote>
        <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="empty"><?= db_ok() ? 'No reviews yet. Been here? Be the first to share.' : 'Reviews can’t load right now.' ?></p>
      <?php endif; ?>
      <details class="fbdrop">
        <summary class="btn btn--ink btn--sm">Rate or comment on <?= e($p['name']) ?></summary>
        <?php $fbSelected = $p['slug']; $fbLock = true; $fbErrors = []; $fbOld = []; $fbAction = url('feedback.php'); $fbId = 'fbp'; include ROOT_PATH . '/includes/feedback_form.php'; ?>
      </details>
    </article>

    <aside class="pgrid__side">
      <div class="sidecard">
        <h2 class="mini-title">Map and directions</h2>
        <?php if ($gm): ?>
          <p class="small muted"><?= $p['has_coords'] ? 'Links use exact coordinates.' : 'These links search by place name, so the pin is the map’s best match.' ?></p>
          <a class="btn btn--ink btn--block" href="<?= e(url('map.php', ['place' => $p['slug']])) ?>"><?= icon('pin', 18) ?> Show on GALÁ map</a>
          <a class="btn btn--line btn--block" href="<?= e($gd) ?>" target="_blank" rel="noopener noreferrer"><?= icon('external', 18) ?> Directions in Google Maps</a>
          <a class="btn btn--line btn--block" href="<?= e($gm) ?>" target="_blank" rel="noopener noreferrer"><?= icon('external', 18) ?> Open in Google Maps</a>
          <a class="btn btn--line btn--block" href="<?= e($osm) ?>" target="_blank" rel="noopener noreferrer"><?= icon('external', 18) ?> Search OpenStreetMap</a>
          <?php if ($p['cat'] === 'diving'): ?><p class="small muted">Dive sites are underwater. Map links go to the nearest named landmark, not the site.</p><?php endif; ?>
        <?php else: ?>
          <p class="small">Map links are not offered for this listing. Ask the community or the Tourism Office how to arrange a visit.</p>
        <?php endif; ?>
      </div>
      <div class="sidecard">
        <h2 class="mini-title">Need help?</h2>
        <p class="small">Not sure about hours, fees or access? Ask the <a href="<?= e(url('travel.php#contacts')) ?>">Puerto Galera Tourism Office</a>, or ask <button type="button" class="linkbtn" data-open-gali>Gali</button>.</p>
      </div>
    </aside>
  </div>
</section>

<?php if ($related): ?>
<section class="section section--tight related">
  <div class="wrap">
    <h2 class="section-title section-title--sm"><?= $p['area'] !== 'To be confirmed' && count(array_filter($related, fn($o) => $o['area'] === $p['area'])) ? 'Nearby in ' . e($p['area']) : 'More in ' . e($cat['label']) ?></h2>
    <div class="bento bento--3"><?php foreach ($related as $o) echo place_card($o, ['class' => 'tile--bento', 'compact' => true]); ?></div>
    <nav class="pager" aria-label="Previous and next in <?= e($cat['label']) ?>">
      <a href="<?= e(url('place.php', ['slug' => $prev['slug']])) ?>" rel="prev"><small>Previous</small><span><?= e($prev['name']) ?></span></a>
      <a href="<?= e(url('place.php', ['slug' => $next['slug']])) ?>" rel="next"><small>Next</small><span><?= e($next['name']) ?></span></a>
    </nav>
  </div>
</section>
<?php endif; ?>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
