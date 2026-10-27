<?php
require __DIR__ . '/includes/bootstrap.php';
$slug = (string) ($_GET['place'] ?? $_POST['place'] ?? '');
$result = null; $errors = []; $old = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = feedback_submit($_POST);
    if ($result['ok']) { flash(['ok' => true, 'message' => $result['message']]); redirect(url('feedback.php', ['sent' => 1])); }
    $errors = $result['errors']; $old = $_POST;
    http_response_code($result['errors'] ? 422 : 400);
}
if (!$old && isset($_GET['rating']) && (int) $_GET['rating'] >= 1 && (int) $_GET['rating'] <= 5) $old = ['rating' => (int) $_GET['rating'], 'kind' => 'Rating'];
$flash = flash();
$notes = feedback_approved(null, 6);
page(['title' => 'Ratings and feedback', 'path' => 'feedback.php', 'nav' => '', 'class' => 'page-feedback',
      'desc' => 'Rate a Puerto Galera place, share a memory or photo, suggest an improvement or report a concern. Feedback is reviewed by staff before it is published.']);
require ROOT_PATH . '/includes/header.php';
?>
<section class="xhead">
  <div class="wrap xhead__inner"><div>
    <p class="crumbs"><a href="<?= e(url()) ?>">Home</a> / <span>Feedback</span></p>
    <h1>Leave your <span class="hand">mark</span></h1>
    <p class="xhead__lede">Share a rating, a memory or a photo from your visit. It helps the next traveler, and shows the tourism team where information is wrong or missing. Staff read every message and photo before anything is published.</p>
  </div></div>
  <svg class="xhead__wave" viewBox="0 0 1440 80" preserveAspectRatio="none" aria-hidden="true"><path d="M0 40C180 0 360 0 540 32s360 40 540 8 240-30 360-8V80H0Z"/></svg>
</section>
<section class="section section--tight">
  <div class="wrap split">
    <div class="split__main">
      <?php if ($flash && !empty($flash['ok'])): ?>
        <div class="notice notice--ok" role="status"><?= icon('check', 22) ?><div><strong>Saved.</strong> <?= e($flash['message']) ?></div></div>
      <?php endif; ?>
      <?php if ($result && !$result['ok']): ?>
        <div class="notice notice--err" role="alert" id="form-error"><?= icon('alert', 22) ?><div><strong>Not sent.</strong> <?= e($result['message']) ?></div></div>
      <?php endif; ?>
      <?php if (!db_ok()): ?>
        <div class="notice notice--err" role="alert"><?= icon('alert', 22) ?><div><strong>Feedback is unavailable right now.</strong> The database could not be reached, so a submission could not be saved. Please try again later.</div></div>
      <?php endif; ?>
      <?php $fbSelected = $slug; $fbLock = false; $fbErrors = $errors; $fbOld = $old; $fbAction = url('feedback.php'); $fbId = 'fb'; include ROOT_PATH . '/includes/feedback_form.php'; ?>
    </div>
    <aside class="split__side">
      <h2 class="mini-title">What happens next</h2>
      <ol class="plain-steps">
        <li>Your message and photo are saved as <em>pending</em>.</li>
        <li>Staff check it for accuracy, safety and privacy.</li>
        <li>Approved ratings count toward a place’s average. Approved comments appear on its page.</li>
      </ol>
      <h2 class="mini-title">Recently approved</h2>
      <?php if ($notes): foreach ($notes as $f): ?>
        <blockquote class="voice voice--small"><?php if (!empty($f['photo']) && fb_photo_url($f['photo'])): ?><img class="voice__photo voice__photo--sm" src="<?= e(fb_photo_url($f['photo'])) ?>" alt="Photo shared by <?= e($f['nickname'] ?: 'a visitor') ?>" loading="lazy"><?php endif; ?><?php if ($f['comment']): ?><p><?= e($f['comment']) ?></p><?php endif; ?><footer><a href="<?= e(url('place.php', ['slug' => $f['place_slug']])) ?>"><?= e($f['place_name']) ?></a> · <?= e($f['nickname'] ?: 'A visitor') ?></footer></blockquote>
      <?php endforeach; else: ?>
        <p class="empty">No approved feedback yet.</p>
      <?php endif; ?>
    </aside>
  </div>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
