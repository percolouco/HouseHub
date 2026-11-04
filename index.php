<?php
// Protection de la page : nécessite d'être connecté

require __DIR__ . '/includes/auth.php';
require_login('/login.php');

// Inclusion i18n (déjà fait dans header, mais par sécurité si besoin de tr() avant)

require_once __DIR__ . '/includes/i18n.php';

// Configuration de la page
$pageTitle  = tr('home_title');
$activePage = "home";
// background géré par home.css via body[data-page="home"]
$pageCss    = "/modules/home/home.css";

require __DIR__ . '/header.php';

// Override background si image personnalisée uploadée
$_fid = $_SESSION['user']['family_id'] ?? null;
$_has_custom_bg = false;
if ($_fid) {
    foreach (glob('/uploads/home_bg_' . $_fid . '.*') as $_f) { $_has_custom_bg = true; break; }
}
if ($_has_custom_bg): ?>
<style>body[data-page="home"] { background-image: url('/home-bg.php?v=<?= filemtime($_f) ?>'); }</style>
<?php endif; ?>

<div class="pf-container">
  
  <div class="pf-hero">
    <h1><?= tr('home_welcome') ?></h1>
    <p><?= tr('home_subtitle') ?></p>

    <?php if (isset($_SESSION['user'])): ?>
      <div class="pf-user-info" style="margin-top: 12px; font-weight: 500;">
        <?= tr('home_logged_as') ?> 
        <strong><?= htmlspecialchars($_SESSION['user']['display_name'] ?? $_SESSION['user']['username']) ?></strong>
      </div>
    <?php endif; ?>
  </div>

  <?php if (!empty($hhAppsMode)): $hhApps = hhAppsList(); ?>
  <div class="hh-apps-bar">
    <button type="button" id="hhOrganize">✎ <?= tr('apps_organize') ?></button>
    <button type="button" id="hhReset" hidden><?= tr('apps_reset') ?></button>
    <button type="button" id="hhDone" class="hh-apps-done" hidden><?= tr('apps_done') ?></button>
    <div class="hh-apps-hint" id="hhHint" hidden><?= tr('apps_hint') ?></div>
  </div>
  <nav class="hh-appgrid" id="hhAppGrid">
    <?php foreach ($hhApps as $a): ?>
    <a href="<?= htmlspecialchars($a['href']) ?>" class="hh-app" data-key="<?= htmlspecialchars($a['key']) ?>" style="--c1:<?= $a['grad'][0] ?>;--c2:<?= $a['grad'][1] ?>">
      <span class="hh-app-ico"><?= $a['icon'] ?></span>
      <span class="hh-app-name"><?= htmlspecialchars($a['label']) ?></span>
    </a>
    <?php endforeach; ?>
    <a href="/logout.php" class="hh-app" data-fixed="1" style="--c1:#ef4444;--c2:#b91c1c">
      <span class="hh-app-ico">🚪</span>
      <span class="hh-app-name"><?= tr('btn_logout') ?></span>
    </a>
  </nav>
  <script>
  (function () {
    var grid = document.getElementById('hhAppGrid'), editing = false, saveTimer = null;
    var bar = { org: document.getElementById('hhOrganize'), reset: document.getElementById('hhReset'), done: document.getElementById('hhDone'), hint: document.getElementById('hhHint') };
    function save(action) {
      var fd = new FormData(); fd.append('csrf_token', window.CSRF_TOKEN);
      if (action) fd.append('action', action);
      else fd.append('order', JSON.stringify([].map.call(grid.querySelectorAll('.hh-app:not([data-fixed])'), function (e) { return e.dataset.key; })));
      return fetch('home-layout.php', { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    }
    function setEdit(on) {
      editing = on; grid.classList.toggle('is-editing', on);
      bar.org.hidden = on; bar.done.hidden = !on; bar.reset.hidden = !on; bar.hint.hidden = !on;
    }
    bar.org.onclick = function () { setEdit(true); };
    bar.done.onclick = function () { setEdit(false); };
    bar.reset.onclick = function () { save('reset').then(function () { location.reload(); }); };

    // Clic : navigation normale, bloquée en mode organiser. Appui long : entre en mode organiser.
    grid.addEventListener('click', function (e) { if (editing && e.target.closest('.hh-app')) e.preventDefault(); });
    grid.addEventListener('contextmenu', function (e) { e.preventDefault(); });

    var drag = null, press = null;
    grid.addEventListener('pointerdown', function (e) {
      var el = e.target.closest('.hh-app'); if (!el || el.dataset.fixed) return;
      if (!editing) {
        press = setTimeout(function () { setEdit(true); press = null; el._longpress = true; }, 480);
        el.addEventListener('pointerup', clear, { once: true }); el.addEventListener('pointercancel', clear, { once: true });
        function clear() { if (press) { clearTimeout(press); press = null; } if (el._longpress) { setTimeout(function () { el._longpress = false; }, 50); } }
        return;
      }
      var r = el.getBoundingClientRect();
      drag = { el: el, gx: e.clientX - r.left, gy: e.clientY - r.top, id: e.pointerId };
      el.classList.add('is-dragging'); el.setPointerCapture && el.setPointerCapture(e.pointerId);
      e.preventDefault();
    });
    grid.addEventListener('pointermove', function (e) {
      if (!drag) return;
      var el = drag.el; el.style.transform = 'none';
      var tiles = [].slice.call(grid.querySelectorAll('.hh-app:not([data-fixed])')).filter(function (t) { return t !== el; });
      for (var i = 0; i < tiles.length; i++) {
        var b = tiles[i].getBoundingClientRect();
        if (e.clientX >= b.left && e.clientX <= b.right && e.clientY >= b.top && e.clientY <= b.bottom) {
          var before = el.compareDocumentPosition(tiles[i]) & Node.DOCUMENT_POSITION_FOLLOWING;
          grid.insertBefore(el, before ? tiles[i].nextSibling : tiles[i]); break;
        }
      }
      var r = el.getBoundingClientRect();
      el.style.transform = 'translate(' + (e.clientX - drag.gx - r.left) + 'px,' + (e.clientY - drag.gy - r.top) + 'px)';
    });
    function drop() {
      if (!drag) return;
      drag.el.classList.remove('is-dragging'); drag.el.style.transform = ''; drag = null; save();
    }
    grid.addEventListener('pointerup', drop); grid.addEventListener('pointercancel', drop);
  })();
  </script>
  <?php endif; ?>

  <section class="pf-section">
    <h2 style="color: #fff; text-shadow: 0 1px 3px rgba(0,0,0,0.6);"><?= tr('home_modules_title') ?></h2>

    <?php $mods = $_SESSION['enabled_modules'] ?? ['calendar','budget','holidays','gifts','food','garage','calendar_ios']; ?>
    <div class="pf-modules-grid">

      <?php if (in_array('calendar', $mods)): ?>
      <a href="/family-calendar.php" class="pf-module-card">
        <div class="pf-card-icon">📅</div>
        <h3 class="pf-card-title"><?= tr('mod_calendar_name') ?></h3>
        <div class="pf-card-desc"><?= tr('mod_calendar_desc') ?></div>
        <span class="pf-card-cta"><?= tr('cta_open') ?></span>
      </a>
      <?php endif; ?>

      <?php if (in_array('budget', $mods)): ?>
      <a href="/budget.php" class="pf-module-card">
        <div class="pf-card-icon">💰</div>
        <h3 class="pf-card-title"><?= tr('mod_budget_name') ?></h3>
        <div class="pf-card-desc"><?= tr('mod_budget_desc') ?></div>
        <span class="pf-card-cta"><?= tr('cta_manage') ?></span>
      </a>
      <?php endif; ?>

      <?php if (in_array('holidays', $mods)): ?>
      <a href="/holidays.php" class="pf-module-card">
        <div class="pf-card-icon">🏖️</div>
        <h3 class="pf-card-title"><?= tr('mod_holidays_name') ?></h3>
        <div class="pf-card-desc"><?= tr('mod_holidays_desc') ?></div>
        <span class="pf-card-cta"><?= tr('cta_explore') ?></span>
      </a>
      <?php endif; ?>

      <?php if (in_array('gifts', $mods)): ?>
      <a href="/gift-list.php" class="pf-module-card">
        <div class="pf-card-icon">🎁</div>
        <h3 class="pf-card-title"><?= tr('mod_gifts_name') ?></h3>
        <div class="pf-card-desc"><?= tr('mod_gifts_desc') ?></div>
        <span class="pf-card-cta"><?= tr('cta_view_lists') ?></span>
      </a>
      <?php endif; ?>

      <?php if (in_array('garage', $mods)): ?>
      <a href="/garage.php" class="pf-module-card">
        <div class="pf-card-icon">🚗</div>
        <h3 class="pf-card-title"><?= tr('mod_garage_name') ?></h3>
        <div class="pf-card-desc"><?= tr('mod_garage_desc') ?></div>
        <span class="pf-card-cta"><?= tr('cta_service') ?></span>
      </a>
      <?php endif; ?>

      <?php if (in_array('sport', $mods)): ?>
      <a href="/sport.php" class="pf-module-card">
        <div class="pf-card-icon">⚽</div>
        <h3 class="pf-card-title"><?= tr('mod_sport_name') ?></h3>
        <div class="pf-card-desc"><?= tr('mod_sport_desc') ?></div>
        <span class="pf-card-cta"><?= tr('cta_service') ?></span>
      </a>
      <?php endif; ?>

      <?php if (in_array('food', $mods)): ?>
      <a href="/food.php" class="pf-module-card">
        <div class="pf-card-icon">🍳</div>
        <h3 class="pf-card-title"><?= tr('mod_food_name') ?></h3>
        <div class="pf-card-desc"><?= tr('mod_food_desc') ?></div>
        <span class="pf-card-cta"><?= tr('cta_food') ?></span>
      </a>
      <?php endif; ?>

      <?php if (in_array('memo', $mods)): ?>
      <a href="/memo.php" class="pf-module-card">
        <div class="pf-card-icon">📝</div>
        <h3 class="pf-card-title"><?= tr('mod_memo_name') ?></h3>
        <div class="pf-card-desc"><?= tr('mod_memo_desc') ?></div>
        <span class="pf-card-cta"><?= tr('cta_jot') ?></span>
      </a>
      <?php endif; ?>

      <?php if (in_array('todo', $mods)): ?>
      <a href="/todo.php" class="pf-module-card">
        <div class="pf-card-icon">✅</div>
        <h3 class="pf-card-title"><?= tr('mod_todo_name') ?></h3>
        <div class="pf-card-desc"><?= tr('mod_todo_desc') ?></div>
        <span class="pf-card-cta"><?= tr('cta_check') ?></span>
      </a>
      <?php endif; ?>


      <?php if (in_array('calendar_ios', $mods)): ?>
      <a href="/calendar-ios.php" class="pf-module-card">
        <div class="pf-card-icon">📱</div>
        <h3 class="pf-card-title"><?= tr('mod_calendar_ios_name') ?></h3>
        <div class="pf-card-desc"><?= tr('mod_calendar_ios_desc') ?></div>
        <span class="pf-card-cta"><?= tr('cta_sync') ?></span>
      </a>
      <?php endif; ?>

      <?php if (in_array('printvault', $mods)): ?>
      <a href="/printvault.php" class="pf-module-card">
        <div class="pf-card-icon">🖨️</div>
        <h3 class="pf-card-title"><?= tr('mod_printvault_name') ?></h3>
        <div class="pf-card-desc"><?= tr('mod_printvault_desc') ?></div>
        <span class="pf-card-cta"><?= tr('cta_print') ?></span>
      </a>
      <?php endif; ?>

    </div>
  </section>
</div>

<?php
require __DIR__ . '/footer.php';
?>