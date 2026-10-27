<?php
require __DIR__ . '/includes/bootstrap.php';
page(['title' => 'Responsible tourism', 'path' => 'responsible.php', 'nav' => 'responsible',
      'desc' => 'How to visit Puerto Galera responsibly: protect reefs, leave no trace, respect communities and follow local rules.']);
$blocks = [
  ['Reefs and marine life', 'wave', ['Do not touch, stand on or collect coral, shells or marine life.', 'Keep fins, gauges and cameras clear of the reef, and do not feed fish.', 'Choose operators who brief you on reef protection and who limit group sizes.', 'Use only what your operator allows near turtles, sharks and other animals. Keep your distance.']],
  ['Beaches, trails and falls', 'leaf', ['Carry out all litter, including food scraps and cigarette butts.', 'Stay on marked paths and follow your guide.', 'Keep soap, shampoo and sunscreen out of rivers, pools and falls where you can.', 'Check weather first. Rivers and trails can become dangerous quickly after rain.']],
  ['Communities and culture', 'pin', ['Ask before photographing people, homes, ceremonies or sacred places, and accept a no.', 'Visit Mangyan communities only by arrangement with their representatives or the Tourism Office.', 'Dress and behave respectfully at churches, memorials and heritage sites.', 'Do not treat traditions as entertainment.']],
  ['Local rules and local business', 'ticket', ['Register and pay required fees. The reference site mentions PG PASS and an environmental fee; confirm current rules with the Tourism Office.', 'Use accredited guides and operators where possible.', 'Buy from local businesses and pay fair prices.', 'Report problems, unsafe practices or damage to the Tourism Office.']],
];
require ROOT_PATH . '/includes/header.php';
?>
<section class="xhead xhead--green">
  <div class="wrap xhead__inner"><div>
    <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <span>Responsible tourism</span></p>
    <h1>Love it. <span class="hand">Leave it better.</span></h1>
    <p class="xhead__lede">Small habits that keep reefs, beaches, forests and communities thriving. Love it, then leave it better.</p>
  </div></div>
  <svg class="xhead__wave" viewBox="0 0 1440 80" preserveAspectRatio="none" aria-hidden="true"><path d="M0 40C180 0 360 0 540 32s360 40 540 8 240-30 360-8V80H0Z"/></svg>
</section>
<section class="section section--tight">
  <div class="wrap">
    <div class="callout callout--care" role="note"><?= icon('info', 22) ?><div><strong>Local rules come first.</strong><p>Specific rules, fees and protected-area requirements are set by the municipality and national agencies. Ask the Puerto Galera Tourism Office for the current rules.</p></div></div>
    <div class="care-page">
      <?php foreach ($blocks as [$t, $ic, $items]): ?>
      <section class="care-block"><h2><?= icon($ic, 26) ?> <?= e($t) ?></h2>
        <ul class="ticks"><?php foreach ($items as $i): ?><li><?= icon('check', 18) ?> <span><?= e($i) ?></span></li><?php endforeach; ?></ul>
      </section>
      <?php endforeach; ?>
    </div>
    <p class="cta-line">Saw something that should be fixed, or a practice that harms a place? <a class="textlink" href="<?= e(url('feedback.php')) ?>">Send a concern <?= icon('arrow', 16) ?></a></p>
  </div>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
