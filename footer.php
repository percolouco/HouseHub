  </main>

  <footer class="pf-footer">
    <div class="pf-container">
      <small>&copy; <?php echo date('Y'); ?> HouseHub</small>
    </div>
  </footer>
  <script>
  // Modales communes (.pf-modal) : Échap et clic sur le fond ferment la modale (via son bouton ✕ pour garder la logique de chaque page)
  (function () {
    var isOpen = function (m) { return /\b(open|active|show|is-active)\b/.test(m.className) || m.style.display === 'flex'; };
    var close = function (m) {
      var x = m.querySelector('.pf-modal-close');
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
