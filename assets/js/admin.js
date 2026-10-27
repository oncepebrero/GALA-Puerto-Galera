/* GALÁ staff area: small conveniences. The page works without this file. */
(function () {
  'use strict';
  var all = document.querySelector('[data-select-all]');
  if (all) {
    var boxes = function () { return Array.prototype.slice.call(document.querySelectorAll('input[name="ids[]"]')); };
    all.addEventListener('change', function () { boxes().forEach(function (b) { b.checked = all.checked; b.closest('.mod').classList.toggle('is-picked', all.checked); }); });
    document.addEventListener('change', function (e) {
      if (e.target.name !== 'ids[]') return;
      e.target.closest('.mod').classList.toggle('is-picked', e.target.checked);
      var bs = boxes(); all.checked = bs.every(function (b) { return b.checked; });
    });
  }
  /* bulk buttons need at least one ticked box */
  var bulk = document.getElementById('bulk');
  if (bulk) bulk.addEventListener('submit', function (e) {
    var picked = bulk.querySelectorAll('input[name="ids[]"]:checked').length;
    if (!picked) { e.preventDefault(); window.alert('Tick at least one item first.'); }
  });
})();
