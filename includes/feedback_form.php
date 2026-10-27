<?php
/** Expects: $fbSelected (slug), $fbLock (bool), $fbErrors (array), $fbOld (array), $fbAction (url), $fbId (string) */
$fbErrors = $fbErrors ?? []; $fbOld = $fbOld ?? []; $fbLock = $fbLock ?? false; $fbId = $fbId ?? 'fb';
$val = fn($k, $d = '') => e($fbOld[$k] ?? $d);
$kind = $fbOld['kind'] ?? 'Rating';
$rate = (int) ($fbOld['rating'] ?? 0);
$max = (int) cfg('feedback.max_comment');
feedback_form_token();
$err = fn($k) => isset($fbErrors[$k]) ? '<p class="field__error" id="' . $fbId . '-' . $k . '-err" role="alert">' . e($fbErrors[$k]) . '</p>' : '';
$inv = fn($k) => isset($fbErrors[$k]) ? ' aria-invalid="true" aria-describedby="' . $fbId . '-' . $k . '-err"' : '';
?>
<form class="fbform" id="<?= e($fbId) ?>" action="<?= e($fbAction) ?>" method="post" enctype="multipart/form-data" novalidate data-feedback-form>
  <?= csrf_field() ?>
  <div class="fbform__hp" aria-hidden="true"><label>Leave this field empty <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
  <div class="fbform__summary" role="alert" aria-live="assertive" data-summary></div>

  <div class="field">
    <?php if ($fbLock): $lp = place_by_slug($fbSelected); ?>
      <span class="label">Place</span>
      <p class="fbform__place"><?= e($lp['name'] ?? '') ?> <small><?= e($lp['locality'] ?? '') ?></small></p>
      <input type="hidden" name="place" value="<?= e($fbSelected) ?>">
    <?php else: ?>
      <label for="<?= e($fbId) ?>-place">Which place is this about?</label>
      <select id="<?= e($fbId) ?>-place" name="place" required<?= $inv('place') ?>>
        <option value="">Choose a place</option>
        <?php foreach (categories() as $ck => $cc): ?>
          <optgroup label="<?= e($cc['label']) ?>">
            <?php foreach (places_in($ck) as $p): ?><option value="<?= e($p['slug']) ?>" <?= ($fbSelected ?? '') === $p['slug'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
          </optgroup>
        <?php endforeach; ?>
      </select>
      <?= $err('place') ?>
    <?php endif; ?>
  </div>

  <fieldset class="field seg">
    <legend>What are you sending?</legend>
    <?php foreach (FB_KINDS as $k): ?>
      <label class="seg__opt"><input type="radio" name="kind" value="<?= e($k) ?>" <?= $kind === $k ? 'checked' : '' ?>><span><?= e($k) ?></span></label>
    <?php endforeach; ?>
  </fieldset>

  <fieldset class="field rate" data-rate>
    <legend>Rating <small data-rate-hint><?= $kind === 'Rating' ? '(required)' : '(optional)' ?></small></legend>
    <div class="rate__stars">
      <?php for ($i = 5; $i >= 1; $i--): ?>
        <input type="radio" id="<?= e($fbId) ?>-r<?= $i ?>" name="rating" value="<?= $i ?>" <?= $rate === $i ? 'checked' : '' ?>>
        <label for="<?= e($fbId) ?>-r<?= $i ?>" title="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>"><span class="visually-hidden"><?= $i ?> star<?= $i > 1 ? 's' : '' ?></span><svg viewBox="0 0 24 24" width="30" height="30" aria-hidden="true"><path d="m12 2.8 2.8 5.9 6.4.8-4.7 4.4 1.2 6.4L12 17.2 6.3 20.3l1.2-6.4L2.8 9.5l6.4-.8Z"/></svg></label>
      <?php endfor; ?>
    </div>
    <?= $err('rating') ?>
  </fieldset>

  <div class="field">
    <label for="<?= e($fbId) ?>-comment">Your comment <small>(optional for ratings)</small></label>
    <textarea id="<?= e($fbId) ?>-comment" name="comment" rows="4" maxlength="<?= $max + 200 ?>" data-max="<?= $max ?>"<?= $inv('comment') ?>><?= $val('comment') ?></textarea>
    <p class="field__hint"><span data-count><?= mb_strlen((string) ($fbOld['comment'] ?? '')) ?></span> / <?= $max ?>. No links, email addresses or phone numbers, please.</p>
    <?= $err('comment') ?>
  </div>

  <?php if (fb_photo_supported()): ?>
  <div class="field field--photo">
    <label for="<?= e($fbId) ?>-photo">Share a photo of your visit <small>(optional)</small></label>
    <input id="<?= e($fbId) ?>-photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp" data-photo data-max-bytes="<?= (int) fb_photo_limit() ?>" data-max-label="<?= e(fb_photo_limit_label()) ?>"<?= $inv('photo') ?>>
    <p class="field__hint">One JPG, PNG or WebP, up to <?= e(fb_photo_limit_label()) ?>. Share only photos you took, and please avoid showing people who have not agreed to it. Staff review every photo before it appears.</p>
    <div class="photoprev" data-photo-preview hidden><img alt="Preview of your photo" width="160" height="120"><button type="button" class="btn btn--line btn--sm" data-photo-clear>Remove photo</button></div>
    <?= $err('photo') ?>
  </div>
  <?php endif; ?>

  <div class="field">
    <label for="<?= e($fbId) ?>-nick">Nickname <small>(optional)</small></label>
    <input id="<?= e($fbId) ?>-nick" type="text" name="nickname" maxlength="40" value="<?= $val('nickname') ?>" autocomplete="off"<?= $inv('nickname') ?>>
    <?= $err('nickname') ?>
  </div>

  <div class="field field--check">
    <label class="check"><input type="checkbox" name="consent" value="1" <?= !empty($fbOld['consent']) ? 'checked' : '' ?><?= $inv('consent') ?>>
      <span>I have read the privacy note below and agree that staff may review and publish this feedback.</span></label>
    <?= $err('consent') ?>
  </div>

  <p class="privacy"><strong>Privacy note.</strong> GALÁ stores your rating, comment, optional photo, optional nickname, the date, and a one-way hash of your connection details that is used only to limit spam. No email address or phone number is collected. Feedback is public only after staff approval, and totals shown to staff never expose who wrote what.</p>

  <button class="btn btn--ink" type="submit" data-submit><span>Send feedback</span></button>
</form>
