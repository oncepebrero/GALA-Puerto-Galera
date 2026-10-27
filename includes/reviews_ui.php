<?php
/**
 * Shared pieces for the visitor "moments" mosaic (home page strip + the All reviews page).
 * rv_card()   one review card, sized 'sm' | 'tall' | 'wide'
 * rv_sizes()  the size of every card: a repeating pattern that tiles a 2-row / 4-column grid with no gaps
 * rv_dialog() the pop-up that shows one review in full (filled in by assets/js/reviews.js)
 */
function rv_sizes(array $rows): array
{
    $p1 = ['tall', 'sm', 'sm', 'wide', 'sm', 'sm'];      // 8 grid cells
    $p2 = ['sm', 'tall', 'sm', 'sm', 'wide', 'sm'];      // 8 grid cells, mirrored
    $out = [];
    foreach ($rows as $i => $r) {
        $pat = intdiv($i, 6) % 2 === 0 ? $p1 : $p2; $s = $pat[$i % 6];
        if ($s === 'tall' && empty($r['photo']) && mb_strlen((string) ($r['comment'] ?? '')) < 90) $s = 'sm';   // a short text-only note does not need a tall card
        $out[$i] = $s;
    }
    return $out;
}
function rv_stars(int $n): string { $n = max(0, min(5, $n)); return str_repeat('★', $n) . '<i>' . str_repeat('★', 5 - $n) . '</i>'; }

function rv_card(array $m, string $size, int $i = 0, bool $reveal = true): string
{
    $pu = !empty($m['photo']) ? fb_photo_url($m['photo']) : '';
    $who = trim((string) ($m['nickname'] ?? '')) ?: 'A visitor';
    $rating = (int) ($m['rating'] ?? 0); $comment = trim((string) ($m['comment'] ?? ''));
    $placeUrl = url('place.php', ['slug' => $m['place_slug']]);
    $date = nice_date(substr((string) $m['created_at'], 0, 10));
    $initial = mb_strtoupper(mb_substr($who, 0, 1));
    $tone = $pu ? 'photo' : 't' . (abs(crc32($who . $m['place_slug'])) % 4);
    $caption = $comment !== '' ? e($comment) : 'Shared a photo from their visit.';
    ob_start(); ?>
<article class="rv rv--<?= e($size) ?> rv--<?= e($tone) ?>"<?= $reveal ? ' data-reveal style="--d:' . (min($i, 8) * 55) . 'ms"' : '' ?>
  data-name="<?= e($who) ?>" data-place="<?= e($m['place_name']) ?>" data-place-url="<?= e($placeUrl) ?>" data-rating="<?= $rating ?>"
  data-date="<?= e($date) ?>" data-text="<?= e($comment) ?>" data-photo="<?= e($pu) ?>">
  <?php if ($pu): ?><img class="rv__img" src="<?= e($pu) ?>" alt="Photo shared by <?= e($who) ?> at <?= e($m['place_name']) ?>" loading="lazy" decoding="async"><?php else: ?><span class="rv__mark" aria-hidden="true">“</span><?php endif; ?>
  <div class="rv__shade" aria-hidden="true"></div>
  <div class="rv__body">
    <span class="rv__av" aria-hidden="true"><?= e($initial) ?></span>
    <h3 class="rv__name"><?= e($who) ?></h3>
    <p class="rv__meta"><span class="rv__stars" role="img" aria-label="<?= $rating ?> out of 5 stars"><?= rv_stars($rating) ?></span><span class="rv__place"><?= e($m['place_name']) ?></span></p>
    <p class="rv__text<?= $comment === '' ? ' rv__text--mute' : '' ?>"><?= $caption ?></p>
  </div>
  <button type="button" class="rv__open" data-rv-open aria-haspopup="dialog"><span class="visually-hidden">Read the full review by <?= e($who) ?> about <?= e($m['place_name']) ?></span></button>
</article>
<?php return (string) ob_get_clean();
}

function rv_dialog(): string
{
    ob_start(); ?>
<dialog class="rv-dialog" id="rv-dialog" aria-labelledby="rv-d-name">
  <div class="rv-dialog__card">
    <button type="button" class="rv-dialog__close" data-rv-close aria-label="Close"><?= icon('close', 22) ?></button>
    <figure class="rv-dialog__fig" hidden><img alt="" src=""></figure>
    <div class="rv-dialog__body">
      <p class="rv-dialog__stars" data-d="stars"></p>
      <h2 id="rv-d-name" data-d="name"></h2>
      <p class="rv-dialog__sub"><a data-d="place" href="#"></a> · <span data-d="date"></span></p>
      <blockquote data-d="text"></blockquote>
    </div>
  </div>
</dialog>
<?php return (string) ob_get_clean();
}
