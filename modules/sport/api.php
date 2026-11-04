<?php
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once __DIR__ . '/includes/sport-lib.php';
require_login();
header('Content-Type: application/json; charset=utf-8');

function sOk($d = []) { echo json_encode(['success' => true] + $d, JSON_UNESCAPED_UNICODE); exit; }
function sErr($m, $c = 400) { http_response_code($c); echo json_encode(['success' => false, 'error' => $m], JSON_UNESCAPED_UNICODE); exit; }

try {
    sportEnsureSchema($pdo);
    $action = $_POST['action'] ?? $_GET['action'] ?? 'list';

    if ($action === 'list') {
        $teams = $pdo->query("SELECT team_id, name, short_name, tla, crest FROM pf_football_teams WHERE followed = 1 ORDER BY team_id DESC")->fetchAll(PDO::FETCH_ASSOC);
        $ids = array_map(fn($t) => (int)$t['team_id'], $teams);
        $matches = [];
        if ($ids) {
            $in = implode(',', $ids);
            $rows = $pdo->query("SELECT match_id, competition_code, competition_name, matchday, stage, kickoff_utc, status, home_team_id, home_name, away_team_id, away_name, score_home, score_away
                                 FROM pf_football_matches WHERE home_team_id IN ($in) OR away_team_id IN ($in) ORDER BY kickoff_utc ASC")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $r['kickoff'] = gmdate('Y-m-d\TH:i:s\Z', strtotime($r['kickoff_utc'] . ' UTC'));
                unset($r['kickoff_utc']);
                $matches[] = $r;
            }
        }
        $last = sportLastFetch($pdo);
        sOk(['teams' => $teams, 'matches' => $matches, 'has_key' => sportGetKey($pdo) !== '', 'stale' => sportStale($pdo),
             'last_fetch' => $last ? gmdate('Y-m-d\TH:i:s\Z', strtotime($last)) : null]);
    }

    // Actions qui écrivent : jeton CSRF obligatoire
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf'] ?? null)) sErr(tr('error_csrf'), 403);

    if ($action === 'refresh') {
        $r = sportSync($pdo, !empty($_POST['force']));
        sOk(['appels' => $r['appels'], 'erreurs' => $r['erreurs']]);
    }
    if ($action === 'save_key') {
        $k = trim($_POST['key'] ?? '');
        if (!preg_match('/^[A-Za-z0-9]{16,64}$/', $k)) sErr('Clé invalide (16 à 64 lettres ou chiffres).');
        $pdo->prepare("REPLACE INTO pf_sport_settings (k, v) VALUES ('football_api_key', ?)")->execute([$k]);
        $pdo->exec("DELETE FROM pf_football_fetch"); // nouvelle clé : on repart d'un appel frais
        $r = sportSync($pdo, false);
        sOk(['appels' => $r['appels'], 'erreurs' => $r['erreurs']]);
    }
    if ($action === 'remove_key') {
        $pdo->exec("DELETE FROM pf_sport_settings WHERE k = 'football_api_key'");
        sOk();
    }
    sErr('Action inconnue.');
} catch (Throwable $e) {
    sErr('Erreur serveur : ' . $e->getMessage(), 500);
}
