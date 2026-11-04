<?php
// Module Sport : chaînes de diffusion des prochains matchs (3 sources, mises en cache en base, appels limités selon la proximité du match).
//  1. TheSportsDB (API gratuite, clé de test) : chaînes du prochain match de chaque équipe, par pays (communautaire, incomplet)
//  2. fcbarcelona.fr : article « Sur quelle chaîne et à quelle heure voir… » (chaînes par pays) pour les matchs du Barça
//  3. footmercato.net : programme TV du PSG (France)
const TV_HORIZON_DAYS = 10;      // seuls les matchs des 10 prochains jours sont interrogés
const TV_NEXT_PER_TEAM = 2;      // prochain match et suivant
const TV_THESPORTSDB = 'https://www.thesportsdb.com/api/v1/json/123';
const TV_TSDB_TEAMS = [524 => 133714, 81 => 133739];  // football-data → TheSportsDB (PSG, FC Barcelone)
const TV_UA = 'Mozilla/5.0 (compatible; HouseHub/2.9; calendrier familial)';

function tvEnsureSchema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS pf_football_tv (
        match_id INT UNSIGNED NOT NULL, source VARCHAR(20) NOT NULL, country VARCHAR(80) NOT NULL, channels VARCHAR(255) NOT NULL,
        PRIMARY KEY (match_id, source, country)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS pf_football_tv_fetch (
        match_id INT UNSIGNED NOT NULL, source VARCHAR(20) NOT NULL, fetched_at DATETIME NOT NULL, found INT NOT NULL DEFAULT 0,
        PRIMARY KEY (match_id, source)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function tvNorm(string $s): string {
    $s = mb_strtolower($s, 'UTF-8');
    $s = strtr($s, ['à'=>'a','â'=>'a','ä'=>'a','á'=>'a','ã'=>'a','ç'=>'c','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','í'=>'i','î'=>'i','ï'=>'i','ñ'=>'n','ó'=>'o','ô'=>'o','ö'=>'o','ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u']);
    return preg_replace('/[^a-z0-9]+/', '', $s);
}
// mots significatifs d'un nom d'équipe (≥ 4 lettres, hors mots génériques)
function tvTokens(string $name): array {
    $stop = ['club', 'football', 'futbol', 'deportivo'];
    $t = [];
    foreach (preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($name, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY) as $w) {
        $w = tvNorm($w);
        if (strlen($w) >= 4 && !in_array($w, $stop, true)) $t[] = $w;
    }
    return $t ?: [tvNorm($name)];
}
function tvMentions(string $haystackNorm, string $teamName): bool {
    foreach (tvTokens($teamName) as $tok) if ($tok !== '' && str_contains($haystackNorm, $tok)) return true;
    return false;
}
function tvHttp(string $url, int $timeout = 15): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_FOLLOWLOCATION => true, CURLOPT_USERAGENT => TV_UA, CURLOPT_HTTPHEADER => ['Accept-Language: fr,en;q=0.8']]);
    $b = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return ($code === 200 && is_string($b)) ? $b : null;
}
// Texte brut d'une page HTML, une ligne par bloc
function tvLines(string $html): array {
    $b = preg_replace('#<script.*?</script>|<style.*?</style>#si', '', $html);
    $t = preg_replace('#<(?:br|/p|/li|/h\d|/div|/tr|/td|/a|/span)[^>]*>#i', "\n", $b);
    $t = html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = str_replace("\xC2\xA0", ' ', $t);   // espaces insécables
    return array_values(array_filter(array_map('trim', preg_split('/\R/u', $t)), 'strlen'));
}

// Prochains matchs à interroger : TV_NEXT_PER_TEAM par équipe suivie, dans l'horizon, avec la fréquence de rafraîchissement selon la proximité.
function tvTargets(PDO $pdo): array {
    $out = [];
    foreach ($pdo->query("SELECT team_id FROM pf_football_teams WHERE followed = 1")->fetchAll(PDO::FETCH_COLUMN) as $tid) {
        $st = $pdo->prepare("SELECT match_id, kickoff_utc, home_team_id, away_team_id, home_name, away_name FROM pf_football_matches
            WHERE (home_team_id = ? OR away_team_id = ?) AND status NOT IN ('FINISHED', 'CANCELLED', 'AWARDED') AND kickoff_utc >= (UTC_TIMESTAMP() - INTERVAL 3 HOUR)
            ORDER BY kickoff_utc LIMIT " . TV_NEXT_PER_TEAM);
        $st->execute([$tid, $tid]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $m) {
            $dt = strtotime($m['kickoff_utc'] . ' UTC') - time();
            if ($dt > TV_HORIZON_DAYS * 86400) continue;
            $m['team_id'] = (int)$tid;
            $m['ttl'] = $dt < 24 * 3600 ? 3600 : ($dt < 3 * 86400 ? 6 * 3600 : 12 * 3600);   // jour de match : toutes les heures
            $out[$m['match_id'] . '-' . $tid] = $m;
        }
    }
    return $out;
}
function tvIsDue(PDO $pdo, array $m, string $source): bool {
    $st = $pdo->prepare("SELECT fetched_at FROM pf_football_tv_fetch WHERE match_id = ? AND source = ?");
    $st->execute([$m['match_id'], $source]);
    $f = $st->fetchColumn();
    return !$f || (time() - strtotime($f)) >= $m['ttl'];
}
function tvStore(PDO $pdo, int $matchId, string $source, array $byCountry): void {
    $pdo->prepare("DELETE FROM pf_football_tv WHERE match_id = ? AND source = ?")->execute([$matchId, $source]);
    $ins = $pdo->prepare("INSERT INTO pf_football_tv (match_id, source, country, channels) VALUES (?, ?, ?, ?)");
    foreach ($byCountry as $country => $chans) {
        $c = trim(implode(', ', array_unique($chans)));
        if ($country !== '' && $c !== '') $ins->execute([$matchId, $source, mb_substr($country, 0, 80), mb_substr($c, 0, 255)]);
    }
    $pdo->prepare("REPLACE INTO pf_football_tv_fetch (match_id, source, fetched_at, found) VALUES (?, ?, NOW(), ?)")->execute([$matchId, $source, count($byCountry)]);
}

// ── Source 1 : TheSportsDB
function tvSyncTheSportsDB(PDO $pdo, array $targets): void {
    $cache = [];
    foreach ($targets as $m) {
        if (!isset(TV_TSDB_TEAMS[$m['team_id']]) || !tvIsDue($pdo, $m, 'thesportsdb')) continue;
        $tsdb = TV_TSDB_TEAMS[$m['team_id']];
        if (!isset($cache[$tsdb])) { $j = json_decode((string)tvHttp(TV_THESPORTSDB . "/eventsnext.php?id=$tsdb"), true); $cache[$tsdb] = $j['events'] ?? []; }
        $opp = ((int)$m['home_team_id'] === $m['team_id']) ? $m['away_name'] : $m['home_name'];
        $found = null;
        foreach ($cache[$tsdb] as $e) {   // la clé gratuite ne renvoie que le prochain match : on le rapproche par date et adversaire
            $ds = abs(strtotime(($e['dateEvent'] ?? '') . ' UTC') - strtotime($m['kickoff_utc'] . ' UTC'));
            if ($ds <= 86400 && tvMentions(tvNorm((string)($e['strEvent'] ?? '')), $opp)) $found = $e;
        }
        if (!$found) { $pdo->prepare("REPLACE INTO pf_football_tv_fetch (match_id, source, fetched_at, found) VALUES (?, 'thesportsdb', NOW(), 0)")->execute([$m['match_id']]); continue; }
        $tv = json_decode((string)tvHttp(TV_THESPORTSDB . '/lookuptv.php?id=' . (int)$found['idEvent']), true);
        $by = [];
        foreach (($tv['tvevent'] ?? []) as $r) $by[(string)($r['strCountry'] ?? '')][] = (string)($r['strChannel'] ?? '');
        tvStore($pdo, (int)$m['match_id'], 'thesportsdb', $by);
    }
}

// ── Source 2 : fcbarcelona.fr (matchs du Barça)
function tvSyncBarca(PDO $pdo, array $targets): void {
    $list = null;
    foreach ($targets as $m) {
        if ($m['team_id'] !== 81 || !tvIsDue($pdo, $m, 'fcbarcelona')) continue;
        if ($list === null) {
            $list = [];
            $html = tvHttp('https://www.fcbarcelona.fr/fr/football/equipe-premiere/actualites/');
            if ($html && preg_match_all('#href="(/fr/football/equipe-premiere/actualites/\d+/sur-quelle-chaine[^"]*)"#', $html, $mm)) $list = array_values(array_unique($mm[1]));
        }
        $opp = ((int)$m['home_team_id'] === 81) ? $m['away_name'] : $m['home_name'];
        $slug = null;
        foreach ($list as $u) if (tvMentions(tvNorm($u), $opp)) { $slug = $u; break; }
        if (!$slug) { $pdo->prepare("REPLACE INTO pf_football_tv_fetch (match_id, source, fetched_at, found) VALUES (?, 'fcbarcelona', NOW(), 0)")->execute([$m['match_id']]); continue; }
        $page = tvHttp('https://www.fcbarcelona.fr' . $slug, 20);
        if (!$page) continue;
        $L = tvLines($page); $by = []; $on = false;
        for ($i = 0; $i < count($L); $i++) {
            if (!$on) { if (stripos($L[$i], 'horaires internationaux') !== false) $on = true; continue; }
            // « Canada - Vancouver (1.00 PM) / Toronto (3.00 PM) » puis « FC Barcelona: @… » puis la ligne des chaînes
            if (preg_match('/\(\d{1,2}[.:]\d{2}\s*[AP]?M?\)/i', $L[$i]) && !preg_match('/^FC[\p{Z}\s]*Barcelona/iu', $L[$i])) {
                $country = trim(preg_replace('/\s*\(.*$/u', '', preg_split('/\s+-\s+/u', $L[$i], 2)[0]));
                if ($country === 'Barcelone') $country = 'Espagne';
                $j = $i + 1; if (isset($L[$j]) && preg_match('/^FC[\p{Z}\s]*Barcelona[\p{Z}\s]*:/iu', $L[$j])) $j++;
                // ligne des chaînes : ni heure, ni comptes / sites du club
                if (isset($L[$j]) && !preg_match('/\(\d{1,2}[.:]\d{2}/', $L[$j]) && !preg_match('/@|www\.|^FC[\p{Z}\s]*Barcelona/iu', $L[$j]) && mb_strlen($L[$j]) < 160) $by[$country] = array_map('trim', explode(',', $L[$j]));
            }
            if (count($by) >= 25 || stripos($L[$i], 'partager') === 0 && $by) break;
        }
        if ($by) tvStore($pdo, (int)$m['match_id'], 'fcbarcelona', $by);
        else $pdo->prepare("REPLACE INTO pf_football_tv_fetch (match_id, source, fetched_at, found) VALUES (?, 'fcbarcelona', NOW(), 0)")->execute([$m['match_id']]);
    }
}

// ── Source 3 : footmercato.net (programme TV du PSG, France) : logos des diffuseurs dans <span class="matchFull__broadcasts">
function tvSyncFootMercato(PDO $pdo, array $targets): void {
    $prog = null;
    foreach ($targets as $m) {
        if ($m['team_id'] !== 524 || !tvIsDue($pdo, $m, 'footmercato')) continue;
        if ($prog === null) {
            $prog = [];
            $html = tvHttp('https://www.footmercato.net/programme-tv/club/psg/', 20);
            if ($html) foreach (array_slice(preg_split('#<div class="matchFull"#', $html), 1) as $blk) {
                if (!preg_match('#<time datetime="([^"]+)"#', $blk, $t)) continue;
                $chans = [];
                if (preg_match('#matchFull__broadcasts">(.*?)</span>#s', $blk, $bm)) {
                    preg_match_all('#alt="([^"]*)"#', $bm[1], $alts);
                    foreach ($alts[1] as $a) { $a = trim(preg_replace('/^Logo\s+/iu', '', html_entity_decode($a, ENT_QUOTES | ENT_HTML5, 'UTF-8'))); if ($a !== '') $chans[] = $a; }
                }
                $prog[] = ['ts' => strtotime($t[1]), 'chans' => $chans];
            }
        }
        $by = [];
        foreach ($prog as $p) {   // le PSG ne joue qu'un match à la fois : on rapproche par l'heure du coup d'envoi (±3 h)
            if ($p['chans'] && abs($p['ts'] - strtotime($m['kickoff_utc'] . ' UTC')) <= 3 * 3600) $by['France'] = $p['chans'];
        }
        tvStore($pdo, (int)$m['match_id'], 'footmercato', $by);
    }
}

function tvSync(PDO $pdo): void {
    tvEnsureSchema($pdo);
    $targets = tvTargets($pdo);
    if (!$targets) return;
    foreach (['tvSyncTheSportsDB', 'tvSyncBarca', 'tvSyncFootMercato'] as $fn) { try { $fn($pdo, $targets); } catch (Throwable $e) { /* une source en échec ne bloque pas les autres */ } }
}

// Chaînes connues par match : [match_id => [[source, country, channels], …]]
function tvLoad(PDO $pdo, array $matchIds): array {
    tvEnsureSchema($pdo);
    if (!$matchIds) return [];
    $in = implode(',', array_map('intval', $matchIds));
    $out = [];
    foreach ($pdo->query("SELECT match_id, source, country, channels FROM pf_football_tv WHERE match_id IN ($in) ORDER BY country")->fetchAll(PDO::FETCH_ASSOC) as $r)
        $out[(int)$r['match_id']][] = ['source' => $r['source'], 'country' => $r['country'], 'channels' => $r['channels']];
    return $out;
}
