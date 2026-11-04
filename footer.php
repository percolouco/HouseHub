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
  </div> <!-- FERMETURE pf-page -->

</body>
</html>
