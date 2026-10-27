/* GALÁ home page effects. Progressive enhancement: everything works without this file. */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* scroll progress bar */
  var bar = $('.hm-progress i');
  if (bar) {
    var tick = false;
    var upd = function () {
      var h = document.documentElement.scrollHeight - window.innerHeight;
      bar.style.transform = 'scaleX(' + (h > 0 ? Math.min(1, window.scrollY / h) : 0).toFixed(4) + ')';
      tick = false;
    };
    window.addEventListener('scroll', function () { if (!tick) { tick = true; requestAnimationFrame(upd); } }, { passive: true });
    upd();
  }

  /* count-up numbers */
  var nums = $$('[data-count]');
  if (nums.length && 'IntersectionObserver' in window && !reduce) {
    var cio = new IntersectionObserver(function (es) {
      es.forEach(function (en) {
        if (!en.isIntersecting) return;
        cio.unobserve(en.target);
        var el = en.target, end = parseInt(el.getAttribute('data-count'), 10) || 0, t0 = null;
        var step = function (t) {
          if (t0 === null) t0 = t;
          var p = Math.min(1, (t - t0) / 1200), eased = 1 - Math.pow(1 - p, 3);
          el.textContent = Math.round(end * eased);
          if (p < 1) requestAnimationFrame(step);
        };
        el.textContent = '0';
        requestAnimationFrame(step);
      });
    }, { threshold: 0.6 });
    nums.forEach(function (n) { cio.observe(n); });
  }

  /* expanding category panels (desktop): hover or focus opens one; stays open when the pointer leaves */
  var acc = $('[data-acc]');
  if (acc) {
    var panes = $$('.hm-pane', acc);
    var open = function (p) { panes.forEach(function (x) { x.classList.toggle('is-on', x === p); }); };
    panes.forEach(function (p) {
      p.addEventListener('pointerenter', function (e) { if (e.pointerType === 'mouse' && window.innerWidth > 900) open(p); });
      p.addEventListener('focusin', function () { open(p); });
      p.addEventListener('click', function (e) {
        /* on touch devices wider than 900px, first tap opens, second tap follows the link */
        if (window.innerWidth > 900 && !p.classList.contains('is-on')) { e.preventDefault(); open(p); }
      });
    });
  }

  /* trip rail buttons */
  var track = $('[data-rail-track]');
  if (track) {
    $$('[data-rail]').forEach(function (b) {
      b.addEventListener('click', function () {
        var dir = parseInt(b.getAttribute('data-rail'), 10) || 1;
        track.scrollBy({ left: dir * Math.max(300, track.clientWidth * 0.6), behavior: reduce ? 'auto' : 'smooth' });
      });
    });
  }
})();
