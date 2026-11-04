<?php
// Préférences d'affichage par utilisateur : mode « apps » (icônes façon mobile) + ordre des apps.

/** Modules affichables comme « apps » : clé => [lien, icône, clé de traduction, dégradé]. */
function hhAppRegistry(): array {
    return [
        'calendar'     => ['/family-calendar.php', '📅', 'menu_calendar',     ['#ef4444', '#f97316']],
        'budget'       => ['/budget.php',          '💰', 'menu_budget',       ['#10b981', '#059669']],
        'holidays'     => ['/holidays.php',        '🏖️', 'menu_holidays',     ['#0ea5e9', '#06b6d4']],
        'gifts'        => ['/gift-list.php',       '🎁', 'menu_gifts',        ['#ec4899', '#f43f5e']],
        'garage'       => ['/garage.php',          '🚗', 'menu_garage',       ['#64748b', '#334155']],
        'memo'         => ['/memo.php',            '📝', 'menu_memo',         ['#eab308', '#f59e0b']],
        'sport'        => ['/sport.php',           '⚽', 'menu_sport',        ['#22c55e', '#16a34a']],
        'todo'         => ['/todo.php',            '✅', 'menu_todo',         ['#6366f1', '#8b5cf6']],
        'food'         => ['/food.php',            '🍳', 'menu_food',         ['#f97316', '#ea580c']],
        'calendar_ios' => ['/calendar-ios.php',    '📱', 'menu_calendar_ios', ['#3b82f6', '#2563eb']],
        'printvault'   => ['/printvault.php',      '🖨️', 'menu_printvault',   ['#14b8a6', '#0f766e']],
        'planka'       => ['/planka.php',          '📋', 'menu_planka',       ['#a855f7', '#7e22ce']],
        'settings'     => ['/settings.php',        '⚙️', 'apps_settings',     ['#94a3b8', '#64748b']],
        'admin'        => ['/admin/',              '🛡️', 'apps_admin',        ['#2563eb', '#1d4ed8']],
    ];
}

/** Lit (et met en cache en session) le mode d'affichage et l'ordre des apps. */
function hhUiPrefs(bool $refresh = false): array {
    if (!$refresh && isset($_SESSION['ui_prefs'])) return $_SESSION['ui_prefs'];
    $prefs = ['mode' => 'classic', 'order' => []];
    $uid = (int)($_SESSION['user']['id'] ?? 0);
    if ($uid) {
        global $meta_pdo;
        require_once __DIR__ . '/meta_db.php';
        $sel = fn() => $meta_pdo->query("SELECT ui_mode, app_order FROM users WHERE id = " . $uid)->fetch(PDO::FETCH_ASSOC);
        try {
            $row = $sel();
        } catch (Throwable $e) {
            try {
                $meta_pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS ui_mode VARCHAR(10) NOT NULL DEFAULT 'classic'");
                $meta_pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS app_order TEXT NULL");
                $row = $sel();
            } catch (Throwable $e2) { $row = null; }
        }
        if ($row) {
            $prefs['mode']  = $row['ui_mode'] === 'apps' ? 'apps' : 'classic';
            $o = json_decode($row['app_order'] ?? '[]', true);
            $prefs['order'] = is_array($o) ? array_values(array_filter($o, 'is_string')) : [];
        }
    }
    return $_SESSION['ui_prefs'] = $prefs;
}

/** Apps visibles pour l'utilisateur courant, dans son ordre. */
function hhAppsList(): array {
    $reg  = hhAppRegistry();
    $mods = $_SESSION['enabled_modules'] ?? [];
    $keys = array_filter(array_keys($reg), function ($k) use ($mods) {
        if ($k === 'settings') return true;
        if ($k === 'admin')    return !empty($_SESSION['user']['is_admin']);
        return in_array($k, $mods);
    });
    $order = hhUiPrefs()['order'];
    usort($keys, function ($a, $b) use ($order) {
        $pa = array_search($a, $order, true); $pb = array_search($b, $order, true);
        return ($pa === false ? 999 : $pa) <=> ($pb === false ? 999 : $pb);
    });
    $out = [];
    foreach ($keys as $k) {
        [$href, $icon, $label, $grad] = $reg[$k];
        $out[] = ['key' => $k, 'href' => $href, 'icon' => $icon, 'label' => tr($label), 'grad' => $grad];
    }
    return $out;
}
