<?php
if (!defined('ROOT_PATH')) require __DIR__ . '/includes/bootstrap.php';
http_response_code(404);
page(['title' => 'Page not found', 'path' => '404.php', 'nav' => '', 'noindex' => true]);
require ROOT_PATH . '/includes/header.php';
?>
<section class="xhead"><div class="wrap xhead__inner"><div>
  <h1>Wrong turn, <span class="hand">kabayan!</span></h1>
  <p class="xhead__lede">The link may be old, or the place may have been renamed. Try searching instead.</p>
  <p><a class="btn btn--sun" href="<?= e(url('explore.php')) ?>">Search all places</a> <a class="btn btn--glass" href="<?= e(url()) ?>">Go home</a></p>
  </div></div>
  <svg class="xhead__wave" viewBox="0 0 1440 80" preserveAspectRatio="none" aria-hidden="true"><path d="M0 40C180 0 360 0 540 32s360 40 540 8 240-30 360-8V80H0Z"/></svg>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
