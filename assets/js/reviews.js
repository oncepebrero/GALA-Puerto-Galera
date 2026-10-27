/* GALÁ visitor moments: sideways strip buttons and the full-review pop-up. Everything still works as plain links without this file. */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---- strip: previous / next buttons ---- */
  $$('[data-rvm-strip]').forEach(function (strip) {
    var track = $('[data-rvm-track]', strip), prev = $('[data-rvm="-1"]', strip), next = $('[data-rvm="1"]', strip);
    if (!track) return;
    var update = function () {
      var max = track.scrollWidth - track.clientWidth - 4;
      if (prev) prev.disabled = track.scrollLeft <= 4;
      if (next) next.disabled = track.scrollLeft >= max;
    };
    [prev, next].forEach(function (b) {
      if (!b) return;
      b.addEventListener('click', function () {
        var dir = parseInt(b.getAttribute('data-rvm'), 10) || 1;
        track.scrollBy({ left: dir * Math.max(260, track.clientWidth * 0.8), behavior: reduce ? 'auto' : 'smooth' });
      });
    });
    track.addEventListener('scroll', function () { window.requestAnimationFrame(update); }, { passive: true });
    window.addEventListener('resize', update);
    track.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowRight') { track.scrollBy({ left: 300, behavior: reduce ? 'auto' : 'smooth' }); e.preventDefault(); }
      if (e.key === 'ArrowLeft') { track.scrollBy({ left: -300, behavior: reduce ? 'auto' : 'smooth' }); e.preventDefault(); }
    });
    update();
  });

  /* ---- pop-up with the full review ---- */
  var dlg = $('#rv-dialog');
  if (!dlg || typeof dlg.showModal !== 'function') return;
  var fig = $('.rv-dialog__fig', dlg), img = $('img', fig), lastBtn = null;
  var field = function (n) { return $('[data-d="' + n + '"]', dlg); };
  var starsHtml = function (n) { var s = document.createElement('span'); s.textContent = '★★★★★'.slice(0, n); var i = document.createElement('i'); i.textContent = '★★★★★'.slice(0, 5 - n); var f = document.createDocumentFragment(); f.appendChild(s); f.appendChild(i); return f; };

  function open(card, btn) {
    var d = card.dataset, rating = parseInt(d.rating, 10) || 0;
    field('name').textContent = d.name;
    field('date').textContent = d.date;
    var pl = field('place'); pl.textContent = d.place; pl.setAttribute('href', d.placeUrl);
    var tx = field('text'); tx.textContent = d.text || 'Shared a photo from their visit.'; tx.style.fontStyle = d.text ? '' : 'italic';
    var st = field('stars'); st.textContent = ''; st.appendChild(starsHtml(rating)); st.setAttribute('role', 'img'); st.setAttribute('aria-label', rating + ' out of 5 stars');
    if (d.photo) { img.src = d.photo; img.alt = 'Photo shared by ' + d.name + ' at ' + d.place; fig.hidden = false; } else { img.removeAttribute('src'); fig.hidden = true; }
    lastBtn = btn; dlg.showModal();
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('[data-rv-open]') : null;
    if (b) { var card = b.closest('.rv'); if (card) open(card, b); return; }
    if (e.target.closest && e.target.closest('[data-rv-close]')) { dlg.close(); return; }
    if (e.target === dlg) dlg.close();                                  // click on the dark backdrop
  });
  dlg.addEventListener('click', function (e) { var c = $('.rv-dialog__card', dlg); if (c && !c.contains(e.target)) dlg.close(); });
  dlg.addEventListener('close', function () { if (lastBtn) lastBtn.focus(); });
})();
