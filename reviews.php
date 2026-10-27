<?php
require __DIR__ . '/includes/bootstrap.php';

/* filters from the address bar: ?stars=5 &photos=1 &place=slug &sort=top &page=2 */
$stars  = (int) ($_GET['stars'] ?? 0); if ($stars < 1 || $stars > 5) $stars = 0;
$photos = !empty($_GET['photos']);
$place  = (string) ($_GET['place'] ?? ''); if ($place !== '' && !place_by_slug($place)) $place = '';
$sort   = ($_GET['sort'] ?? '') === 'top' ? 'top' : 'new';
$pageNo = max(1, (int) ($_GET['page'] ?? 1));

$facets = feedback_review_facets();
$ov     = feedback_overview();
$res    = feedback_reviews(['place' => $place, 'min' => $stars, 'max' => $stars, 'photos' => $photos, 'sort' => $sort, 'page' => $pageNo, 'per' => 24]);
$rows   = $res['rows']; $sizes = rv_sizes($rows);
$totalAll = array_sum($facets['stars']);
$filtered = $stars || $photos || $place !== '' || $sort !== 'new';

/** Build a link that keeps the current filters and changes some of them. Page resets unless given. */
$link = function (array $change = []) use ($stars, $photos, $place, $sort): string {
    $q = array_merge(['stars' => $stars ?: null, 'photos' => $photos ? 1 : null, 'place' => $place ?: null, 'sort' => $sort !== 'new' ? $sort : null], $change);
    return url('reviews.php', array_filter($q, fn($v) => $v !== null && $v !== '' && $v !== 0));
};

page(['title' => 'All visitor reviews', 'path' => 'reviews.php', 'nav' => '', 'class' => 'page-reviews',
      'desc' => 'Read every approved visitor review and photo from Puerto Galera: beaches, dive sites, waterfalls and heritage places, rated by the people who went.',
      'noindex' => $filtered || $pageNo > 1]);
require ROOT_PATH . '/includes/header.php';
?>
<link rel="stylesheet" href="<?= e(asset('assets/css/reviews.css')) ?>">
<section class="xhead">
  <div class="wrap xhead__inner"><div>
    <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <span>All reviews</span></p>
    <h1>Straight from our <span class="hand">visitors</span></h1>
    <p class="xhead__lede">Every approved rating, memory and photo shared by people who have been to Puerto Galera. Tap any card to read it in full.</p>
  </div></div>
  <svg class="xhead__wave" viewBox="0 0 1440 80" preserveAspectRatio="none" aria-hidden="true"><path d="M0 40C180 0 360 0 540 32s360 40 540 8 240-30 360-8V80H0Z"/></svg>
</section>

<section class="section section--tight rvm">
  <div class="wrap">
    <?php if ($totalAll > 0 && $ov['avg'] !== null): $avgR = (int) round($ov['avg']); ?>
    <div class="rvp-sum" data-reveal>
      <div class="rvp-sum__big">
        <b><?= e(number_format($ov['avg'], 1)) ?></b>
        <div><span class="rvp-sum__stars" role="img" aria-label="Average <?= e(number_format($ov['avg'], 1)) ?> out of 5"><?= rv_stars($avgR) ?></span>
          <small><?= (int) $ov['n'] ?> rating<?= $ov['n'] === 1 ? '' : 's' ?><?= $ov['photos'] ? ' · ' . (int) $ov['photos'] . ' photo' . ($ov['photos'] === 1 ? '' : 's') : '' ?></small></div>
      </div>
      <ul class="rvp-bars" aria-label="Reviews by star rating">
        <?php $max = max(1, max($facets['stars'])); foreach ([5, 4, 3, 2, 1] as $n): ?>
        <li><a href="<?= e($link(['stars' => $stars === $n ? null : $n, 'page' => null])) ?>"<?= $stars === $n ? ' aria-current="true"' : '' ?>><span><?= $n ?> ★</span><i style="--w:<?= round($facets['stars'][$n] / $max * 100) ?>%"></i><em><?= (int) $facets['stars'][$n] ?></em></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <div class="rvp-filters" role="group" aria-label="Filter reviews">
      <a class="pill<?= !$filtered ? ' is-on' : '' ?>" href="<?= e(url('reviews.php')) ?>">All</a>
      <a class="pill<?= $photos ? ' is-on' : '' ?>" href="<?= e($link(['photos' => $photos ? null : 1, 'page' => null])) ?>">With photos</a>
      <a class="pill<?= $sort === 'top' ? ' is-on' : '' ?>" href="<?= e($link(['sort' => $sort === 'top' ? null : 'top', 'page' => null])) ?>">Highest rated first</a>
      <?php if ($facets['places']): ?>
      <form method="get" action="<?= e(url('reviews.php')) ?>">
        <?php if ($stars): ?><input type="hidden" name="stars" value="<?= $stars ?>"><?php endif; ?>
        <?php if ($photos): ?><input type="hidden" name="photos" value="1"><?php endif; ?>
        <?php if ($sort === 'top'): ?><input type="hidden" name="sort" value="top"><?php endif; ?>
        <label class="visually-hidden" for="rv-place">Place</label>
        <select id="rv-place" name="place" onchange="this.form.submit()">
          <option value="">All places</option>
          <?php foreach ($facets['places'] as $slug => $nm): ?><option value="<?= e($slug) ?>"<?= $place === $slug ? ' selected' : '' ?>><?= e($nm) ?></option><?php endforeach; ?>
        </select>
        <noscript><button class="btn btn--ink btn--sm" type="submit">Apply</button></noscript>
      </form>
      <?php endif; ?>
    </div>

    <?php if ($rows): ?>
    <p class="rvp-count" aria-live="polite">Showing <?= (int) count($rows) ?> of <?= (int) $res['total'] ?> review<?= $res['total'] === 1 ? '' : 's' ?><?= $stars ? ' · ' . $stars . ' star' . ($stars > 1 ? 's' : '') : '' ?><?= $place !== '' ? ' · ' . e($facets['places'][$place] ?? '') : '' ?></p>
    <div class="rvm-grid">
      <?php foreach ($rows as $i => $m) echo rv_card($m, $sizes[$i], $i); ?>
    </div>

    <?php if ($res['pages'] > 1): $cur = $res['page']; $last = $res['pages']; ?>
    <nav class="rvp-pager" aria-label="Review pages">
      <?php if ($cur > 1): ?><a href="<?= e($link(['page' => $cur - 1 > 1 ? $cur - 1 : null])) ?>" rel="prev">← Newer</a><?php endif; ?>
      <?php $shown = array_unique(array_filter([1, $cur - 1, $cur, $cur + 1, $last], fn($n) => $n >= 1 && $n <= $last)); sort($shown); $prevN = 0;
      foreach ($shown as $n): if ($n - $prevN > 1): ?><span class="is-gap" aria-hidden="true">…</span><?php endif; $prevN = $n;
        if ($n === $cur): ?><span aria-current="page"><?= $n ?></span><?php else: ?><a href="<?= e($link(['page' => $n > 1 ? $n : null])) ?>" aria-label="Page <?= $n ?>"><?= $n ?></a><?php endif;
      endforeach; ?>
      <?php if ($cur < $last): ?><a href="<?= e($link(['page' => $cur + 1])) ?>" rel="next">Older →</a><?php endif; ?>
    </nav>
    <?php endif; ?>

    <?php elseif ($filtered): ?>
    <div class="empty-state"><h2>No reviews match those filters</h2><p>Try another star rating or place, or show everything.</p><a class="btn btn--ink btn--sm" href="<?= e(url('reviews.php')) ?>">Show all reviews</a></div>
    <?php else: ?>
    <div class="empty-state"><h2>No reviews yet</h2><p>Be the first to share a favorite moment. Rate a place you loved, add a photo, and it can appear here once staff approve it.</p><a class="btn btn--sun btn--sm" href="<?= e(url('feedback.php')) ?>">Share your moment</a></div>
    <?php endif; ?>

    <div class="rvp-cta" data-reveal>
      <div><h2>Been to Puerto Galera?</h2><p>Add a comment or a photo and it could be featured here after staff review.</p></div>
      <a class="btn btn--sun btn--lg" href="<?= e(url('feedback.php')) ?>">Share your moment <?= icon('arrow', 20) ?></a>
    </div>
  </div>
</section>
<?= rv_dialog() ?>
<script src="<?= e(asset('assets/js/reviews.js')) ?>" defer></script>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
