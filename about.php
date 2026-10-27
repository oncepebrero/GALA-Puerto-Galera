<?php
require __DIR__ . '/includes/bootstrap.php';
$all = all_places(true);
$pub = array_filter($all, fn($p) => $p['published']);
page(['title' => 'About this directory', 'path' => 'about.php', 'nav' => '', 'desc' => 'How GALÁ collects, labels and verifies Puerto Galera tourism information.']);
require ROOT_PATH . '/includes/header.php';
?>
<section class="xhead xhead--ocean">
  <div class="wrap xhead__inner"><div>
    <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <span>About</span></p>
    <h1>About <span class="hand">GALÁ</span></h1>
    <p class="xhead__lede">Explore. Enjoy. Tara Galera! A friendly way to discover Puerto Galera.</p>
  </div></div>
  <svg class="xhead__wave" viewBox="0 0 1440 80" preserveAspectRatio="none" aria-hidden="true"><path d="M0 40C180 0 360 0 540 32s360 40 540 8 240-30 360-8V80H0Z"/></svg>
</section>
<section class="section section--tight">
  <div class="wrap prose">
    <h2>What GALÁ is</h2>
    <p>GALÁ is a student research project, <em>Development of a Web-Based Tourism Discovery System for Attractions &amp; Visitor Feedback in Puerto Galera</em>. It helps visitors find beaches, dive sites, heritage places, waterfalls and recreation, see them on a map, and share their own ratings.</p>
    <h2>Where the information comes from</h2>
    <p>Place names, categories, descriptions, localities, travel estimates and contacts were gathered into a research inventory from <a href="<?= e(cfg('reference_site')) ?>" target="_blank" rel="noopener noreferrer">Experience Puerto Galera</a> and related references. The directory holds <?= count(all_places()) ?> places in four categories.</p>
    <h2>Please double-check before you go</h2>
    <p>Opening hours, fees, boat schedules and access change. Confirm with the operator or the Puerto Galera Tourism Office, whose contacts are on the <a href="<?= e(url('travel.php#contacts')) ?>">travel page</a>.</p>
    <h2>About the pictures</h2>
    <p>Where a real photograph has been supplied it is used; otherwise GALÁ shows an illustration of what the place is about. Illustrations are artwork, not photographs of the exact spot.</p>
    <h2>Respect for communities</h2>
    <p>Mangyan community listings carry no photographs of people, no map pins and no cultural detail. Visits are arranged with the communities themselves.</p>
    <h2>Feedback</h2>
    <p>Ratings and comments are reviewed by staff before they appear. See the <a href="<?= e(url('feedback.php')) ?>">feedback page</a> and its privacy note.</p>
  </div>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
