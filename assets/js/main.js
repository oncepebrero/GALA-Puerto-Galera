/* GALÁ — vanilla JavaScript. No frameworks. Each feature initialises only if its markup exists. */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var BASE = document.body.getAttribute('data-base') || '';
  function readJSON(id) { var el = document.getElementById(id); if (!el) return null; try { return JSON.parse(el.textContent); } catch (e) { return null; } }
  function el(tag, attrs, text) {
    var n = document.createElement(tag);
    if (attrs) Object.keys(attrs).forEach(function (k) { n.setAttribute(k, attrs[k]); });
    if (text != null) n.textContent = text;
    return n;
  }

  /* ---------- header state + mobile menu ---------- */
  var header = $('#site-header');
  function onScrollHeader() { if (header) header.classList.toggle('is-scrolled', window.scrollY > 40); }
  onScrollHeader();
  window.addEventListener('scroll', onScrollHeader, { passive: true });

  var toggle = $('.nav-toggle'), menu = $('#mobile-menu');
  if (toggle && menu) {
    var closeBtn = $('.mobile-menu__close', menu), lastFocus = null;
    var focusables = function () { return $$('a[href],button:not([disabled])', menu); };
    function openMenu() {
      lastFocus = document.activeElement; menu.hidden = false; document.body.classList.add('menu-open');
      toggle.setAttribute('aria-expanded', 'true'); toggle.setAttribute('aria-label', 'Close menu'); closeBtn.focus();
    }
    function closeMenu() {
      menu.hidden = true; document.body.classList.remove('menu-open');
      toggle.setAttribute('aria-expanded', 'false'); toggle.setAttribute('aria-label', 'Open menu');
      if (lastFocus) lastFocus.focus();
    }
    toggle.addEventListener('click', function () { menu.hidden ? openMenu() : closeMenu(); });
    closeBtn.addEventListener('click', closeMenu);
    menu.addEventListener('click', function (e) { if (e.target.tagName === 'A') closeMenu(); });
    document.addEventListener('keydown', function (e) {
      if (menu.hidden) return;
      if (e.key === 'Escape') { closeMenu(); return; }
      if (e.key === 'Tab') {
        var f = focusables(); if (!f.length) return;
        var first = f[0], last = f[f.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
      }
    });
    window.addEventListener('resize', function () { if (window.innerWidth > 1100 && !menu.hidden) closeMenu(); });
  }

  /* ---------- parallax (subtle, skipped for reduced motion) ---------- */
  var par = $$('[data-parallax]');
  if (par.length && !reduce) {
    var ticking = false;
    var run = function () {
      var vh = window.innerHeight;
      par.forEach(function (n) {
        var r = n.parentElement.getBoundingClientRect();
        if (r.bottom < -200 || r.top > vh + 200) return;
        var f = parseFloat(n.getAttribute('data-parallax')) || 0.1;
        var offset = (r.top + r.height / 2 - vh / 2) * f * -1;
        n.style.transform = 'translate3d(0,' + offset.toFixed(1) + 'px,0)';
      });
      ticking = false;
    };
    window.addEventListener('scroll', function () { if (!ticking) { ticking = true; requestAnimationFrame(run); } }, { passive: true });
    run();
  }

  /* ---------- scroll reveal (only on marked elements) ---------- */
  var rev = $$('[data-reveal]');
  if (rev.length) {
    if (!('IntersectionObserver' in window) || reduce) { rev.forEach(function (n) { n.classList.add('is-in'); }); }
    else {
      var io = new IntersectionObserver(function (es) {
        es.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); } });
      }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
      rev.forEach(function (n) { io.observe(n); });
    }
  }

  /* ---------- hero search suggestions (ARIA combobox) ---------- */
  var sform = $('[data-suggest]');
  var sdata = readJSON('gala-suggest');
  if (sform && sdata) {
    var input = $('input[name="q"]', sform), list = $('.suggest', sform), idx = -1, items = [];
    var render = function (q) {
      list.textContent = ''; idx = -1; items = [];
      q = q.trim().toLowerCase();
      if (q.length < 2) { list.hidden = true; input.setAttribute('aria-expanded', 'false'); return; }
      var hits = sdata.filter(function (p) { return (p.n + ' ' + p.a + ' ' + p.c).toLowerCase().indexOf(q) !== -1; }).slice(0, 7);
      if (!hits.length) {
        var none = el('li', { 'class': 'suggest__none', role: 'option', 'aria-disabled': 'true' }, 'No matching places. Try a locality such as Sabang.');
        list.appendChild(none);
      }
      hits.forEach(function (p, i) {
        var li = el('li', { role: 'option', id: 'sg-' + i, 'data-slug': p.s });
        li.appendChild(el('span', null, p.n)); li.appendChild(el('small', null, p.a === 'To be confirmed' ? p.c : p.a));
        list.appendChild(li); items.push(li);
      });
      list.hidden = false; input.setAttribute('aria-expanded', 'true');
    };
    var pick = function (li) { window.location.href = BASE + '/place.php?slug=' + encodeURIComponent(li.getAttribute('data-slug')); };
    var mark = function () {
      items.forEach(function (li, i) { li.setAttribute('aria-selected', i === idx ? 'true' : 'false'); });
      if (idx >= 0) { input.setAttribute('aria-activedescendant', items[idx].id); items[idx].scrollIntoView({ block: 'nearest' }); } else input.removeAttribute('aria-activedescendant');
    };
    input.addEventListener('input', function () { render(input.value); });
    input.addEventListener('keydown', function (e) {
      if (list.hidden || !items.length) return;
      if (e.key === 'ArrowDown') { e.preventDefault(); idx = (idx + 1) % items.length; mark(); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); idx = (idx - 1 + items.length) % items.length; mark(); }
      else if (e.key === 'Enter' && idx >= 0) { e.preventDefault(); pick(items[idx]); }
      else if (e.key === 'Escape') { list.hidden = true; input.setAttribute('aria-expanded', 'false'); }
    });
    list.addEventListener('mousedown', function (e) { var li = e.target.closest('li[data-slug]'); if (li) { e.preventDefault(); pick(li); } });
    document.addEventListener('click', function (e) { if (!sform.contains(e.target)) { list.hidden = true; input.setAttribute('aria-expanded', 'false'); } });
  }

  /* ---------- dive tabs ---------- */
  var tabs = $$('.dive__tab');
  if (tabs.length) {
    var panels = $$('.dive__panel');
    var show = function (i, focus) {
      tabs.forEach(function (t, k) { var on = k === i; t.setAttribute('aria-selected', on ? 'true' : 'false'); t.tabIndex = on ? 0 : -1; panels[k].hidden = !on; });
      if (focus) tabs[i].focus();
    };
    show(0, false);
    tabs.forEach(function (t, i) {
      t.addEventListener('click', function () { show(i, false); });
      t.addEventListener('keydown', function (e) {
        var vertical = window.innerWidth > 980, n = tabs.length, next = null;
        if (e.key === (vertical ? 'ArrowDown' : 'ArrowRight')) next = (i + 1) % n;
        else if (e.key === (vertical ? 'ArrowUp' : 'ArrowLeft')) next = (i - 1 + n) % n;
        else if (e.key === 'Home') next = 0; else if (e.key === 'End') next = n - 1;
        if (next !== null) { e.preventDefault(); show(next, true); }
      });
    });
  }

  /* ---------- directory filtering (progressive enhancement of explore.php) ---------- */
  var form = $('#filters'), grid = $('#grid');
  if (form && grid) {
    var cards = $$('.tile', grid), count = $('#results-count'), empty = $('#empty'), total = cards.length;
    var qIn = $('#f-q'), areaSel = $('#f-area'), featSel = $('#f-feature'), featWrap = $('#f-feature-wrap'), sortSel = $('#f-sort');
    var catOrder = $$('input[name="cat"]', form).map(function (r) { return r.value; }).filter(Boolean);
    var curCat = function () { var c = $('input[name="cat"]:checked', form); return c ? c.value : ''; };

    var rebuildAreas = function () {
      var cat = curCat(), keep = areaSel.value, map = {};
      cards.forEach(function (c) { if (cat && c.dataset.cat !== cat) return; var a = c.dataset.area; if (a !== 'To be confirmed') map[a] = (map[a] || 0) + 1; });
      areaSel.textContent = '';
      areaSel.appendChild(el('option', { value: '' }, 'Anywhere'));
      Object.keys(map).sort().forEach(function (a) { var o = el('option', { value: a }, a + ' (' + map[a] + ')'); if (a === keep) o.selected = true; areaSel.appendChild(o); });
    };
    var apply = function (updateUrl) {
      var q = qIn.value.trim().toLowerCase(), cat = curCat(), area = areaSel.value, feat = featSel ? featSel.value : '', words = q ? q.split(/\s+/) : [];
      featWrap.hidden = !(cat === 'diving' || feat);
      var n = 0;
      cards.forEach(function (c) {
        var ok = (!cat || c.dataset.cat === cat) && (!area || c.dataset.area === area) &&
                 (!feat || c.dataset.features.split('|').indexOf(feat) !== -1) &&
                 words.every(function (w) { return c.dataset.search.indexOf(w) !== -1; });
        c.hidden = !ok; if (ok) n++;
      });
      var sorted = cards.slice().sort(function (a, b) {
        if (sortSel.value === 'cat') { var d = catOrder.indexOf(a.dataset.cat) - catOrder.indexOf(b.dataset.cat); if (d) return d; }
        return a.dataset.name.localeCompare(b.dataset.name);
      });
      sorted.forEach(function (c) { grid.appendChild(c); });
      count.innerHTML = ''; var strong = el('strong', { 'class': 'bump' }, String(n)); count.appendChild(strong); count.appendChild(document.createTextNode(' of ' + total + ' places'));
      var k = 0; cards.forEach(function (c) { if (!c.hidden) { c.style.setProperty('--i', String(Math.min(k++, 14))); c.classList.remove('is-new'); void c.offsetWidth; c.classList.add('is-new'); c.addEventListener('animationend', function h() { c.classList.remove('is-new'); c.removeEventListener('animationend', h); }); } });
      empty.hidden = n !== 0;
      if (updateUrl && window.history && history.replaceState) {
        var p = new URLSearchParams();
        if (qIn.value.trim()) p.set('q', qIn.value.trim()); if (cat) p.set('cat', cat); if (area) p.set('area', area); if (feat) p.set('feature', feat); if (sortSel.value !== 'az') p.set('sort', sortSel.value);
        history.replaceState(null, '', location.pathname + (p.toString() ? '?' + p.toString() : ''));
      }
    };
    var t;
    qIn.addEventListener('input', function () { clearTimeout(t); t = setTimeout(function () { apply(true); }, 120); });
    form.addEventListener('change', function (e) { if (e.target.name === 'cat') { rebuildAreas(); } apply(true); });
    form.addEventListener('submit', function (e) { e.preventDefault(); apply(true); });
    $('#f-reset').addEventListener('click', function (e) {
      e.preventDefault(); form.reset(); qIn.value = ''; $('input[name="cat"][value=""]', form).checked = true; rebuildAreas(); areaSel.value = ''; if (featSel) featSel.value = ''; sortSel.value = 'az'; apply(true);
    });
    rebuildAreas(); var initArea = new URLSearchParams(location.search).get('area'); if (initArea) areaSel.value = initArea;
    apply(false);
  }

  /* ---------- interactive map ---------- */
  var app = $('[data-map]');
  var mdata = readJSON('gala-map');
  if (app && mdata) {
    var listEl = $('#m-list'), qEl = $('#m-q'), cEl = $('#m-cat'), cntEl = $('#m-count'), emptyEl = $('#m-empty');
    var frame = $('#m-iframe'), loading = $('#m-loading'), title = $('#m-title'), sub = $('#m-sub'), verify = $('#m-verify'), links = $('#m-links'), note = $('#m-note');
    var current = null, loadTimer = null;
    var G = 'https://www.google.com/maps';

    var setLoading = function (on) {
      loading.classList.toggle('is-done', !on);
      if (on) { loading.textContent = ''; loading.appendChild(el('span', { 'class': 'spinner', 'aria-hidden': 'true' })); loading.appendChild(document.createTextNode(' Loading map…')); }
      clearTimeout(loadTimer);
      if (on) loadTimer = setTimeout(function () {
        loading.classList.remove('is-done'); loading.textContent = 'The map is taking a while. Check your connection, or use the map links below.';
      }, 9000);
    };
    frame.addEventListener('load', function () { setLoading(false); });
    setLoading(true);

    var drawList = function () {
      var q = qEl.value.trim().toLowerCase(), cat = cEl.value, n = 0;
      listEl.textContent = '';
      mdata.forEach(function (p) {
        if (cat && p.c !== cat) return;
        if (q && (p.n + ' ' + p.l + ' ' + p.a).toLowerCase().indexOf(q) === -1) return;
        var li = el('li'), b = el('button', { type: 'button', 'aria-pressed': current && current.s === p.s ? 'true' : 'false', 'data-slug': p.s });
        b.appendChild(el('strong', null, p.n)); b.appendChild(el('small', null, p.cl + ' · ' + (p.a === 'To be confirmed' ? 'Locality to be confirmed' : p.a)));
        li.appendChild(b); listEl.appendChild(li); n++;
      });
      cntEl.textContent = n + ' place' + (n === 1 ? '' : 's') + ' listed';
      emptyEl.hidden = n !== 0;
    };
    var linkBtn = function (href, label, cls) { var a = el('a', { href: href, 'class': 'btn btn--sm ' + cls }, label); if (/^https?:/.test(href)) { a.target = '_blank'; a.rel = 'noopener noreferrer'; } return a; };

    var choose = function (slug, pushUrl) {
      var p = mdata.filter(function (x) { return x.s === slug; })[0]; if (!p) return;
      current = p; title.textContent = p.n; sub.textContent = p.l; links.textContent = '';
      Array.prototype.forEach.call(listEl.querySelectorAll('button'), function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-slug') === slug ? 'true' : 'false'); });
      links.appendChild(linkBtn(BASE + '/place.php?slug=' + encodeURIComponent(p.s), 'Place details', 'btn--ink'));
      if (p.ind) {
        verify.hidden = false; verify.textContent = 'No map pin, by design'; note.textContent = 'This is a community listing. Location and visits are arranged with community representatives or the Puerto Galera Tourism Office, so GALÁ does not show a pin or directions.';
      } else {
        var q = encodeURIComponent(p.q);
        frame.src = G + '?q=' + q + '&z=' + (p.dive ? 13 : 15) + '&output=embed'; setLoading(true);
        links.appendChild(linkBtn(G + '/dir/?api=1&destination=' + q, 'Directions', 'btn--line'));
        links.appendChild(linkBtn(G + '/search/?api=1&query=' + q, 'Google Maps', 'btn--line'));
        links.appendChild(linkBtn('https://www.openstreetmap.org/search?query=' + q, 'OpenStreetMap', 'btn--line'));
        verify.hidden = true;
        note.textContent = p.dive ? 'Dive sites are underwater, so the map shows the nearest landmark.' : (p.v ? '' : 'The pin is the map service’s best match for the name.');
      }
      if (pushUrl && history.replaceState) history.replaceState(null, '', BASE + '/map.php?place=' + encodeURIComponent(slug));
    };
    listEl.addEventListener('click', function (e) { var b = e.target.closest('button[data-slug]'); if (b) choose(b.getAttribute('data-slug'), true); });
    qEl.addEventListener('input', drawList); cEl.addEventListener('change', drawList);
    drawList();
    var init = app.getAttribute('data-initial'); if (init) { choose(init, false); var sel = listEl.querySelector('[aria-pressed="true"]'); if (sel) sel.scrollIntoView({ block: 'nearest' }); }
  }

  /* ---------- feedback forms: validation + safe submit ---------- */
  $$('[data-feedback-form]').forEach(function (f) {
    var summary = $('[data-summary]', f), btn = $('[data-submit]', f), area = $('textarea', f), counter = $('[data-count]', f), hint = $('[data-rate-hint]', f);
    var max = area ? parseInt(area.getAttribute('data-max'), 10) : 600;
    var kind = function () { var k = $('input[name="kind"]:checked', f); return k ? k.value : 'Rating'; };
    var clearErr = function () {
      summary.textContent = '';
      $$('.field__error.js-err', f).forEach(function (n) { n.remove(); });
      $$('[aria-invalid="true"]', f).forEach(function (n) { n.removeAttribute('aria-invalid'); });
    };
    var addErr = function (target, msg, host) {
      var id = f.id + '-' + (target.name || 'x') + '-jserr';
      var p = el('p', { 'class': 'field__error js-err', id: id, role: 'alert' }, msg);
      (host || target.parentElement).appendChild(p); target.setAttribute('aria-invalid', 'true'); target.setAttribute('aria-describedby', id);
    };
    if (area && counter) area.addEventListener('input', function () { counter.textContent = String(area.value.length); counter.parentElement.style.color = area.value.length > max ? '#a3262a' : ''; });
    $$('input[name="kind"]', f).forEach(function (r) { r.addEventListener('change', function () { if (hint) hint.textContent = kind() === 'Rating' ? '(required)' : '(optional)'; }); });
    var photo = $('input[type="file"][data-photo]', f), prev = $('[data-photo-preview]', f);
    var photoErr = function () {
      if (!photo || !photo.files || !photo.files[0]) return '';
      var file = photo.files[0], lim = parseInt(photo.getAttribute('data-max-bytes'), 10) || 0;
      if (!/^image\/(jpeg|png|webp)$/.test(file.type)) return 'Please choose a JPG, PNG or WebP photo.';
      if (lim && file.size > lim) return 'That photo is too large. Please choose one under ' + photo.getAttribute('data-max-label') + '.';
      return '';
    };
    var clearPhoto = function () { if (photo) photo.value = ''; if (prev) { prev.hidden = true; var im = $('img', prev); if (im) im.removeAttribute('src'); } };
    if (photo && prev) {
      photo.addEventListener('change', function () {
        $$('.field__error.js-err', photo.parentElement).forEach(function (n) { n.remove(); });
        var bad = photoErr();
        if (bad) { addErr(photo, bad); clearPhoto(); return; }
        var file = photo.files && photo.files[0]; if (!file) { clearPhoto(); return; }
        var rd = new FileReader();
        rd.onload = function () { var im = $('img', prev); im.src = rd.result; prev.hidden = false; };
        rd.readAsDataURL(file);
      });
      var clr = $('[data-photo-clear]', f); if (clr) clr.addEventListener('click', clearPhoto);
    }
    f.addEventListener('submit', function (e) {
      clearErr(); var errs = [], first = null;
      var pe = photoErr(); if (pe) { addErr(photo, pe); errs.push('photo'); first = first || photo; }
      var place = $('select[name="place"]', f);
      if (place && !place.value) { addErr(place, 'Choose the place your feedback is about.'); errs.push('place'); first = first || place; }
      var rated = $('input[name="rating"]:checked', f);
      if (kind() === 'Rating' && !rated) { var rs = $('[data-rate]', f); var anyR = $('input[name="rating"]', f); addErr(anyR, 'Choose a rating from 1 to 5 stars.', rs); errs.push('rating'); first = first || $('input[name="rating"]', f); }
      var c = area ? area.value.trim() : '';
      if (c.length > max) { addErr(area, 'Please keep your comment under ' + max + ' characters.'); errs.push('comment'); first = first || area; }
      else if (kind() !== 'Rating' && c.length < 10) { addErr(area, 'Please write at least a short sentence (10 characters).'); errs.push('comment'); first = first || area; }
      else if (/(https?:\/\/|www\.|[\w.+-]+@[\w-]+\.\w+)/i.test(c)) { addErr(area, 'Please remove links and email addresses.'); errs.push('comment'); first = first || area; }
      var consent = $('input[name="consent"]', f);
      if (consent && !consent.checked) { addErr(consent, 'Please confirm that you have read the privacy note.', consent.closest('.field')); errs.push('consent'); first = first || consent; }
      if (errs.length) { e.preventDefault(); summary.textContent = 'Please fix ' + errs.length + ' item' + (errs.length > 1 ? 's' : '') + ' below.'; if (first) first.focus(); return; }
      if (btn) { btn.disabled = true; btn.firstElementChild.textContent = 'Sending…'; }
    });
  });


  /* ---------- page transition: wave wipe ---------- */
  var wipe = $('.wipe');
  if (wipe && !reduce) {
    var root = document.documentElement;
    if (root.classList.contains('wipe-pending')) {
      try { sessionStorage.removeItem('gala-wipe'); } catch (e) {}
      requestAnimationFrame(function () {
        wipe.classList.add('is-out'); root.classList.remove('wipe-pending');
        setTimeout(function () { wipe.classList.remove('is-out'); }, 900);
      });
    }
    document.addEventListener('click', function (e) {
      var a = e.target.closest && e.target.closest('a[href]');
      if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      if (a.target && a.target !== '_self') return;
      if (a.hasAttribute('download') || a.origin !== location.origin) return;
      if (a.pathname === location.pathname && a.search === location.search) return;
      if (/\/admin\//.test(a.pathname) || /\.(jpg|png|pdf|csv|xml)$/i.test(a.pathname)) return;
      e.preventDefault();
      wipe.classList.add('is-in');
      try { sessionStorage.setItem('gala-wipe', '1'); } catch (er) {}
      setTimeout(function () { location.href = a.href; }, 480);
    });
    window.addEventListener('pageshow', function (e) { if (e.persisted) { wipe.classList.remove('is-in', 'is-out'); } });
  }

  /* ---------- expanding category panels ---------- */
  var panelsEl = $$('.panels .panel');
  if (panelsEl.length) {
    var openPanel = function (p) { panelsEl.forEach(function (x) { x.classList.toggle('is-open', x === p); }); };
    openPanel(panelsEl[0]);
    panelsEl.forEach(function (p) { p.addEventListener('mouseenter', function () { openPanel(p); }); p.addEventListener('focus', function () { openPanel(p); }); });
  }

  /* ---------- beach locality filter ---------- */
  var bf = $('[data-beach-filter]'), bg = $('#beachgrid');
  if (bf && bg) {
    var btiles = $$('.tile', bg);
    bf.addEventListener('click', function (e) {
      var b = e.target.closest('button[data-area]'); if (!b) return;
      $$('.pill', bf).forEach(function (x) { var on = x === b; x.classList.toggle('is-on', on); x.setAttribute('aria-pressed', on ? 'true' : 'false'); });
      var area = b.getAttribute('data-area'), k = 0;
      btiles.forEach(function (t) {
        var show = !area || t.dataset.area === area; t.hidden = !show;
        if (show) { t.style.setProperty('--i', String(k++)); t.classList.remove('is-new'); void t.offsetWidth; t.classList.add('is-new'); }
      });
    });
  }

  /* ---------- 3D tilt on tiles ---------- */
  if (!reduce && window.matchMedia('(hover: hover)').matches) {
    document.addEventListener('mousemove', function (e) {
      var l = e.target.closest && e.target.closest('.tile__link[data-tilt]'); if (!l) return;
      var r = l.getBoundingClientRect(), x = (e.clientX - r.left) / r.width - .5, y = (e.clientY - r.top) / r.height - .5;
      l.style.setProperty('--ry', (x * 7).toFixed(2) + 'deg'); l.style.setProperty('--rx', (-y * 7).toFixed(2) + 'deg');
    });
    document.addEventListener('mouseout', function (e) {
      var l = e.target.closest && e.target.closest('.tile__link[data-tilt]'); if (l) { l.style.setProperty('--ry', '0deg'); l.style.setProperty('--rx', '0deg'); }
    });
  }

  /* ---------- surprise me ---------- */
  $$('[data-surprise]').forEach(function (b) {
    b.addEventListener('click', function () {
      var go = function (slug) { location.href = BASE + '/place.php?slug=' + encodeURIComponent(slug); };
      var list = readJSON('gala-suggest');
      if (list && list.length) { go(list[Math.floor(Math.random() * list.length)].s); return; }
      fetch(BASE + '/api/places.php').then(function (r) { return r.json(); }).then(function (j) { go(j.places[Math.floor(Math.random() * j.places.length)].slug); }).catch(function () { location.href = BASE + '/explore.php'; });
    });
  });

  /* ---------- Ask Gali ---------- */
  var gali = $('#gali');
  if (gali) {
    var fab = $('.gali__fab', gali), gp = $('#gali-panel'), glog = $('.gali__log', gali), gform = $('.gali__form', gali), gin = $('#gali-q'), api = gali.getAttribute('data-api'), greeted = false, busy = false;
    var addMsg = function (who, text, links) {
      var m = el('div', { 'class': 'gmsg gmsg--' + who });
      if (who === 'bot') { var av = el('span', { 'class': 'gmsg__av', 'aria-hidden': 'true' }); var im = el('img', { src: BASE + '/assets/img/brand/gala-emblem.png', alt: '', width: '22', height: '22' }); av.appendChild(im); m.appendChild(av); }
      var b = el('div', { 'class': 'gmsg__bubble' });
      String(text).split('\n').forEach(function (line) { if (line.trim()) b.appendChild(el('p', null, line)); });
      if (links && links.length) {
        var wrap = el('div', { 'class': 'gmsg__links' });
        links.forEach(function (l) { var a = el('a', { href: l.url }, l.label); if (/^https?:/.test(l.url) && l.url.indexOf(location.origin) !== 0) { a.target = '_blank'; a.rel = 'noopener noreferrer'; } wrap.appendChild(a); });
        b.appendChild(wrap);
      }
      m.appendChild(b); glog.appendChild(m); glog.scrollTop = glog.scrollHeight; return m;
    };
    var openG = function () {
      gp.hidden = false; gali.classList.add('is-open'); fab.setAttribute('aria-expanded', 'true');
      if (!greeted) { greeted = true; addMsg('bot', 'Kumusta! I\u2019m Gali, your guide to Puerto Galera.\nAsk me about a beach, a dive site, a waterfall, how to get here, fares or events. Tara!'); }
      setTimeout(function () { gin.focus(); }, 60);
    };
    var closeG = function (focusFab) { gp.hidden = true; gali.classList.remove('is-open'); fab.setAttribute('aria-expanded', 'false'); if (focusFab) fab.focus(); };
    fab.addEventListener('click', function () { gp.hidden ? openG() : closeG(true); });
    $('.gali__close', gali).addEventListener('click', function () { closeG(true); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !gp.hidden && gali.contains(document.activeElement)) closeG(true); });
    $$('[data-open-gali]').forEach(function (b) { b.addEventListener('click', openG); });
    var ask = function (q) {
      q = q.trim(); if (!q || busy) return; busy = true; addMsg('me', q);
      var t = el('div', { 'class': 'gmsg gmsg--bot' }); var tb = el('div', { 'class': 'gmsg__bubble gtyping', 'aria-label': 'Gali is typing' }); tb.appendChild(el('i')); tb.appendChild(el('i')); tb.appendChild(el('i')); t.appendChild(tb); glog.appendChild(t); glog.scrollTop = glog.scrollHeight;
      fetch(api + '?q=' + encodeURIComponent(q)).then(function (r) { if (!r.ok) throw new Error('bad'); return r.json(); }).then(function (j) {
        setTimeout(function () { t.remove(); addMsg('bot', j.text, j.links); busy = false; }, reduce ? 0 : 450);
      }).catch(function () { t.remove(); addMsg('bot', 'Oops, I lost my signal. Please try again in a moment.'); busy = false; });
    };
    gform.addEventListener('submit', function (e) { e.preventDefault(); var v = gin.value; gin.value = ''; ask(v); });
    $$('.gali__chips button', gali).forEach(function (c) { c.addEventListener('click', function () { ask(c.textContent); }); });
  }

  /* ---------- confirm destructive actions ---------- */
  $$('[data-confirm]').forEach(function (b) { b.addEventListener('click', function (e) { if (!window.confirm(b.getAttribute('data-confirm'))) e.preventDefault(); }); });
})();
