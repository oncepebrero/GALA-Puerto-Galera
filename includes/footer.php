</main>
<?php $tr = require ROOT_PATH . '/data/travel.php'; $c = $tr['contact']; ?>
<footer class="site-footer">
  <div class="site-footer__big" aria-hidden="true"><span>TARA GALERA!</span></div>
  <div class="wrap footer__grid">
    <div class="footer__brand">
      <img src="<?= e(asset('assets/img/brand/gala-logo.png')) ?>" alt="GALÁ" width="160" height="160" loading="lazy" class="footer__logo">
      <p><strong>Explore. Enjoy. <span class="hand">Tara Galera!</span></strong><br>Beaches, reefs, heritage and trails of the northern coast of Mindoro.</p>
    </div>
    <nav class="footer__col" aria-label="Explore">
      <h2>Explore</h2>
      <ul>
        <?php foreach (categories() as $k => $cat): ?><li><a href="<?= e(url('explore.php', ['cat' => $k])) ?>"><?= e($cat['label']) ?></a></li><?php endforeach; ?>
        <li><a href="<?= e(url('map.php')) ?>">Interactive map</a></li>
      </ul>
    </nav>
    <nav class="footer__col" aria-label="Plan your visit">
      <h2>Plan your visit</h2>
      <ul>
        <li><a href="<?= e(url('travel.php')) ?>">Getting there</a></li>
        <li><a href="<?= e(url('travel.php#faq')) ?>">FAQs</a></li>
        <li><a href="<?= e(url('events.php')) ?>">Events and offers</a></li>
        <li><a href="<?= e(url('responsible.php')) ?>">Responsible travel</a></li>
        <li><a href="<?= e(url('reviews.php')) ?>">Visitor reviews</a></li>
        <li><a href="<?= e(url('feedback.php')) ?>">Ratings and feedback</a></li>
        <li><a href="<?= e(url('about.php')) ?>">About GALÁ</a></li>
      </ul>
    </nav>
    <div class="footer__col">
      <h2>Tourism contacts</h2>
      <ul class="footer__contact">
        <?php foreach ($c['phones'] as $ph): ?><li><?= icon('phone', 16) ?> <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $ph)) ?>"><?= e($ph) ?></a></li><?php endforeach; ?>
        <?php foreach ($c['emails'] as $em): ?><li><?= icon('mail', 16) ?> <a href="mailto:<?= e($em) ?>"><?= e($em) ?></a></li><?php endforeach; ?>
        <li><?= icon('external', 16) ?> <a href="<?= e($c['facebook']) ?>" rel="noopener noreferrer" target="_blank">Experience Puerto Galera on Facebook</a></li>
      </ul>
    </div>
  </div>
  <div class="wrap footer__legal">
    <p>GALÁ is a student research project, not an official municipal website. Information was gathered from <a href="<?= e(cfg('reference_site')) ?>" rel="noopener noreferrer" target="_blank">Experience Puerto Galera</a>. Times, fees and access can change, so please confirm with the operator or the Puerto Galera Tourism Office before you go. Pictures are illustrations unless a photo credit says otherwise.</p>
  </div>
</footer>

<div class="gali" id="gali" data-api="<?= e(url('api/gali.php')) ?>">
  <button class="gali__fab" type="button" aria-expanded="false" aria-controls="gali-panel">
    <span class="gali__avatar"><img src="<?= e(asset('assets/img/brand/gala-emblem.png')) ?>" alt="" width="34" height="34"></span>
    <span class="gali__fab-text">Ask Gali</span>
    <span class="gali__ping" aria-hidden="true"></span>
  </button>
  <section class="gali__panel" id="gali-panel" role="dialog" aria-label="Ask Gali, your Puerto Galera guide" hidden>
    <header class="gali__head">
      <span class="gali__avatar gali__avatar--lg"><img src="<?= e(asset('assets/img/brand/gala-emblem.png')) ?>" alt="" width="40" height="40"></span>
      <div><strong>Gali</strong><small><span class="gali__dot"></span> Your Galera guide</small></div>
      <button class="gali__close" type="button" aria-label="Close chat"><?= icon('close', 20) ?></button>
    </header>
    <div class="gali__log" role="log" aria-live="polite" aria-label="Conversation with Gali" tabindex="0"></div>
    <div class="gali__chips" aria-label="Quick questions">
      <button type="button">Best beaches</button><button type="button">Dive sites</button><button type="button">Waterfalls</button><button type="button">How do I get there?</button><button type="button">What's on?</button><button type="button">Surprise me</button>
    </div>
    <form class="gali__form" autocomplete="off">
      <label class="visually-hidden" for="gali-q">Ask Gali a question</label>
      <input id="gali-q" type="text" maxlength="200" placeholder="Ask about a place, fares, events…">
      <button type="submit" aria-label="Send"><?= icon('send', 20) ?></button>
    </form>
  </section>
</div>
<script src="<?= e(asset('assets/js/main.js')) ?>" defer></script>
</body>
</html>
