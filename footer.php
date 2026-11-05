  </main>

  <footer class="pf-footer">
    <div class="pf-container">
      <small>&copy; <?php echo date('Y'); ?> HouseHub</small>
    </div>
  </footer>
  <script>
  // Sélecteur de couleur commun : tout <input type="color"> devient une pastille + palette moderne (+ code hexadécimal libre)
  (function () {
    var PALETTE = ['#ef4444','#f97316','#f59e0b','#eab308','#84cc16','#22c55e','#10b981','#14b8a6','#06b6d4','#0ea5e9','#3b82f6','#6366f1','#8b5cf6','#a855f7','#d946ef','#ec4899','#f43f5e','#a16207','#64748b','#475569','#1e293b','#94a3b8','#cbd5e1','#0f766e'];
    var pop = null, current = null;
    var valid = function (v) { return /^#[0-9a-fA-F]{6}$/.test(v); };
    var paint = function (inp) { var b = inp._pfBtn; if (b) b.style.setProperty('--pf-swatch', valid(inp.value) ? inp.value : '#cccccc'); };
    function closePop() { if (pop) { pop.remove(); pop = null; current = null; } }
    function setColor(inp, v) { inp.value = v.toLowerCase(); paint(inp); inp.dispatchEvent(new Event('input', { bubbles: true })); inp.dispatchEvent(new Event('change', { bubbles: true })); }
    function openPop(inp) {
      closePop(); current = inp;
      pop = document.createElement('div'); pop.className = 'pf-colorpop';
      var html = '<div class="pf-colorpop-grid">';
      PALETTE.forEach(function (c) { html += '<button type="button" class="pf-colorpop-sw' + (c === inp.value.toLowerCase() ? ' is-sel' : '') + '" style="--c:' + c + '" data-c="' + c + '" aria-label="' + c + '"></button>'; });
      html += '</div><div class="pf-colorpop-hex" style="--c:' + (valid(inp.value) ? inp.value : '#cccccc') + '"><span class="pf-colorpop-dot"></span><input type="text" class="pf-input" maxlength="7" value="' + inp.value.toUpperCase() + '" placeholder="#3B82F6"></div>';
      pop.innerHTML = html; document.body.appendChild(pop);
      var r = inp._pfBtn.getBoundingClientRect(), w = pop.offsetWidth, h = pop.offsetHeight;
      pop.style.left = Math.max(8, Math.min(r.left, window.innerWidth - w - 8)) + 'px';
      pop.style.top = (r.bottom + h + 12 > window.innerHeight ? Math.max(8, r.top - h - 8) : r.bottom + 8) + 'px';
      pop.addEventListener('click', function (e) { var c = e.target.getAttribute && e.target.getAttribute('data-c'); if (c) { setColor(inp, c); closePop(); } });
      var hx = pop.querySelector('input');
      hx.addEventListener('input', function () { var v = hx.value.trim(); if (v && v[0] !== '#') v = '#' + v; if (valid(v)) { setColor(inp, v); pop.querySelector('.pf-colorpop-hex').style.setProperty('--c', v); } });
      hx.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); closePop(); } });
    }
    function upgrade(inp) {
      if (inp._pfBtn) return;
      var b = document.createElement('button'); b.type = 'button'; b.className = 'pf-colorpick-btn'; b.title = inp.title || <?= json_encode(tr('color_title'), JSON_UNESCAPED_UNICODE) ?>;
      inp._pfBtn = b; inp.style.display = 'none'; inp.parentNode.insertBefore(b, inp); paint(inp);
      b.addEventListener('click', function (e) { e.stopPropagation(); if (current === inp) closePop(); else openPop(inp); });
    }
    function scan(root) { (root.querySelectorAll ? root.querySelectorAll('input[type="color"]') : []).forEach(upgrade); }
    document.addEventListener('click', function (e) { if (pop && !pop.contains(e.target)) closePop(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && pop) { closePop(); e.stopPropagation(); } }, true);
    window.addEventListener('scroll', closePop, true);
    new MutationObserver(function (ms) { ms.forEach(function (m) { m.addedNodes.forEach(function (n) { if (n.nodeType === 1) { if (n.matches && n.matches('input[type="color"]')) upgrade(n); scan(n); } }); }); }).observe(document.documentElement, { childList: true, subtree: true });
    scan(document);
  })();
  </script>
  <script>
  // Modales communes (.pf-modal) : Échap et clic sur le fond ferment la modale (via son bouton ✕ pour garder la logique de chaque page)
  (function () {
    var isOpen = function (m) { return /\b(open|active|show|is-active)\b/.test(m.className) || m.style.display === 'flex'; };
    var close = function (m) {
      var x = m.querySelector('.pf-modal-close, [data-modal-cancel]');
      if (x) { x.click(); return; }
      ['open', 'active', 'show', 'is-active'].forEach(function (c) { m.classList.remove(c); });
      if (m.style.display) m.style.display = 'none';
      document.body.classList.remove('no-scroll');
    };
    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape' || e.defaultPrevented) return;
      var ms = Array.prototype.filter.call(document.querySelectorAll('.pf-modal'), isOpen);
      if (ms.length) close(ms[ms.length - 1]);
    });
    var down = null;
    document.addEventListener('mousedown', function (e) { down = e.target; });
    document.addEventListener('click', function (e) {
      var m = e.target;
      if (m === down && m.classList && m.classList.contains('pf-modal') && isOpen(m) && !m.hasAttribute('data-keep-open')) close(m);
    });
  })();
  </script>
  <script>
  // Accessibilité : nom accessible des boutons icône (repris du title), rôle des modales, focus gardé dans la modale ouverte
  (function () {
    var open = function (m) { return /\b(open|active|show|is-active)\b/.test(m.className) || m.style.display === 'flex'; };
    var LABELS = <?= json_encode(['add' => tr('a11y_add'), 'prev' => tr('a11y_prev'), 'next' => tr('a11y_next'), 'view' => tr('a11y_view'), 'edit' => tr('a11y_edit'), 'del' => tr('a11y_delete'), 'close' => tr('a11y_close')], JSON_UNESCAPED_UNICODE) ?>;
    var SYMBOLES = { add: ['+', '\uff0b'], prev: ['\u25c0', '\u2039', '\u2190', '<'], next: ['\u25b6', '\u203a', '\u2192', '>'], view: ['\ud83d\udc41'], edit: ['\u270f', '\ud83d\udd8a'], del: ['\ud83d\uddd1'], close: ['\u00d7', '\u2715', '\u2716'] };
    var nommer = function () {
      document.querySelectorAll('button, a, [role="button"]').forEach(function (b) {
        if (b.hasAttribute('aria-label') || b.hasAttribute('aria-labelledby')) return;
        if (/[\p{L}\p{N}]/u.test(b.textContent || '')) return; // a déjà un vrai texte
        var t = b.getAttribute('title') || (b.querySelector('img[alt]') || {}).alt;
        if (!t) {
          var sym = b.textContent.replace(/[\s\ufe0f]/g, '');
          for (var k in SYMBOLES) if (sym && SYMBOLES[k].indexOf(sym) !== -1) { t = LABELS[k]; break; }
        }
        if (t) b.setAttribute('aria-label', t);
      });
      document.querySelectorAll('.pf-modal').forEach(function (m) {
        if (!m.hasAttribute('role')) m.setAttribute('role', 'dialog');
        if (!m.hasAttribute('aria-modal')) m.setAttribute('aria-modal', 'true');
        var h = m.querySelector('.pf-modal-title');
        if (h && !m.hasAttribute('aria-labelledby') && !m.hasAttribute('aria-label')) m.setAttribute('aria-label', h.textContent.trim());
      });
    };
    var timer = null;
    var plan = function () { clearTimeout(timer); timer = setTimeout(nommer, 150); };
    nommer();
    if (window.MutationObserver) new MutationObserver(plan).observe(document.body, { childList: true, subtree: true });
    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Tab') return;
      var ms = Array.prototype.filter.call(document.querySelectorAll('.pf-modal'), open);
      var m = ms[ms.length - 1];
      if (!m) return;
      var f = Array.prototype.filter.call(m.querySelectorAll('a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])'), function (x) { return !x.disabled && x.offsetParent !== null && x.type !== 'hidden'; });
      if (!f.length) return;
      if (!m.contains(document.activeElement)) { f[0].focus(); e.preventDefault(); }
      else if (e.shiftKey && document.activeElement === f[0]) { f[f.length - 1].focus(); e.preventDefault(); }
      else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { f[0].focus(); e.preventDefault(); }
    });
  })();
  </script>
  </div> <!-- FERMETURE pf-page -->

</body>
</html>
