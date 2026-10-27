<?php
require __DIR__ . '/includes/bootstrap.php';
$events = events_sorted();
$services = require ROOT_PATH . '/data/event_services.php';
$type = (string) ($_GET['type'] ?? '');
$types = array_values(array_unique(array_column($events, 'type')));
page(['title' => 'Notices, events and promotions', 'path' => 'events.php', 'nav' => 'events',
      'desc' => 'Tourism announcements, events and promotions for Puerto Galera, with date and verification status.']);
require ROOT_PATH . '/includes/header.php';
?>
<section class="xhead xhead--sun">
  <div class="wrap xhead__inner"><div>
    <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <span>Events and offers</span></p>
    <h1>What’s <span class="hand">happening</span></h1>
    <p class="xhead__lede">Festivals, workshops, clean-ups and special offers around Puerto Galera. Dates and venues can change, so check with the organizer.</p>
  </div></div>
  <svg class="xhead__wave" viewBox="0 0 1440 80" preserveAspectRatio="none" aria-hidden="true"><path d="M0 40C180 0 360 0 540 32s360 40 540 8 240-30 360-8V80H0Z"/></svg>
</section>
<section class="section section--tight">
  <div class="wrap">
    <?php if (count($types) > 1): ?>
    <div class="chipbar" role="group" aria-label="Filter by type">
      <a class="pill<?= $type === '' ? ' is-on' : '' ?>" href="<?= e(url('events.php')) ?>">All</a>
      <?php foreach ($types as $t): ?><a class="pill<?= $type === $t ? ' is-on' : '' ?>" href="<?= e(url('events.php', ['type' => $t])) ?>"><?= e($t) ?></a><?php endforeach; ?>
    </div>
    <?php endif; ?>
    <ol class="evgrid">
      <?php $n = 0; foreach ($events as $ev): if ($type && $ev['type'] !== $type) continue; $n++; $st = event_status($ev); ?>
      <li class="evcard evcard--<?= e($st['class']) ?>" data-reveal style="--d:<?= min($n, 6) * 60 ?>ms">
        <div class="evcard__date" aria-hidden="true">
          <?php if ($ev['date']): ?><b><?= e(date('j', strtotime($ev['date']))) ?></b><span><?= e(date('M Y', strtotime($ev['date']))) ?></span><?php if (!empty($ev['end']) && $ev['end'] !== $ev['date']): ?><em>to <?= e(date('j M', strtotime($ev['end']))) ?></em><?php endif; ?><?php else: ?><b>TBA</b><?php endif; ?>
        </div>
        <div class="evcard__body">
          <p class="evcard__top"><span class="status status--<?= e($st['class']) ?>"><?= e($st['label']) ?></span><span class="ev__type"><?= e($ev['type']) ?></span><?php if (!empty($ev['verified'])): ?><span class="ev__verified" title="Staff have confirmed these details with the organizer"><?= icon('check', 14) ?> Verified</span><?php else: ?><span class="ev__unverified" title="Not yet confirmed by staff. Check with the organizer">Check with organizer</span><?php endif; ?></p>
          <h2><?= e($ev['title']) ?></h2>
          <p><?= e($ev['text']) ?></p>
          <dl class="tl__meta">
            <div><dt>When</dt><dd><?php if ($ev['date']): ?><time datetime="<?= e($ev['date']) ?>"><?= e(nice_date($ev['date'])) ?></time><?= !empty($ev['end']) && $ev['end'] !== $ev['date'] ? ' to ' . e(nice_date($ev['end'])) : '' ?><?php else: ?>To be announced<?php endif; ?></dd></div>
            <div><dt>Where</dt><dd><?= e($ev['venue'] ?: 'To be announced') ?></dd></div>
            <div><dt>Organizer</dt><dd><?= e($ev['organizer'] ?: 'To be announced') ?></dd></div>
          </dl>
          <?php if (!empty($ev['link'])): ?><p class="evcard__link"><a href="<?= e($ev['link']) ?>" rel="noopener noreferrer" target="_blank">More information <?= icon('arrow', 16) ?></a></p><?php endif; ?>
        </div>
      </li>
      <?php endforeach; if (!$n): ?>
      <li class="empty-state"><h2>Nothing of that type right now</h2><p>Try another type or show everything.</p><a class="btn btn--ink btn--sm" href="<?= e(url('events.php')) ?>">Show all</a></li>
      <?php endif; ?>
    </ol>

    <h2 class="h-sub">Celebrations, weddings and business events</h2>
    <p class="muted">Planning something bigger? Puerto Galera hosts these kinds of gatherings. Ask the Tourism Office for venue and operator contacts.</p>
    <ul class="service-list">
      <?php foreach ($services as $s): ?><li><h3><?= e($s['title']) ?></h3><p><?= e($s['text']) ?></p></li><?php endforeach; ?>
    </ul>
    <p class="muted small footnote">Events marked <strong>Verified</strong> have been confirmed by staff with the organizer. For the others, please check the date and venue with the organizer before you travel.</p>
  </div>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
