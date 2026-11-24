<?php
// API JSON de l'onglet « Chantiers » du budget
header('Content-Type: application/json');
require_once __DIR__ . '/../../../../includes/auth.php';
require_once __DIR__ . '/../../../../includes/db.php';
require_once __DIR__ . '/../../../../includes/i18n.php';
require_once __DIR__ . '/../projects_schema.php';
require_login();

function out($d) { echo json_encode($d); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['success' => false, 'error' => 'Method not allowed']);
projectsEnsureSchema($pdo);

$action = $_POST['action'] ?? '';
$STATUSES = ['idea', 'quote', 'planned', 'doing', 'done'];
$num = fn($k, $d = 0.0) => isset($_POST[$k]) && $_POST[$k] !== '' ? (float)str_replace(',', '.', $_POST[$k]) : $d;
$str = fn($k, $max = 200) => mb_substr(trim((string)($_POST[$k] ?? '')), 0, $max, 'UTF-8');
$id = (int)($_POST['id'] ?? 0);
$pid = (int)($_POST['project_id'] ?? 0);
// seules les adresses http(s) sont gardées comme liens
$cleanUrl = function (string $u): string { $u = trim($u); return preg_match('#^https?://#i', $u) ? mb_substr($u, 0, 500, 'UTF-8') : ''; };
$projectExists = function (int $p) use ($pdo) { $s = $pdo->prepare("SELECT 1 FROM pf_projects WHERE id = ?"); $s->execute([$p]); return (bool)$s->fetchColumn(); };

try {
    switch ($action) {
        case 'project_save':
            $name = $str('name', 150);
            if ($name === '') out(['success' => false, 'error' => 'Nom requis']);
            $status = in_array($_POST['status'] ?? '', $STATUSES, true) ? $_POST['status'] : 'idea';
            $icon = $str('icon', 16) ?: '🔨';
            $target = ($_POST['target_budget'] ?? '') !== '' ? max(0, $num('target_budget')) : null;
            if ($id) {
                $pdo->prepare("UPDATE pf_projects SET name=?, icon=?, status=?, target_budget=? WHERE id=?")->execute([$name, $icon, $status, $target, $id]);
            } else {
                $pdo->prepare("INSERT INTO pf_projects (name, icon, status, target_budget, sort_order) VALUES (?,?,?,?, (SELECT COALESCE(MAX(s.sort_order),0)+1 FROM pf_projects s))")->execute([$name, $icon, $status, $target]);
                $id = (int)$pdo->lastInsertId();
            }
            out(['success' => true, 'id' => $id]);

        case 'project_notes':
            $pdo->prepare("UPDATE pf_projects SET notes=? WHERE id=?")->execute([mb_substr((string)($_POST['notes'] ?? ''), 0, 20000, 'UTF-8'), $id]);
            out(['success' => true]);

        case 'project_dims':
            $d = fn($k) => ($_POST[$k] ?? '') !== '' ? max(0, $num($k)) : null;
            $pdo->prepare("UPDATE pf_projects SET dim_w=?, dim_l=?, dim_h=? WHERE id=?")->execute([$d('dim_w'), $d('dim_l'), $d('dim_h'), $id]);
            out(['success' => true]);

        case 'project_delete':
            $pdo->prepare("DELETE FROM pf_project_items WHERE project_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM pf_project_tools WHERE project_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM pf_project_links WHERE project_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM pf_projects WHERE id=?")->execute([$id]);
            out(['success' => true]);

        case 'item_save':
            if (!$projectExists($pid)) out(['success' => false, 'error' => 'Chantier introuvable']);
            $kind = ($_POST['kind'] ?? '') === 'labor' ? 'labor' : 'material';
            $enteredHt = !empty($_POST['entered_ht']);
            $vat = min(100, max(0, $num('vat_rate', 20)));
            $p = projectPrices(max(0, $num('price')), $enteredHt, $vat);
            $vals = [$kind, $str('lot', 100), $str('name', 200), $str('store', 100), $cleanUrl((string)($_POST['url'] ?? '')),
                     max(0, $num('qty', 1)), $str('unit', 20), $p['ttc'], $p['ht'], $vat, $enteredHt ? 1 : 0, $str('note', 255), !empty($_POST['is_bought']) ? 1 : 0];
            if ($id) {
                $pdo->prepare("UPDATE pf_project_items SET kind=?, lot=?, name=?, store=?, url=?, qty=?, unit=?, unit_ttc=?, unit_ht=?, vat_rate=?, entered_ht=?, note=?, is_bought=? WHERE id=? AND project_id=?")
                    ->execute(array_merge($vals, [$id, $pid]));
            } else {
                $pdo->prepare("INSERT INTO pf_project_items (kind, lot, name, store, url, qty, unit, unit_ttc, unit_ht, vat_rate, entered_ht, note, is_bought, project_id, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?, (SELECT COALESCE(MAX(s.sort_order),0)+1 FROM pf_project_items s WHERE s.project_id = ?))")
                    ->execute(array_merge($vals, [$pid, $pid]));
                $id = (int)$pdo->lastInsertId();
            }
            out(['success' => true, 'id' => $id, 'line_ttc' => round($vals[5] * $p['ttc'], 2), 'line_ht' => round($vals[5] * $p['ht'], 2), 'totals' => projectTotals($pdo, $pid)]);

        case 'item_delete':
            $pdo->prepare("DELETE FROM pf_project_items WHERE id=? AND project_id=?")->execute([$id, $pid]);
            out(['success' => true, 'totals' => projectTotals($pdo, $pid)]);

        case 'item_bought':
            $pdo->prepare("UPDATE pf_project_items SET is_bought=? WHERE id=? AND project_id=?")->execute([!empty($_POST['is_bought']) ? 1 : 0, $id, $pid]);
            out(['success' => true, 'totals' => projectTotals($pdo, $pid)]);

        case 'tool_save':
            if (!$projectExists($pid)) out(['success' => false, 'error' => 'Chantier introuvable']);
            if ($id) $pdo->prepare("UPDATE pf_project_tools SET name=?, lender=? WHERE id=? AND project_id=?")->execute([$str('name', 150), $str('lender', 100), $id, $pid]);
            else { $pdo->prepare("INSERT INTO pf_project_tools (project_id, name, lender) VALUES (?,?,?)")->execute([$pid, $str('name', 150), $str('lender', 100)]); $id = (int)$pdo->lastInsertId(); }
            out(['success' => true, 'id' => $id]);

        case 'tool_delete':
            $pdo->prepare("DELETE FROM pf_project_tools WHERE id=? AND project_id=?")->execute([$id, $pid]);
            out(['success' => true]);

        case 'link_save':
            if (!$projectExists($pid)) out(['success' => false, 'error' => 'Chantier introuvable']);
            $url = $cleanUrl((string)($_POST['url'] ?? ''));
            if ($url === '') out(['success' => false, 'error' => 'Adresse http(s) requise']);
            if ($id) $pdo->prepare("UPDATE pf_project_links SET title=?, url=? WHERE id=? AND project_id=?")->execute([$str('title', 200), $url, $id, $pid]);
            else { $pdo->prepare("INSERT INTO pf_project_links (project_id, title, url) VALUES (?,?,?)")->execute([$pid, $str('title', 200), $url]); $id = (int)$pdo->lastInsertId(); }
            out(['success' => true, 'id' => $id]);

        case 'link_delete':
            $pdo->prepare("DELETE FROM pf_project_links WHERE id=? AND project_id=?")->execute([$id, $pid]);
            out(['success' => true]);

        case 'project_provision':
            // Crée (ou met à jour, tant qu'elle n'est pas payée) la provision du chantier dans « Provisions »
            $amount = round(max(0, $num('amount')), 2);
            $date = (string)($_POST['expected_date'] ?? '');
            if ($amount <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) out(['success' => false, 'error' => 'Montant et date requis']);
            $st = $pdo->prepare("SELECT name, provision_id FROM pf_projects WHERE id=?"); $st->execute([$id]);
            $p = $st->fetch(PDO::FETCH_ASSOC);
            if (!$p) out(['success' => false, 'error' => 'Chantier introuvable']);
            $title = mb_substr('🔨 ' . $p['name'], 0, 150, 'UTF-8');
            $updated = false;
            if (!empty($p['provision_id'])) {
                $u = $pdo->prepare("UPDATE pf_expected_expenses SET title=?, amount=?, expected_date=? WHERE id=? AND is_paid=0");
                $u->execute([$title, $amount, $date, $p['provision_id']]);
                $c = $pdo->prepare("SELECT COUNT(*) FROM pf_expected_expenses WHERE id=? AND is_paid=0"); $c->execute([$p['provision_id']]);
                $updated = (int)$c->fetchColumn() > 0;
            }
            if (!$updated) {
                $pdo->prepare("INSERT INTO pf_expected_expenses (title, amount, expected_date) VALUES (?,?,?)")->execute([$title, $amount, $date]);
                $pdo->prepare("UPDATE pf_projects SET provision_id=? WHERE id=?")->execute([(int)$pdo->lastInsertId(), $id]);
            }
            out(['success' => true, 'updated' => $updated]);
    }
    out(['success' => false, 'error' => 'Action inconnue']);
} catch (PDOException $e) {
    out(['success' => false, 'error' => 'Erreur BDD : ' . $e->getMessage()]);
}
