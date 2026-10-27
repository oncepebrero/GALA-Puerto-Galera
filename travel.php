<?php
require __DIR__ . '/includes/bootstrap.php';
$tr = require ROOT_PATH . '/data/travel.php';
$faqs = require ROOT_PATH . '/data/faqs.php';
$c = $tr['contact'];
page(['title' => 'Travel guide and visitor help', 'path' => 'travel.php', 'nav' => 'travel',
      'desc' => 'How to get to Puerto Galera from Manila, typical fares and fees, local transport, tourism-office contacts and frequently asked questions.',
      'schema' => ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(fn($f) => ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]], $faqs)]]);
require ROOT_PATH . '/includes/header.php';
?>
<section class="xhead xhead--ocean">
  <div class="wrap xhead__inner"><div>
    <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <span>Travel guide</span></p>
    <h1>Getting to <span class="hand">Puerto Galera</span></h1>
    <p class="xhead__lede">From Manila by bus, across the water by ferry, then a short ride to your beach. Here is the usual route.</p>
  </div></div>
  <svg class="xhead__wave" viewBox="0 0 1440 80" preserveAspectRatio="none" aria-hidden="true"><path d="M0 40C180 0 360 0 540 32s360 40 540 8 240-30 360-8V80H0Z"/></svg>
</section>
<section class="section section--tight">
  <div class="wrap">
    <div class="callout callout--care" role="note"><?= icon('info', 22) ?><div><strong>Good to know.</strong><p>Fares, fees, operators and schedules can change, especially on weekends and holidays. Confirm amounts with the operator or the Puerto Galera Tourism Office before you pay or book.</p></div></div>

    <ol class="steps">
      <?php foreach ($tr['steps'] as $i => $s): ?>
      <li class="step">
        <div class="step__ico"><?= icon($s['icon'], 28) ?></div>
        <div class="step__body">
          <h2><?= e($s['title']) ?></h2>
          <p><?= e($s['text']) ?></p>
          <dl class="step__facts"><?php foreach ($s['facts'] as [$k, $v]): ?><div><dt><?= e($k) ?></dt><dd><?= e($v) ?></dd></div><?php endforeach; ?></dl>
        </div>
      </li>
      <?php endforeach; ?>
    </ol>

    <div class="two-col">
      <div>
        <h2 class="h-sub">Where it is</h2>
        <dl class="deflist"><?php foreach ($tr['geo'] as [$k, $v]): ?><div><dt><?= e($k) ?></dt><dd><?= e($v) ?></dd></div><?php endforeach; ?></dl>
        <a class="btn btn--line btn--sm" href="<?= e(url('map.php')) ?>"><?= icon('pin', 16) ?> See the map</a>
      </div>
      <div id="contacts">
        <h2 class="h-sub">Tourism contacts</h2>
        <dl class="deflist">
          <div><dt>Telephone</dt><dd><?php foreach ($c['phones'] as $i => $ph): ?><?= $i ? ' / ' : '' ?><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $ph)) ?>"><?= e($ph) ?></a><?php endforeach; ?></dd></div>
          <div><dt>Email</dt><dd><?php foreach ($c['emails'] as $i => $em): ?><?= $i ? '<br>' : '' ?><a href="mailto:<?= e($em) ?>"><?= e($em) ?></a><?php endforeach; ?></dd></div>
          <div><dt>Facebook</dt><dd><a href="<?= e($c['facebook']) ?>" target="_blank" rel="noopener noreferrer">Experience Puerto Galera</a></dd></div>
          <div><dt>About page</dt><dd><a href="<?= e($c['about']) ?>" target="_blank" rel="noopener noreferrer">experiencepuertogalera.com/about</a></dd></div>
        </dl>
        <p class="muted small"><?= e($c['note']) ?></p>
      </div>
    </div>

    <h2 class="h-sub">Visitor assistance</h2>
    <ul class="assist">
      <?php foreach ($tr['assistance'] as [$t, $d]): ?><li><h3><?= e($t) ?></h3><p><?= e($d) ?></p></li><?php endforeach; ?>
    </ul>

    <h2 class="h-sub" id="faq">Frequently asked questions</h2>
    <div class="faq">
      <?php foreach ($faqs as $f): ?>
      <details><summary><?= e($f['q']) ?></summary><p><?= e($f['a']) ?></p></details>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
