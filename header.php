<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// 1. Inclusion du moteur de traduction
require_once __DIR__ . '/includes/i18n.php';

/**
 * Génère une URL avec le paramètre de langue mis à jour
 */
function getLangUrl($newLang) {
    $params = $_GET;
    $params['lang'] = $newLang;
    return '?' . http_build_query($params);
}

$pageTitle = $pageTitle ?? "HouseHub";
$activePage = $activePage ?? "";
$currentLang = $_SESSION['app_lang'] ?? 'fr';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link rel="manifest" href="/manifest.json">
  <link rel="icon" type="image/png" href="/favicon.png">
  <link rel="apple-touch-icon" href="/favicon.png">
  <link rel="stylesheet" href="/global.css">
  <?php if (!empty($pageCss)): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($pageCss) ?>">
  <?php endif; ?>
  <link rel="stylesheet" href="/dark-mode.css">
  <script>(function(){const t=localStorage.getItem('hh-theme');if(t==='dark')document.documentElement.setAttribute('data-theme','dark');})()</script>
  <style>
    /* Patch CSS pour la zone droite de l'en-tête (Desktop / Mobile) */
    .pf-header-right { display: flex; align-items: center; gap: 15px; }
    @media (max-width: 768px) {
        .pf-desktop-actions { display: none !important; }
    }
  </style>
</head>
<body data-page="<?= htmlspecialchars($activePage ?? '') ?>">

  <header class="pf-header">
    <a href="/index.php" class="pf-logo">HouseHub</a>

    <?php if (isset($_SESSION['user'])): ?>
    <?php $mods = $_SESSION['enabled_modules'] ?? ['calendar','budget','holidays','gifts','calendar_ios']; ?>
    <nav class="pf-nav">
      <a href="/index.php" class="pf-nav-link <?= $activePage === 'home' ? 'pf-nav-link--active' : '' ?>"><?= tr('menu_home') ?></a>
      <?php if (in_array('calendar',  $mods)): ?><a href="/family-calendar.php" class="pf-nav-link <?= $activePage === 'family-calendar' ? 'pf-nav-link--active' : '' ?>"><?= tr('menu_calendar') ?></a><?php endif; ?>
      <?php if (in_array('budget',    $mods)): ?><a href="/budget.php" class="pf-nav-link <?= $activePage === 'budget' ? 'pf-nav-link--active' : '' ?>"><?= tr('menu_budget') ?></a><?php endif; ?>
      <?php if (in_array('holidays',  $mods)): ?><a href="/holidays.php" class="pf-nav-link <?= $activePage === 'holidays' ? 'pf-nav-link--active' : '' ?>"><?= tr('menu_holidays') ?></a><?php endif; ?>
      <?php if (in_array('gifts',     $mods)): ?><a href="/gift-list.php" class="pf-nav-link <?= $activePage === 'gift-list' ? 'pf-nav-link--active' : '' ?>"><?= tr('menu_gifts') ?></a><?php endif; ?>
      <?php if (in_array('garage',    $mods)): ?><a href="/garage.php" class="pf-nav-link <?= $activePage === 'garage' ? 'pf-nav-link--active' : '' ?>"><?= tr('menu_garage') ?></a><?php endif; ?>
      <?php if (in_array('memo',      $mods)): ?><a href="/memo.php" class="pf-nav-link <?= $activePage === 'memo' ? 'pf-nav-link--active' : '' ?>"><?= tr('menu_memo') ?></a><?php endif; ?>
      <?php if (in_array('todo',      $mods)): ?><a href="/todo.php" class="pf-nav-link <?= $activePage === 'todo' ? 'pf-nav-link--active' : '' ?>"><?= tr('menu_todo') ?></a><?php endif; ?>
      <?php if (in_array('food',      $mods)): ?><a href="/food.php" class="pf-nav-link <?= $activePage === 'food' ? 'pf-nav-link--active' : '' ?>"><?= tr('menu_food') ?></a><?php endif; ?>
      <?php if (in_array('calendar_ios', $mods)): ?><a href="/calendar-ios.php" class="pf-nav-link <?= $activePage === 'calendar-ios' ? 'pf-nav-link--active' : '' ?>"><?= tr('menu_calendar_ios') ?></a><?php endif; ?>
      <?php if (in_array('printvault',  $mods)): ?><a href="/printvault.php" class="pf-nav-link <?= $activePage === 'printvault' ? 'pf-nav-link--active' : '' ?>"><?= tr('menu_printvault') ?></a><?php endif; ?>
      <?php if (in_array('planka',      $mods)): ?><a href="/planka.php" class="pf-nav-link <?= $activePage === 'planka' ? 'pf-nav-link--active' : '' ?>"><?= tr('menu_planka') ?></a><?php endif; ?>
    </nav>
    <?php endif; ?>

    <div class="pf-header-right">
      <button class="theme-switch" id="theme-toggle" onclick="toggleTheme()" title="Thème sombre / clair" aria-label="Basculer le thème">
        <span class="ts-sun" aria-hidden="true">☀️</span>
        <span class="ts-track"><span class="ts-thumb"></span></span>
        <span class="ts-moon" aria-hidden="true">🌙</span>
      </button>

      <?php if (isset($_SESSION['user'])): ?>
        <div class="pf-desktop-actions" style="display: flex; align-items: center; gap: 10px; border-left: 1px solid #cbd5e1; padding-left: 15px;">
          <a href="/settings.php" class="pf-user-badge" style="text-decoration:none;color:inherit" title="Paramètres">
            ⚙️ <?= htmlspecialchars($_SESSION['user']['display_name'] ?? $_SESSION['user']['username']) ?>
          </a>
          <?php if (!empty($_SESSION['user']['is_admin'])): ?>
            <a href="/admin/" class="pf-nav-link" style="font-size:0.85rem;color:#2563eb" title="Admin">Admin</a>
          <?php endif; ?>
          <a href="/logout.php" class="pf-logout-btn"><?= tr('btn_logout') ?></a>
        </div>
        
        <button class="pf-burger-btn" aria-label="<?= tr('aria_menu') ?>" style="margin-left: 5px;">☰</button>
        
      <?php else: ?>
        <a href="/login.php" class="pf-nav-link" style="border-left: 1px solid #cbd5e1; padding-left: 15px; margin-left: 5px;"><?= tr('btn_login') ?></a>
      <?php endif; ?>
    </div>
  </header>

  <?php if (isset($_SESSION['user'])): ?>
  <div class="pf-mobile-overlay" id="mobileOverlay"></div>
  
  <div class="pf-mobile-menu" id="mobileMenu">
    <div class="pf-mobile-menu-header">
        <span class="pf-logo">HouseHub</span>
        <button class="pf-mobile-close-btn" id="closeMobileMenu">&times;</button>
    </div>
    
    <div class="pf-mobile-menu-body">
        <a href="/index.php" class="pf-mobile-nav-link">🏠 <?= tr('menu_home') ?></a>
        <a href="/family-calendar.php" class="pf-mobile-nav-link">📅 <?= tr('menu_calendar') ?></a>
        <?php if (in_array('budget',   $mods)): ?><a href="/budget.php" class="pf-mobile-nav-link">💰 <?= tr('menu_budget') ?></a><?php endif; ?>
        <?php if (in_array('holidays', $mods)): ?><a href="/holidays.php" class="pf-mobile-nav-link">🏖️ <?= tr('menu_holidays') ?></a><?php endif; ?>
        <?php if (in_array('gifts',    $mods)): ?><a href="/gift-list.php" class="pf-mobile-nav-link">🎁 <?= tr('menu_gifts') ?></a><?php endif; ?>
        <?php if (in_array('garage',   $mods)): ?><a href="/garage.php" class="pf-mobile-nav-link">🚗 <?= tr('menu_garage') ?></a><?php endif; ?>
        <?php if (in_array('memo',     $mods)): ?><a href="/memo.php" class="pf-mobile-nav-link">📝 <?= tr('menu_memo') ?></a><?php endif; ?>
        <?php if (in_array('todo',     $mods)): ?><a href="/todo.php" class="pf-mobile-nav-link">✅ <?= tr('menu_todo') ?></a><?php endif; ?>
        <?php if (in_array('food',     $mods)): ?><a href="/food.php" class="pf-mobile-nav-link">🍳 <?= tr('menu_food') ?></a><?php endif; ?>
        <?php if (in_array('calendar_ios', $mods)): ?><a href="/calendar-ios.php" class="pf-mobile-nav-link">📱 <?= tr('menu_calendar_ios') ?></a><?php endif; ?>
        <?php if (in_array('printvault',  $mods)): ?><a href="/printvault.php" class="pf-mobile-nav-link">🖨️ <?= tr('menu_printvault') ?></a><?php endif; ?>
        <?php if (in_array('planka',      $mods)): ?><a href="/planka.php" class="pf-mobile-nav-link">📋 <?= tr('menu_planka') ?></a><?php endif; ?>
        <a href="/settings.php" class="pf-mobile-nav-link">⚙️ Paramètres</a>
        <?php if (!empty($_SESSION['user']['is_admin'])): ?>
        <a href="/admin/" class="pf-mobile-nav-link" style="color:#2563eb">🛡️ Admin</a>
        <?php endif; ?>
        <a href="/logout.php" class="pf-mobile-nav-link pf-mobile-logout" style="margin-top: auto;">🚪 <?= tr('btn_logout') ?></a>
    </div>
  </div>
  <?php endif; ?>

  <main class="pf-main<?= ($mainClass ?? '') ? ' '.htmlspecialchars($mainClass) : '' ?>">

  <script>
    // CSRF avant I18N
    window.CSRF_TOKEN = "<?= htmlspecialchars(function_exists('csrf_token') ? csrf_token() : '', ENT_QUOTES, 'UTF-8') ?>";

    window.I18N = <?php echo json_encode($current_translations_array ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;

    window.CONFIG = {
        PEOPLE: <?= isset($familyPeople) ? json_encode($familyPeople) : '[]' ?>,
        CURRENT_USER_ID: <?= isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 'null' ?>,
        CURRENCY: '<?php echo defined('CURRENCY') ? CURRENCY : "€"; ?>',
        ZONE_SCOLAIRE: '<?php echo defined('ZONE_SCOLAIRE') ? ZONE_SCOLAIRE : "C"; ?>'
    };
    
    function tr(key) {
        return window.I18N[key] || key;
    }

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js').catch(err => {
                console.error('Service Worker registration failed:', err);
            });
        });
    }

    function toggleTheme(){
      const dark=document.documentElement.getAttribute('data-theme')==='dark';
      document.documentElement.setAttribute('data-theme',dark?'light':'dark');
      localStorage.setItem('hh-theme',dark?'light':'dark');
    }

    <?php if (isset($_SESSION['user'])): ?>
    document.addEventListener('DOMContentLoaded', () => {
        const burgerBtn = document.querySelector('.pf-burger-btn');
        const mobileMenu = document.getElementById('mobileMenu');
        const overlay = document.getElementById('mobileOverlay');
        const closeBtn = document.getElementById('closeMobileMenu');
        
        function openMenu() {
            mobileMenu.classList.add('is-open');
            overlay.classList.add('is-visible');
            document.body.classList.add('no-scroll');
        }

        function closeMenu() {
            mobileMenu.classList.remove('is-open');
            overlay.classList.remove('is-visible');
            document.body.classList.remove('no-scroll');
        }

        if(burgerBtn && mobileMenu && overlay && closeBtn) {
            burgerBtn.addEventListener('click', openMenu);
            closeBtn.addEventListener('click', closeMenu);
            overlay.addEventListener('click', closeMenu);
        }
    });
    <?php endif; ?>

    /**
     * pachaFetch : Utilitaire de requête robuste avec gestion Offline Cross-Browser
     */
    async function pachaFetch(url, options = {}) {
        const finalUrl = url.startsWith('/') ? url.substring(1) : url;

        options.credentials = 'same-origin'; 
        options.headers = {
            ...options.headers,
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };

        try {
            const response = await fetch(finalUrl, options);
            const rawText = await response.text();
            
            try {
                return JSON.parse(rawText);
            } catch (jsonErr) {
                console.error("Réponse corrompue (HTML) :", rawText);
                throw new Error("Erreur serveur : format JSON invalide.");
            }
        } catch (err) {
            // Si hors ligne ET modification (POST/PUT/DELETE)
            if (!navigator.onLine && options.method && options.method.toUpperCase() !== 'GET') {
                await enqueueOfflineAction(finalUrl, options);
                
                // Chrome/Android : Déclenche le Background Sync
                if ('serviceWorker' in navigator && 'SyncManager' in window) {
                    navigator.serviceWorker.ready.then(registration => {
                        registration.sync.register('househub-sync').catch(() => {});
                    });
                }

                showToast("Mémorisé hors ligne 📡", "success");
                return { success: true, offline: true };
            }

            console.error(`Erreur pachaFetch [${finalUrl}] :`, err);
            throw err;
        }
    }

    /**
     * Utilitaire PWA : Ajout à la file d'attente (IndexedDB)
     */
    function enqueueOfflineAction(url, options) {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open('househub-offline-sync', 1);
            request.onupgradeneeded = event => {
                event.target.result.createObjectStore('sync-queue', { autoIncrement: true });
            };
            request.onsuccess = event => {
                const db = event.target.result;
                const tx = db.transaction('sync-queue', 'readwrite');
                const store = tx.objectStore('sync-queue');
                
                let bodyData = options.body;
                if (bodyData instanceof FormData) {
                    bodyData = new URLSearchParams(bodyData).toString();
                    options.headers['Content-Type'] = 'application/x-www-form-urlencoded';
                }

                store.add({
                    url: url,
                    method: options.method,
                    headers: options.headers,
                    body: bodyData,
                    timestamp: Date.now()
                });
                resolve();
            };
            request.onerror = event => reject(event.target.error);
        });
    }

    /**
     * Fallback pour Firefox et iOS Safari (Dépilement manuel au retour réseau)
     */
    window.addEventListener('online', () => {
        const request = indexedDB.open('househub-offline-sync', 1);
        request.onsuccess = event => {
            const db = event.target.result;
            if (!db.objectStoreNames.contains('sync-queue')) return;
            
            const tx = db.transaction('sync-queue', 'readonly');
            const store = tx.objectStore('sync-queue');
            const getAll = store.getAll();
            
            getAll.onsuccess = async () => {
                const actions = getAll.result;
                if (actions && actions.length > 0) {
                    showToast("Synchronisation des données... 🔄", "success");
                    for (const action of actions) {
                        try {
                            await fetch(action.url, {
                                method: action.method,
                                headers: action.headers,
                                body: action.body
                            });
                            const delTx = db.transaction('sync-queue', 'readwrite');
                            delTx.objectStore('sync-queue').delete(action.id);
                        } catch (e) {
                            console.error("Échec de la synchro", e);
                            break; 
                        }
                    }
                    showToast("Synchronisation terminée ! ✅", "success");
                    // Rafraîchissement léger recommandé après synchro de masse
                    setTimeout(() => window.location.reload(), 1500);
                }
            };
        };
    });

    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `pf-toast pf-toast--${type}`;
        toast.innerHTML = message;
        document.body.appendChild(toast);
        
        setTimeout(() => toast.classList.add('is-visible'), 100);
        setTimeout(() => {
            toast.classList.remove('is-visible');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    async function pachaConfirm(title, message) {
        return new Promise((resolve) => {
            const modal = document.createElement('div');
            modal.className = 'pf-modal open';
            modal.innerHTML = `
                <div class="pf-modal-content" style="max-width: 400px; align-self: center;">
                    <h3 style="margin-top:0;">${title}</h3>
                    <p style="color:var(--text-muted);">${message}</p>
                    <div class="modal-footer">
                        <button class="pf-btn btn-secondary" id="confirm-cancel">${tr('btn_cancel')}</button>
                        <button class="pf-btn" id="confirm-ok" style="background:var(--danger);">${tr('btn_delete')}</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            document.getElementById('confirm-cancel').onclick = () => { modal.remove(); resolve(false); };
            document.getElementById('confirm-ok').onclick = () => { modal.remove(); resolve(true); };
        });
    }
  </script>