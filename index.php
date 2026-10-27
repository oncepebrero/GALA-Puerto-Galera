<?php
require __DIR__ . '/includes/bootstrap.php';

$cats = categories();
$all = all_places();
$beaches = places_in('beach');
$dives = places_in('diving');
$culture = places_in('culture');
$nature = array_values(array_filter(places_in('nature'), fn($p) => $p['type'] === 'Nature'));
$recreation = array_values(array_filter(places_in('nature'), fn($p) => $p['type'] === 'Recreation'));
$events = array_slice(array_values(array_filter(events_sorted(), fn($e) => event_status($e)['class'] !== 'past')), 0, 3);
$travel = require ROOT_PATH . '/data/travel.php';
$notes = feedback_approved(null, 3);

page([
    'title' => '', 'path' => '', 'class' => 'home has-hero', 'hero_preload' => true, 'nav' => '',
    'desc' => 'Explore. Enjoy. Tara Galera! Search ' . count($all) . ' beaches, dive sites, heritage places, waterfalls and recreation spots in Puerto Galera, Oriental Mindoro, see them on a map and share your own rating.',
    'schema' => [
        '@context' => 'https://schema.org', '@type' => 'TouristDestination', 'name' => 'Puerto Galera',
        'description' => 'Coastal destination on the northern coast of Mindoro Island, Oriental Mindoro, Philippines, with beaches, diving, heritage and nature-based tourism.',
        'containedInPlace' => ['@type' => 'AdministrativeArea', 'name' => 'Oriental Mindoro, MIMAROPA, Philippines'], 'url' => abs_url(),
    ],
]);

function img_of(string $slug, string $size = 'src'): string { $p = place_by_slug($slug); return $p ? place_image($p, $size)['src'] : ''; }

$panels = [
    'beach' => ['minolo-beach', 'Sand between your toes'],
    'diving' => ['coral-garden', 'Reefs, walls and wrecks'],
    'culture' => ['escarceo-point-lighthouse', 'Stories worth a stop'],
    'nature' => ['tukuran-falls', 'Falls, caves and fun'],
];
$stackSlugs = ['minolo-beach', 'coral-garden', 'tukuran-falls', 'escarceo-point-lighthouse', 'the-verde-island-passage'];
$stack = [];
foreach ($stackSlugs as $sl) { if ($pp = place_by_slug($sl)) $stack[] = $pp; }
$trips = [
    ['Beach-hopping day', 'Hop between coves, swim and eat by the sea.', 'aqua', ['minolo-beach', 'banana-beach', 'small-tabinay-beach', 'encenada-beach']],
    ['Dive weekend', 'Wrecks, walls and the Verde Island Passage.', 'ocean', ['coral-garden', 'japanese-wreck', 'shark-cave', 'the-verde-island-passage']],
    ['Falls and springs', 'Trek, splash and soak in warm water.', 'palm', ['tukuran-falls', 'tamaraw-falls', 'talipanan-falls', 'tabinay-hot-spring']],
    ['Heritage stroll', 'Waterfront, church, museum and a lighthouse.', 'sun', ['muelle-cultural-and-heritage-park', 'immaculate-conception-church', 'excavation-museum', 'escarceo-point-lighthouse']],
];
$suggestIndex = array_map(fn($p) => ['n' => $p['name'], 's' => $p['slug'], 'c' => $cats[$p['cat']]['label'], 'a' => area_label($p)], $all);

require ROOT_PATH . '/includes/header.php';
?>
<link rel="stylesheet" href="<?= e(asset('assets/css/home.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/reviews.css')) ?>">
<div class="hm-progress" aria-hidden="true"><i></i></div>

<section class="hero" id="top" aria-labelledby="hero-title">
  <div class="hero__media"><img src="<?= e(asset(cfg('hero_image'))) ?>" srcset="<?= e(asset(cfg('hero_image_small'))) ?> 1200w, <?= e(asset(cfg('hero_image'))) ?> 1672w" sizes="100vw" alt="A turquoise bay with banca boats, a sandy beach and green hills in Puerto Galera" width="1672" height="941" fetchpriority="high"></div>
  <div class="hero__shade" aria-hidden="true"></div>

  <div class="wrap hero__inner">
    <p class="hero__eyebrow"><span></span>Puerto Galera · Oriental Mindoro · Philippines</p>
    <h1 class="hero__title" id="hero-title" aria-label="Explore. Enjoy. Tara Galera!">
      <span class="hl hl--1" aria-hidden="true">Explore.</span>
      <span class="hl hl--2" aria-hidden="true">Enjoy.</span>
      <span class="hl hl--3 hand" aria-hidden="true">Tara Galera!</span>
    </h1>
    <p class="hero__lede">Powder-white sand, glass-clear reefs, hidden waterfalls and centuries of stories, all waiting on the shores of one of the world’s most beautiful bays. Find your perfect place and go.</p>

    <form class="hero__search" action="<?= e(url('explore.php')) ?>" method="get" role="search" data-suggest>
      <label class="visually-hidden" for="hs-q">Search places in Puerto Galera</label>
      <?= icon('search', 22) ?>
      <input id="hs-q" name="q" type="search" autocomplete="off" placeholder="Where to? Try “wreck”, “Sabang” or “waterfall”" role="combobox" aria-expanded="false" aria-controls="hs-list" aria-autocomplete="list" maxlength="80">
      <button class="btn btn--sun" type="submit">Start exploring</button>
      <ul class="suggest" id="hs-list" role="listbox" hidden></ul>
    </form>
    <div class="hero__quick" aria-label="Quick links">
      <a href="<?= e(url('explore.php', ['cat' => 'beach'])) ?>">Beaches</a>
      <a href="<?= e(url('explore.php', ['cat' => 'diving'])) ?>">Diving</a>
      <a href="<?= e(url('explore.php', ['cat' => 'nature'])) ?>">Waterfalls and trails</a>
      <a href="<?= e(url('explore.php', ['cat' => 'culture'])) ?>">Heritage</a>
      <button type="button" data-surprise><?= icon('shuffle', 16) ?> Surprise me</button>
    </div>
  </div>

  <dl class="hero__stats" aria-label="Puerto Galera at a glance">
    <div><dt>Places to discover</dt><dd data-count="<?= (int) count($all) ?>"><?= e(count($all)) ?></dd></div>
    <div><dt>Beaches</dt><dd data-count="<?= (int) count($beaches) ?>"><?= e(count($beaches)) ?></dd></div>
    <div><dt>Dive sites</dt><dd data-count="<?= (int) count($dives) ?>"><?= e(count($dives)) ?></dd></div>
    <div><dt>Heritage sites</dt><dd data-count="<?= (int) count($culture) ?>"><?= e(count($culture)) ?></dd></div>
  </dl>

  <svg class="hero__waves" viewBox="0 0 1440 120" preserveAspectRatio="none" aria-hidden="true">
    <path class="w w1" d="M0 70C200 20 380 20 560 56s340 40 520 4 260-36 360-14V120H0Z"/>
    <path class="w w3" d="M0 98C220 66 400 66 580 92s320 20 500 0 260-20 360 0V120H0Z"/>
  </svg>
</section>

<div class="hm">

<!-- WELCOME -->
<section class="hm-intro" aria-labelledby="intro-title">
  <div class="hm-w hm-intro__grid">
    <div class="hm-intro__text">
      <p class="hm-eyebrow" data-reveal>Welcome to Puerto Galera</p>
      <h2 class="hm-mega" id="intro-title" data-reveal>One bay. Endless ways to <span class="hand mark">fall in love.</span></h2>
      <p class="hm-lead" data-reveal>Swim in water so clear you can count the corals, chase waterfalls through the jungle, dive into history and end every day with a sunset worth the trip. We’ve done the picking, so you can just go.</p>
      <div class="hm-actions" data-reveal>
        <a class="btn btn--sun btn--lg" href="<?= e(url('explore.php')) ?>">Explore all <?= count($all) ?> places <?= icon('arrow', 20) ?></a>
        <a class="btn btn--line btn--lg" href="<?= e(url('travel.php')) ?>">How to get here</a>
      </div>
    </div>
    <div class="hm-intro__photos" aria-hidden="true">
      <img class="hm-ph hm-ph--1" src="<?= e(img_of('minolo-beach')) ?>" alt="" width="640" height="800" loading="lazy">
      <img class="hm-ph hm-ph--2" src="<?= e(img_of('coral-garden')) ?>" alt="" width="520" height="520" loading="lazy">
      <span class="hm-ph__badge"><b><?= count($all) ?></b>places<br>to love</span>
    </div>
  </div>
</section>

<!-- CHOOSE: expanding panels -->
<section class="hm-choose" id="choose" aria-labelledby="choose-title">
  <div class="hm-w">
    <header class="hm-head" data-reveal>
      <p class="hm-eyebrow">Choose your adventure</p>
      <h2 class="hm-mega" id="choose-title">What kind of <span class="hand mark">day</span> do you want?</h2>
    </header>
  </div>
  <div class="hm-w">
    <div class="hm-acc" data-acc>
      <?php $n = 0; foreach ($cats as $k => $c): [$slug, $line] = $panels[$k]; $list = array_slice(places_in($k), 0, 4); ?>
      <a class="hm-pane<?= $n === 0 ? ' is-on' : '' ?>" href="<?= e(url('explore.php', ['cat' => $k])) ?>">
        <img src="<?= e(img_of($slug)) ?>" alt="" width="1200" height="1400" loading="lazy">
        <span class="hm-pane__v" aria-hidden="true"><b><?= str_pad((string) ($n + 1), 2, '0', STR_PAD_LEFT) ?></b><span><?= e($c['label']) ?></span></span>
        <span class="hm-pane__in">
          <span class="hm-pane__count"><?= count_in($k) ?> places</span>
          <span class="hm-pane__name"><?= e($c['label']) ?></span>
          <span class="hm-pane__tag"><?= e($line) ?></span>
          <span class="hm-pane__list"><?php foreach ($list as $p): ?><span><?= e($p['name']) ?></span><?php endforeach; ?></span>
          <span class="hm-pane__go">Explore <?= icon('arrow', 22) ?></span>
        </span>
      </a>
      <?php $n++; endforeach; ?>
    </div>
  </div>
</section>

<!-- MUST-SEE -->
<?php if ($stack): ?>
<section class="hm-top" id="highlights" aria-labelledby="top-title">
  <div class="hm-w">
    <header class="hm-head hm-head--row" data-reveal>
      <div>
        <p class="hm-eyebrow">Top picks</p>
        <h2 class="hm-mega" id="top-title">Must-see <span class="hand mark">places</span></h2>
      </div>
      <a class="btn btn--ink" href="<?= e(url('explore.php')) ?>">See every place <?= icon('arrow', 18) ?></a>
    </header>
    <div class="hm-top__grid">
      <?php foreach ($stack as $i => $p): $im = place_image($p, 'src'); ?>
      <a class="hm-pick hm-pick--<?= $i + 1 ?>" href="<?= e(url('place.php', ['slug' => $p['slug']])) ?>" data-reveal style="--d:<?= $i * 70 ?>ms">
        <img src="<?= e($im['src']) ?>" alt="" width="1000" height="800" loading="lazy">
        <span class="hm-pick__rank" aria-hidden="true">#<?= $i + 1 ?></span>
        <span class="hm-pick__txt">
          <small><?= e($cats[$p['cat']]['single']) ?> · <?= e(area_label($p)) ?></small>
          <strong><?= e($p['name']) ?></strong>
          <span class="hm-pick__sum"><?= e($p['summary']) ?></span>
          <span class="hm-pick__go">Discover <?= icon('arrow', 18) ?></span>
        </span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- TRIP RAIL -->
<section class="hm-rail" id="plan" aria-labelledby="trips-title">
  <div class="hm-w hm-rail__head">
    <header class="hm-head hm-head--light" data-reveal>
      <p class="hm-eyebrow">Ready-made trips</p>
      <h2 class="hm-mega" id="trips-title">Not sure where to start? <span class="hand mark">Pick a vibe.</span></h2>
    </header>
    <div class="hm-rail__btns" aria-hidden="true"><button type="button" data-rail="-1" tabindex="-1" aria-label="Previous"><?= icon('arrow', 22) ?></button><button type="button" data-rail="1" tabindex="-1" aria-label="Next"><?= icon('arrow', 22) ?></button></div>
  </div>
  <div class="hm-rail__track" tabindex="0" role="region" aria-label="Trip ideas, scroll sideways" data-rail-track>
    <?php foreach ($trips as $i => [$title, $line, $tone, $slugs]):
        $first = place_by_slug($slugs[0]); $tim = $first ? place_image($first, 'src')['src'] : ''; ?>
    <article class="hm-trip hm-trip--<?= e($tone) ?>">
      <img src="<?= e($tim) ?>" alt="" width="900" height="1200" loading="lazy">
      <div class="hm-trip__in">
        <span class="hm-trip__n"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
        <h3><?= e($title) ?></h3>
        <p><?= e($line) ?></p>
        <ol>
          <?php foreach ($slugs as $s): $pp = place_by_slug($s); if (!$pp) continue; ?>
          <li><a href="<?= e(url('place.php', ['slug' => $s])) ?>"><?= e($pp['name']) ?><?= icon('arrow', 18) ?></a></li>
          <?php endforeach; ?>
        </ol>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
</section>

<!-- FAVORITE MOMENTS (approved visitor ratings) -->
<?php $moments = feedback_moments(14); $momSizes = rv_sizes($moments); $ov = feedback_overview(); ?>
<section class="hm-moments rvm" id="moments" aria-labelledby="mom-title">
  <div class="hm-w">
    <header class="hm-head hm-head--row" data-reveal>
      <div>
        <p class="hm-eyebrow">Straight from our visitors</p>
        <h2 class="hm-mega" id="mom-title">Favorite moments from our <span class="hand mark">recent visitors</span></h2>
      </div>
      <?php if ($ov['avg'] !== null): ?>
      <div class="hm-score" aria-label="Average visitor rating <?= e(number_format($ov['avg'], 1)) ?> out of 5">
        <b><?= e(number_format($ov['avg'], 1)) ?></b>
        <span><span class="hm-score__stars" aria-hidden="true"><?= str_repeat('★', (int) round($ov['avg'])) ?><i><?= str_repeat('★', 5 - (int) round($ov['avg'])) ?></i></span>
        <small><?= (int) $ov['n'] ?> rating<?= $ov['n'] === 1 ? '' : 's' ?><?= $ov['photos'] ? ' · ' . (int) $ov['photos'] . ' photo' . ($ov['photos'] === 1 ? '' : 's') : '' ?></small></span>
      </div>
      <?php endif; ?>
    </header>

    <?php if ($moments): ?>
  </div>
  <div class="rvm-strip" data-rvm-strip>
    <div class="rvm-track" data-rvm-track tabindex="0" role="region" aria-label="Visitor moments, scroll sideways">
      <?php foreach ($moments as $i => $m) echo rv_card($m, $momSizes[$i], $i, false); ?>
    </div>
    <div class="rvm-nav">
      <button type="button" data-rvm="-1" aria-label="Previous moments" disabled><?= icon('arrow', 22) ?></button>
      <button type="button" data-rvm="1" aria-label="More moments"><?= icon('arrow', 22) ?></button>
      <a class="btn btn--ink rvm-nav__all" href="<?= e(url('reviews.php')) ?>">View all reviews <?= icon('arrow', 18) ?></a>
    </div>
  </div>
  <div class="hm-w">
    <?php else: ?>
    <div class="hm-mom__empty" data-reveal><p><strong>Be the first to share a favorite moment.</strong> Rate a place you loved, add a photo, and it can appear right here once staff approve it.</p></div>
    <?php endif; ?>

    <div class="hm-rate" data-reveal>
      <div><h3>Been to Puerto Galera?</h3><p>Tap a star to rate your visit. Add a comment or a photo and it could be featured here.</p></div>
      <div class="hm-rate__stars" role="group" aria-label="Rate your visit">
        <?php for ($i = 5; $i >= 1; $i--): ?><a href="<?= e(url('feedback.php', ['rating' => $i])) ?>" title="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>"><span class="visually-hidden"><?= $i ?> star<?= $i > 1 ? 's' : '' ?></span>★</a><?php endfor; ?>
      </div>
      <a class="btn btn--sun btn--lg" href="<?= e(url('feedback.php')) ?>">Share your moment <?= icon('arrow', 20) ?></a>
    </div>
  </div>
</section>

<!-- PLAN + EVENTS -->
<section class="hm-plan" aria-labelledby="plan-title">
  <div class="hm-w">
    <header class="hm-head" data-reveal>
      <p class="hm-eyebrow">Plan your visit</p>
      <h2 class="hm-mega" id="plan-title">Manila to the shore, <span class="hand mark">step by step.</span></h2>
    </header>

    <div class="hm-ticket" data-reveal>
      <div class="hm-ticket__top">
        <span class="hm-ticket__end"><small>From</small><b>Manila</b></span>
        <span class="hm-ticket__route" aria-hidden="true"><i></i><em><?= icon('ferry', 22) ?></em><i></i></span>
        <span class="hm-ticket__end hm-ticket__end--r"><small>To</small><b>Puerto Galera</b></span>
      </div>
      <ol class="hm-ticket__steps">
        <?php foreach ($travel['steps'] as $i => $s): ?>
        <li>
          <span class="hm-ticket__n"><?= $i + 1 ?></span>
          <span class="hm-ico"><?= icon($s['icon'], 24) ?></span>
          <h3><?= e($s['title']) ?></h3>
          <dl><?php foreach ($s['facts'] as [$k, $v]): ?><div><dt><?= e($k) ?></dt><dd><?= e($v) ?></dd></div><?php endforeach; ?></dl>
        </li>
        <?php endforeach; ?>
      </ol>
      <div class="hm-ticket__foot">
        <p>Amounts are estimates. Please confirm with the operators.</p>
        <a class="btn btn--ink" href="<?= e(url('travel.php')) ?>">Full travel guide <?= icon('arrow', 18) ?></a>
      </div>
    </div>

    <div class="hm-agenda" data-reveal>
      <header class="hm-agenda__head">
        <div>
          <p class="hm-eyebrow hm-eyebrow--sun">On the calendar</p>
          <h2 class="hm-mega hm-mega--sm">What’s <span class="hand">happening</span></h2>
        </div>
        <a class="btn btn--glass" href="<?= e(url('events.php')) ?>">All events and offers <?= icon('arrow', 18) ?></a>
      </header>
      <ul class="hm-agenda__list">
        <?php foreach ($events as $ev): $st = event_status($ev); ?>
        <li><a class="hm-ev" href="<?= e(url('events.php')) ?>">
          <span class="hm-ev__date"><?php if ($ev['date']): ?><b><?= e(date('j', strtotime($ev['date']))) ?></b><small><?= e(date('M', strtotime($ev['date']))) ?></small><?php else: ?><b>TBA</b><?php endif; ?></span>
          <span class="hm-ev__body"><strong><?= e($ev['title']) ?></strong><?php if (!empty($ev['venue'])): ?><small><?= icon('pin', 14) ?> <?= e($ev['venue']) ?></small><?php endif; ?></span>
          <span class="hm-ev__st"><?= e($st['label']) ?></span>
          <span class="hm-ev__go" aria-hidden="true"><?= icon('arrow', 20) ?></span>
        </a></li>
        <?php endforeach; ?>
        <?php if (!$events): ?><li class="hm-agenda__empty">No upcoming events listed yet. Check back soon!</li><?php endif; ?>
      </ul>
    </div>
  </div>
</section>

<!-- CLOSING -->
<section class="hm-cta" aria-labelledby="cta-title">
  <img class="hm-cta__bg" data-parallax="0.08" src="<?= e(asset(cfg('hero_image'))) ?>" alt="" width="1672" height="941" loading="lazy">
  <div class="hm-cta__shade" aria-hidden="true"></div>
  <div class="hm-w hm-cta__inner">
    <p class="hm-eyebrow hm-eyebrow--sun" data-reveal>Ready when you are</p>
    <h2 class="hm-cta__title" id="cta-title" data-reveal>Your Galera is <span class="hand">waiting.</span></h2>
    <p class="hm-cta__sub" data-reveal>Pack light, bring a smile and let Puerto Galera do the rest.</p>
    <div class="hm-actions" data-reveal>
      <a class="btn btn--sun btn--lg" href="<?= e(url('explore.php')) ?>">Start exploring <?= icon('arrow', 22) ?></a>
      <button class="btn btn--glass btn--lg" type="button" data-surprise><?= icon('shuffle', 20) ?> Surprise me</button>
    </div>
    <?php if ($notes): ?>
    <div class="hm-voices" aria-label="Traveler notes">
      <?php foreach ($notes as $f): ?>
      <blockquote data-reveal><p>“<?= e($f['comment']) ?>”</p>
        <footer><?= $f['rating'] ? stars_html((float) $f['rating'], 1) . ' ' : '' ?><a href="<?= e(url('place.php', ['slug' => $f['place_slug']])) ?>"><?= e($f['place_name']) ?></a> · <?= e($f['nickname'] ?: 'A visitor') ?></footer></blockquote>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <p class="hm-cta__care"><?= icon('leaf', 20) ?> Love it, leave it better. <a href="<?= e(url('responsible.php')) ?>">Responsible travel guide</a> · <a href="<?= e(url('feedback.php')) ?>">Rate a place</a></p>
  </div>
</section>

</div>

<script type="application/json" id="gala-suggest"><?= json_embed($suggestIndex) ?></script>
<?= rv_dialog() ?>
<script src="<?= e(asset('assets/js/home.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/reviews.js')) ?>" defer></script>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
