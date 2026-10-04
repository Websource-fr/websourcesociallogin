/* Websource Social Login : écran de configuration (copie d'URL, aperçu en direct, validation des couleurs). Aucune donnée sensible ici. */
(function () {
  'use strict';
  var root = document.querySelector('[data-wssl-bo]');
  if (!root) { return; }

  // Boutons « Copier »
  root.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('[data-wssl-copy]') : null;
    if (!b) { return; }
    var text = b.getAttribute('data-wssl-copy');
    var done = function () {
      var old = b.textContent;
      b.textContent = '✔';
      b.classList.add('is-done');
      setTimeout(function () { b.textContent = old; b.classList.remove('is-done'); }, 1500);
    };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(done, function () { fallback(); });
    } else { fallback(); }
    function fallback() {
      var t = document.createElement('textarea');
      t.value = text; t.setAttribute('readonly', ''); t.style.position = 'fixed'; t.style.opacity = '0';
      document.body.appendChild(t); t.select();
      try { document.execCommand('copy'); done(); } catch (err) { /* sélection manuelle */ }
      document.body.removeChild(t);
    }
  });

  // Aperçu en direct du style
  var preview = root.querySelector('[data-wssl-preview] .wssl');
  var colorRe = /^(#(?:[0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})|transparent|var\(--[a-z0-9-]{1,40}\)|rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(?:,\s*(?:0|1|0?\.\d{1,3})\s*)?\))$/i;
  function val(key) { var el = root.querySelector('[data-wssl-style="' + key + '"]'); return el ? (el.type === 'checkbox' ? el.checked : el.value) : ''; }
  function update() {
    if (!preview) { return; }
    var cls = ['wssl', 'wssl--' + val('preset'), 'wssl--' + val('layout'), 'wssl--shape-' + val('shape'), 'wssl--place-login'];
    if (val('icon_only')) { cls.push('wssl--icononly'); }
    preview.className = cls.join(' ');
    var radius = parseInt(val('radius'), 10);
    var shape = val('shape');
    var r = !isNaN(radius) && radius >= 0 && radius <= 32 ? radius + 'px' : (shape === 'pill' ? '999px' : shape === 'square' ? '2px' : 'var(--radius-xl, .75rem)');
    var border = parseInt(val('border'), 10);
    preview.style.setProperty('--wssl-radius', r);
    preview.style.setProperty('--wssl-border-w', (!isNaN(border) && border >= 0 && border <= 4 ? border : 1) + 'px');
    [['bg', '--wssl-bg'], ['color', '--wssl-fg'], ['border_color', '--wssl-bc']].forEach(function (p) {
      var el = root.querySelector('[data-wssl-style="' + p[0] + '"]');
      var v = (el ? el.value : '').trim();
      var ok = v === '' || colorRe.test(v);
      if (el) { el.classList.toggle('is-invalid', !ok); }
      if (v !== '' && ok) { preview.style.setProperty(p[1], v); } else { preview.style.removeProperty(p[1]); }
    });
  }
  root.querySelectorAll('[data-wssl-style]').forEach(function (el) { el.addEventListener('input', update); el.addEventListener('change', update); });
  update();
})();
