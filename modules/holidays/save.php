<?php
require __DIR__ . '/../../includes/auth.php';
require_login('/login.php');
require __DIR__ . '/../../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo 'Method Not Allowed';
  exit;
}

$action = $_POST['action'] ?? '';

function hol_back(string $fallback = '/holidays.php'): void {
  $to = $_SERVER['HTTP_REFERER'] ?? $fallback;
  header("Location: $to");
  exit;
}

/**
 * Normalise un décimal saisi (accepte virgule ou point), retourne float|NULL
 */
function hol_norm_decimal($v): ?float {
  if (!isset($v)) return null;
  $s = trim((string)$v);
  if ($s === '') return null;
  $s = str_replace(',', '.', $s);
  return is_numeric($s) ? (float)$s : null;
}

/**
 * Normalise une date (YYYY-MM-DD) : '' -> NULL, sinon retourne la chaîne telle quelle
 */
function hol_norm_date($v): ?string {
  if (!isset($v)) return null;
  $s = trim((string)$v);
  return $s === '' ? null : $s;
}

try {
  switch ($action) {
    case 'create_idea': {
      $status = $_POST['status'] ?? 'draft';
      $start  = hol_norm_date($_POST['desired_start_date'] ?? null);
      $end    = hol_norm_date($_POST['desired_end_date'] ?? null);
      if (!empty($start) && $status === 'draft') { $status = 'planned'; }

      $latVal = hol_norm_decimal($_POST['lat'] ?? null);
      $lngVal = hol_norm_decimal($_POST['lng'] ?? null);

      $stmt = $pdo->prepare("
        INSERT INTO pf_holidays_ideas
          (title, country, region, city, lat, lng, desired_start_date, desired_end_date, season_hint, ideal_days, status, notes)
        VALUES
          (:title,:country,:region,:city,:lat,:lng,:start,:end,:season,:days,:status,:notes)
      ");
      $stmt->execute([
        ':title'  => trim($_POST['title'] ?? ''),
        ':country'=> trim($_POST['country'] ?? ''),
        ':region' => trim($_POST['region'] ?? ''),
        ':city'   => trim($_POST['city'] ?? ''),
        ':lat'    => $latVal,
        ':lng'    => $lngVal,
        ':start'  => $start,
        ':end'    => $end,
        ':season' => trim($_POST['season_hint'] ?? ''),
        ':days'   => ($_POST['ideal_days'] !== '' ? (int)$_POST['ideal_days'] : null),
        ':status' => $status,
        ':notes'  => trim($_POST['notes'] ?? ''),
      ]);
      $newId = (int)$pdo->lastInsertId();
      header("Location: /holidays.php?id={$newId}");
      exit;
    }

    case 'update_idea': {
      $status = $_POST['status'] ?? 'draft';
      $start  = hol_norm_date($_POST['desired_start_date'] ?? null);
      $end    = hol_norm_date($_POST['desired_end_date'] ?? null);
      if (!empty($start) && $status === 'draft') { $status = 'planned'; }

      $latVal = hol_norm_decimal($_POST['lat'] ?? null);
      $lngVal = hol_norm_decimal($_POST['lng'] ?? null);

      $stmt = $pdo->prepare("
        UPDATE pf_holidays_ideas
        SET title=:title, country=:country, region=:region, city=:city, lat=:lat, lng=:lng,
            desired_start_date=:start, desired_end_date=:end,
            season_hint=:season, ideal_days=:days, status=:status, notes=:notes
        WHERE id=:id
      ");
      $stmt->execute([
        ':id'     => (int)$_POST['id'],
        ':title'  => trim($_POST['title'] ?? ''),
        ':country'=> trim($_POST['country'] ?? ''),
        ':region' => trim($_POST['region'] ?? ''),
        ':city'   => trim($_POST['city'] ?? ''),
        ':lat'    => $latVal,
        ':lng'    => $lngVal,
        ':start'  => $start,
        ':end'    => $end,
        ':season' => trim($_POST['season_hint'] ?? ''),
        ':days'   => ($_POST['ideal_days'] !== '' ? (int)$_POST['ideal_days'] : null),
        ':status' => $status,
        ':notes'  => trim($_POST['notes'] ?? ''),
      ]);
      hol_back();
    }

    case 'delete_idea': {
      $stmt = $pdo->prepare("DELETE FROM pf_holidays_ideas WHERE id = :id");
      $stmt->execute([':id' => (int)$_POST['id']]);
      header("Location: /holidays.php");
      exit;
    }

    case 'add_transport': {
      $stmt = $pdo->prepare("
        INSERT INTO pf_holidays_transport (idea_id, mode, duration_min, cost, co2_kg, link, notes)
        VALUES (:id,:mode,:dur,:cost,:co2,:link,:notes)
      ");
      $stmt->execute([
        ':id'   => (int)$_POST['idea_id'],
        ':mode' => strtoupper($_POST['mode'] ?? 'OTHER'),
        ':dur'  => ($_POST['duration_min'] !== '' ? (int)$_POST['duration_min'] : null),
        ':cost' => hol_norm_decimal($_POST['cost'] ?? null),
        ':co2'  => hol_norm_decimal($_POST['co2_kg'] ?? null),
        ':link' => trim($_POST['link'] ?? ''),
        ':notes'=> trim($_POST['notes'] ?? ''),
      ]);
      hol_back();
    }

    case 'add_lodging': {
      $stmt = $pdo->prepare("
        INSERT INTO pf_holidays_lodging (idea_id, type, location_text, price_per_n, nights, free_cancel, family_friendly, link, notes)
        VALUES (:id,:type,:loc,:ppn,:n,:fc,:ff,:link,:notes)
      ");
      $stmt->execute([
        ':id'   => (int)$_POST['idea_id'],
        ':type' => strtoupper($_POST['type'] ?? 'OTHER'),
        ':loc'  => trim($_POST['location_text'] ?? ''),
        ':ppn'  => hol_norm_decimal($_POST['price_per_n'] ?? null),
        ':n'    => ($_POST['nights'] !== '' ? (int)$_POST['nights'] : null),
        ':fc'   => isset($_POST['free_cancel']) ? 1 : 0,
        ':ff'   => isset($_POST['family_friendly']) ? 1 : 0,
        ':link' => trim($_POST['link'] ?? ''),
        ':notes'=> trim($_POST['notes'] ?? ''),
      ]);
      hol_back();
    }

    case 'add_activity': {
      $stmt = $pdo->prepare("
        INSERT INTO pf_holidays_activities (idea_id, name, kind, cost_est, need_booking, weather, link, notes)
        VALUES (:id,:name,:kind,:cost,:need,:weather,:link,:notes)
      ");
      $stmt->execute([
        ':id'     => (int)$_POST['idea_id'],
        ':name'   => trim($_POST['name'] ?? ''),
        ':kind'   => trim($_POST['kind'] ?? ''),
        ':cost'   => hol_norm_decimal($_POST['cost_est'] ?? null),
        ':need'   => isset($_POST['need_booking']) ? 1 : 0,
        ':weather'=> strtoupper($_POST['weather'] ?? 'ANY'),
        ':link'   => trim($_POST['link'] ?? ''),
        ':notes'  => trim($_POST['notes'] ?? ''),
      ]);
      hol_back();
    }

    case 'add_budget': {
      $stmt = $pdo->prepare("
        INSERT INTO pf_holidays_budget_items (idea_id, category, label, amount, per_person)
        VALUES (:id,:cat,:label,:amt,:pp)
      ");
      $stmt->execute([
        ':id'    => (int)$_POST['idea_id'],
        ':cat'   => strtoupper($_POST['category'] ?? 'OTHER'),
        ':label' => trim($_POST['label'] ?? ''),
        ':amt'   => hol_norm_decimal($_POST['amount'] ?? null) ?? 0.0,
        ':pp'    => isset($_POST['per_person']) ? 1 : 0,
      ]);
      hol_back();
    }

    default:
      http_response_code(400);
      echo 'Unknown action';
      exit;
  }
} catch (Throwable $e) {
  http_response_code(500);
  echo "Error: " . htmlspecialchars($e->getMessage());
}
