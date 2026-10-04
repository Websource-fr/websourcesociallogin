/* Websource Social Login : état « chargement » du bouton (aucune donnée). */
(function () {
  'use strict';
  function reset() {
    document.querySelectorAll('.wssl-btn.is-loading').forEach(function (b) {
      b.classList.remove('is-loading');
      b.removeAttribute('aria-busy');
    });
  }
  document.addEventListener('click', function (e) {
    var a = e.target.closest ? e.target.closest('.wssl-btn') : null;
    if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey) { return; }
    if (a.getAttribute('href') === '#') { e.preventDefault(); return; }
    if (a.classList.contains('is-loading')) { e.preventDefault(); return; }
    a.classList.add('is-loading');
    a.setAttribute('aria-busy', 'true');
  });
  // retour arrière du navigateur (cache page) : on retire l'état de chargement
  window.addEventListener('pageshow', function (e) { if (e.persisted) { reset(); } });
})();
