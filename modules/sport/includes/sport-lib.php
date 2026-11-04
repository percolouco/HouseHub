<?php
// Module Sport : calendrier des matchs des équipes suivies (football-data.org), stocké en base pour limiter les appels à l'API.
const SPORT_TTL_H = 6;              // pas de nouvel appel pour une équipe avant 6 h
const SPORT_MIN_FORCE_MIN = 10;     // « Actualiser » manuel : au plus une fois toutes les 10 min
const SPORT_DEFAULT_TEAMS = [[524, 'Paris Saint-Germain FC', 'PSG', 'PSG'], [81, 'FC Barcelona', 'Barça', 'FCB']];

function sportEnsureSchema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS pf_sport_settings (k VARCHAR(40) NOT NULL PRIMARY KEY, v TEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS pf_football_teams (
        team_id INT UNSIGNED NOT NULL PRIMARY KEY,
        name VARCHAR(100) NOT NULL, short_name VARCHAR(40) NOT NULL DEFAULT '', tla VARCHAR(8) NOT NULL DEFAULT '', crest VARCHAR(255) NOT NULL DEFAULT '',
        followed TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS pf_football_matches (
        match_id INT UNSIGNED NOT NULL PRIMARY KEY,
        competition_code VARCHAR(8) NOT NULL, competition_name VARCHAR(100) NOT NULL,
        season_year SMALLINT NULL, matchday SMALLINT NULL, stage VARCHAR(40) NOT NULL DEFAULT '',
        kickoff_utc DATETIME NOT NULL, status VARCHAR(20) NOT NULL,
        home_team_id INT UNSIGNED NULL, home_name VARCHAR(100) NOT NULL DEFAULT '', away_team_id INT UNSIGNED NULL, away_name VARCHAR(100) NOT NULL DEFAULT '',
        score_home TINYINT NULL, score_away TINYINT NULL,
        api_last_updated DATETIME NULL, fetched_at DATETIME NOT NULL,
        KEY idx_kickoff (kickoff_utc), KEY idx_home (home_team_id), KEY idx_away (away_team_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS pf_football_fetch (
        team_id INT UNSIGNED NOT NULL PRIMARY KEY, fetched_at DATETIME NOT NULL, nb_matches INT NOT NULL DEFAULT 0, http_code SMALLINT NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if ((int)$pdo->query("SELECT COUNT(*) FROM pf_football_teams")->fetchColumn() === 0) {
        $ins = $pdo->prepare("INSERT INTO pf_football_teams (team_id, name, short_name, tla, followed) VALUES (?, ?, ?, ?, 1)");
        foreach (SPORT_DEFAULT_TEAMS as $t) $ins->execute($t);
    }
}

function sportGetKey(PDO $pdo): string {
    $st = $pdo->prepare("SELECT v FROM pf_sport_settings WHERE k = 'football_api_key'");
    $st->execute();
    return (string)($st->fetchColumn() ?: '');
}

// Dernier appel API réussi ou non (toutes équipes suivies) ; null si jamais.
function sportLastFetch(PDO $pdo): ?string {
    $v = $pdo->query("SELECT MAX(fetched_at) FROM pf_football_fetch")->fetchColumn();
    return $v ?: null;
}

// Une équipe est « à rafraîchir » si jamais récupérée ou récupérée il y a plus de SPORT_TTL_H heures.
function sportStale(PDO $pdo): bool {
    $n = (int)$pdo->query("SELECT COUNT(*) FROM pf_football_teams t WHERE t.followed = 1 AND NOT EXISTS
        (SELECT 1 FROM pf_football_fetch f WHERE f.team_id = t.team_id AND f.http_code = 200 AND f.fetched_at > (NOW() - INTERVAL " . SPORT_TTL_H . " HOUR))")->fetchColumn();
    return $n > 0;
}

// Synchronise les équipes suivies. $force : ignore le TTL de 6 h (mais respecte SPORT_MIN_FORCE_MIN). Retourne ['appels' => n, 'erreurs' => [...]].
function sportSync(PDO $pdo, bool $force = false): array {
    $key = sportGetKey($pdo);
    $out = ['appels' => 0, 'erreurs' => []];
    if ($key === '') { $out['erreurs'][] = 'Clé football-data.org non renseignée (⚙️).'; return $out; }
    $utc = fn($s) => $s ? gmdate('Y-m-d H:i:s', strtotime($s)) : null;
    foreach ($pdo->query("SELECT team_id, name FROM pf_football_teams WHERE followed = 1")->fetchAll(PDO::FETCH_ASSOC) as $t) {
        $tid = (int)$t['team_id'];
        $st = $pdo->prepare("SELECT fetched_at, http_code FROM pf_football_fetch WHERE team_id = ?"); $st->execute([$tid]);
        $f = $st->fetch(PDO::FETCH_ASSOC);
        if ($f) {
            $age = time() - strtotime($f['fetched_at']);
            if (!$force && (int)$f['http_code'] === 200 && $age < SPORT_TTL_H * 3600) continue;
            if ($force && $age < SPORT_MIN_FORCE_MIN * 60) continue;
        }
        $ch = curl_init("https://api.football-data.org/v4/teams/$tid/matches");
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_HTTPHEADER => ['X-Auth-Token: ' . $key]]);
        $body = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        $out['appels']++;
        $j = json_decode((string)$body, true);
        if ($code !== 200 || !isset($j['matches'])) {
            $pdo->prepare("REPLACE INTO pf_football_fetch (team_id, fetched_at, nb_matches, http_code) VALUES (?, NOW(), 0, ?)")->execute([$tid, $code]);
            $out['erreurs'][] = $t['name'] . ' : ' . ($code === 403 || $code === 401 ? 'clé refusée' : ($code === 429 ? 'trop d\'appels, réessaie dans une minute' : "erreur HTTP $code"));
            continue;
        }
        // le nom court choisi à l'installation (PSG, Barça) est conservé si l'API n'en renvoie pas
        $pdo->prepare("UPDATE pf_football_teams SET name = IF(? <> '', ?, name), short_name = IF(? <> '', ?, short_name), tla = IF(? <> '', ?, tla), crest = IF(? <> '', ?, crest) WHERE team_id = ?")
            ->execute([$j['team']['name'] ?? '', $j['team']['name'] ?? '', $j['team']['shortName'] ?? '', $j['team']['shortName'] ?? '', $j['team']['tla'] ?? '', $j['team']['tla'] ?? '', $j['team']['crest'] ?? '', $j['team']['crest'] ?? '', $tid]);
        $up = $pdo->prepare("INSERT INTO pf_football_matches (match_id, competition_code, competition_name, season_year, matchday, stage, kickoff_utc, status, home_team_id, home_name, away_team_id, away_name, score_home, score_away, api_last_updated, fetched_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE kickoff_utc = VALUES(kickoff_utc), status = VALUES(status), matchday = VALUES(matchday), stage = VALUES(stage),
                home_name = VALUES(home_name), away_name = VALUES(away_name), score_home = VALUES(score_home), score_away = VALUES(score_away), api_last_updated = VALUES(api_last_updated), fetched_at = NOW()");
        $n = 0;
        foreach ($j['matches'] as $m) {
            $up->execute([$m['id'], $m['competition']['code'] ?? '', $m['competition']['name'] ?? '', (int)substr($m['season']['startDate'] ?? '0', 0, 4) ?: null, $m['matchday'] ?? null, $m['stage'] ?? '',
                $utc($m['utcDate']), $m['status'], $m['homeTeam']['id'] ?? null, $m['homeTeam']['shortName'] ?? ($m['homeTeam']['name'] ?? ''), $m['awayTeam']['id'] ?? null, $m['awayTeam']['shortName'] ?? ($m['awayTeam']['name'] ?? ''),
                $m['score']['fullTime']['home'] ?? null, $m['score']['fullTime']['away'] ?? null, $utc($m['lastUpdated'] ?? null)]);
            $n++;
        }
        $pdo->prepare("REPLACE INTO pf_football_fetch (team_id, fetched_at, nb_matches, http_code) VALUES (?, NOW(), ?, 200)")->execute([$tid, $n]);
    }
    return $out;
}
