<?php
// modules/budget/views/chantiers.php — chiffrage des travaux / réorganisations (cartes + page de chantier)
require_once __DIR__ . '/../includes/projects_schema.php';
projectsEnsureSchema($pdo);

$STATUSES = ['idea' => ['💡', 'ch_st_idea', '#64748b'], 'quote' => ['🧮', 'ch_st_quote', '#d97706'], 'planned' => ['📅', 'ch_st_planned', '#2563eb'],
             'doing' => ['🔨', 'ch_st_doing', '#7c3aed'], 'done' => ['✅', 'ch_st_done', '#16a34a']];
$eur = fn($v) => number_format((float)$v, 2, ',', ' ') . ' €';
$pid = (int)($_GET['p'] ?? 0);
$project = null;
if ($pid) {
    $st = $pdo->prepare("SELECT * FROM pf_projects WHERE id = ?");
    $st->execute([$pid]);
    $project = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}
$T = [
    'confirmDelProject' => tr('ch_confirm_del_project'), 'confirmDelLine' => tr('ch_confirm_del_line'), 'confirmDelItem' => tr('ch_confirm_del_item'),
    'pickHint' => tr('ch_pick_hint'), 'provisionOk' => tr('ch_provision_ok'), 'provisionUpd' => tr('ch_provision_upd'), 'error' => tr('error_generic'),
    'hint' => tr('ch_ht_hint'),
];
?>
<div class="budget-view ch-view">
<?php if (!$project): /* ======================= LISTE DES CARTES ======================= */
    $projects = $pdo->query("SELECT * FROM pf_projects ORDER BY FIELD(status,'doing','planned','quote','idea','done'), sort_order, id")->fetchAll(PDO::FETCH_ASSOC);
    $cards = []; $grand = 0; $grandBought = 0;
    foreach ($projects as $p) {
        $t = projectTotals($pdo, (int)$p['id']);
        $cards[] = [$p, $t];
        if ($p['status'] !== 'done') { $grand += $t['ttc']; $grandBought += $t['bought_ttc']; }
    }
?>
    <div class="view-header ch-head">
        <h2>🔨 <?= tr('ch_title') ?></h2>
        <div class="ch-grand" title="<?= tr('ch_grand_hint') ?>"><span><?= tr('ch_grand_total') ?></span> <strong><?= $eur($grand) ?></strong>
            <small><?= tr('ch_remaining') ?> : <?= $eur($grand - $grandBought) ?></small></div>
    </div>
    <div class="ch-filters" id="ch-filters">
        <button type="button" class="ch-chip active" data-st="all"><?= tr('ch_all') ?></button>
        <?php foreach ($STATUSES as $k => $s): ?><button type="button" class="ch-chip" data-st="<?= $k ?>"><?= $s[0] ?> <?= tr($s[1]) ?></button><?php endforeach; ?>
    </div>
    <div class="ch-grid">
        <?php foreach ($cards as [$p, $t]):
            $s = $STATUSES[$p['status']] ?? $STATUSES['idea'];
            $pct = $t['ttc'] > 0 ? min(100, round($t['bought_ttc'] / $t['ttc'] * 100)) : 0;
            $over = $p['target_budget'] !== null && $t['ttc'] > (float)$p['target_budget'];
        ?>
        <a class="ch-card" href="?tab=chantiers&p=<?= (int)$p['id'] ?>" data-st="<?= htmlspecialchars($p['status']) ?>">
            <div class="ch-card-top"><span class="ch-card-icon"><?= htmlspecialchars($p['icon'] ?: '🔨') ?></span>
                <span class="ch-badge" style="--c:<?= $s[2] ?>"><?= $s[0] ?> <?= tr($s[1]) ?></span></div>
            <div class="ch-card-name"><?= htmlspecialchars($p['name']) ?></div>
            <div class="ch-card-total"><?= $eur($t['ttc']) ?></div>
            <div class="ch-card-sub">
                <?= $t['lines'] ?> <?= tr('ch_lines') ?>
                <?php if ($t['labor_ttc'] > 0): ?> · <?= tr('ch_incl_labor') ?> <?= $eur($t['labor_ttc']) ?><?php endif; ?>
            </div>
            <?php if ($p['target_budget'] !== null): ?>
                <div class="ch-card-sub <?= $over ? 'ch-over' : '' ?>"><?= tr('ch_target') ?> : <?= $eur($p['target_budget']) ?><?= $over ? ' · ' . tr('ch_over') . ' ' . $eur($t['ttc'] - (float)$p['target_budget']) : '' ?></div>
            <?php endif; ?>
            <div class="ch-bar" title="<?= tr('ch_bought') ?> : <?= $eur($t['bought_ttc']) ?>"><span style="width:<?= $pct ?>%"></span></div>
            <div class="ch-card-sub"><?= tr('ch_bought') ?> <?= $eur($t['bought_ttc']) ?> (<?= $pct ?> %)</div>
        </a>
        <?php endforeach; ?>
        <button type="button" class="ch-card ch-card-new" onclick="chOpenProject()">＋ <?= tr('ch_new') ?></button>
    </div>
    <?php if (!$cards): ?><p class="pf-muted-note" style="text-align:center;margin-top:16px"><?= tr('ch_empty') ?></p><?php endif; ?>

<?php else: /* ======================= PAGE D'UN CHANTIER ======================= */
    $items = $pdo->prepare("SELECT * FROM pf_project_items WHERE project_id = ? ORDER BY sort_order, id");
    $items->execute([$pid]); $items = $items->fetchAll(PDO::FETCH_ASSOC);
    $tools = $pdo->prepare("SELECT * FROM pf_project_tools WHERE project_id = ? ORDER BY id"); $tools->execute([$pid]); $tools = $tools->fetchAll(PDO::FETCH_ASSOC);
    $links = $pdo->prepare("SELECT * FROM pf_project_links WHERE project_id = ? ORDER BY id"); $links->execute([$pid]); $links = $links->fetchAll(PDO::FETCH_ASSOC);
    $lots = array_values(array_unique(array_filter(array_map(fn($i) => $i['lot'], $pdo->query("SELECT DISTINCT lot FROM pf_project_items WHERE lot <> ''")->fetchAll(PDO::FETCH_ASSOC)))));
    $s = $STATUSES[$project['status']] ?? $STATUSES['idea'];
    $prov = null;
    if (!empty($project['provision_id'])) {
        $sp = $pdo->prepare("SELECT amount, expected_date, is_paid FROM pf_expected_expenses WHERE id = ?"); $sp->execute([$project['provision_id']]); $prov = $sp->fetch(PDO::FETCH_ASSOC) ?: null;
    }
?>
    <div class="ch-back"><a href="?tab=chantiers">← <?= tr('ch_back') ?></a></div>
    <div class="view-header ch-head">
        <h2><?= htmlspecialchars($project['icon'] ?: '🔨') ?> <?= htmlspecialchars($project['name']) ?>
            <span class="ch-badge" style="--c:<?= $s[2] ?>"><?= $s[0] ?> <?= tr($s[1]) ?></span></h2>
        <div class="ch-actions">
            <button type="button" class="pf-btn" onclick="chOpenProject(CH.project)">✏️ <?= tr('ch_edit') ?></button>
            <button type="button" class="pf-btn" onclick="chOpenProvision()">🧮 <?= tr('ch_provision') ?></button>
            <button type="button" class="pf-btn ch-danger" onclick="chDeleteProject()">🗑️</button>
        </div>
    </div>
    <?php if ($prov): ?><p class="pf-muted-note">🧮 <?= tr('ch_provision_linked') ?> : <?= $eur($prov['amount']) ?> · <?= date('d/m/Y', strtotime($prov['expected_date'])) ?><?= $prov['is_paid'] ? ' (' . tr('ch_provision_paid') . ')' : '' ?></p><?php endif; ?>

    <div class="ch-summary" id="ch-summary"></div>

    <section class="pf-card ch-sec">
        <h3 class="pf-card-h2">🧱 <?= tr('ch_materials') ?> <span class="ch-sec-total" id="ch-tot-material"></span></h3>
        <div class="ch-rows" id="ch-rows-material"></div>
        <button type="button" class="pf-btn ch-add" onclick="chAddItem('material')">＋ <?= tr('ch_add_line') ?></button>
    </section>

    <section class="pf-card ch-sec">
        <h3 class="pf-card-h2">👷 <?= tr('ch_labor') ?> <span class="ch-sec-total" id="ch-tot-labor"></span></h3>
        <p class="pf-muted-note" style="margin-top:0"><?= tr('ch_labor_hint') ?></p>
        <div class="ch-rows" id="ch-rows-labor"></div>
        <button type="button" class="pf-btn ch-add" onclick="chAddItem('labor')">＋ <?= tr('ch_add_labor') ?></button>
    </section>

    <div class="ch-two">
        <section class="pf-card ch-sec">
            <h3 class="pf-card-h2">📐 <?= tr('ch_dims') ?></h3>
            <div class="ch-dims">
                <label><?= tr('ch_width') ?> <input type="number" step="any" min="0" id="ch-dim-w" value="<?= htmlspecialchars((string)($project['dim_w'] ?? '')) ?>"></label>
                <label><?= tr('ch_length') ?> <input type="number" step="any" min="0" id="ch-dim-l" value="<?= htmlspecialchars((string)($project['dim_l'] ?? '')) ?>"></label>
                <label><?= tr('ch_height') ?> <input type="number" step="any" min="0" id="ch-dim-h" value="<?= htmlspecialchars((string)($project['dim_h'] ?? '')) ?>"></label>
            </div>
            <div class="ch-dim-res">
                <button type="button" class="ch-pick" data-k="surface" title="<?= tr('ch_pick_title') ?>"><?= tr('ch_surface') ?> : <strong id="ch-surface">0</strong> m²</button>
                <button type="button" class="ch-pick" data-k="volume" title="<?= tr('ch_pick_title') ?>"><?= tr('ch_volume') ?> : <strong id="ch-volume">0</strong> m³</button>
            </div>
        </section>

        <section class="pf-card ch-sec">
            <h3 class="pf-card-h2">🧰 <?= tr('ch_tools') ?></h3>
            <div id="ch-tools"></div>
            <button type="button" class="pf-btn ch-add" onclick="chAddTool()">＋ <?= tr('ch_add_tool') ?></button>
        </section>
    </div>

    <div class="ch-two">
        <section class="pf-card ch-sec">
            <h3 class="pf-card-h2">🔗 <?= tr('ch_links') ?></h3>
            <div id="ch-links"></div>
            <button type="button" class="pf-btn ch-add" onclick="chAddLink()">＋ <?= tr('ch_add_link') ?></button>
        </section>
        <section class="pf-card ch-sec">
            <h3 class="pf-card-h2">📝 <?= tr('ch_notes') ?></h3>
            <textarea id="ch-notes" class="pf-input" rows="6" placeholder="<?= tr('ch_notes_ph') ?>"><?= htmlspecialchars((string)$project['notes']) ?></textarea>
        </section>
    </div>

    <datalist id="ch-lots"><?php foreach ($lots as $l): ?><option value="<?= htmlspecialchars($l) ?>"><?php endforeach; ?></datalist>

    <div id="ch-prov-modal" class="ch-modal" hidden>
        <div class="ch-modal-box">
            <h3>🧮 <?= tr('ch_provision') ?></h3>
            <p class="pf-muted-note"><?= tr('ch_provision_desc') ?></p>
            <label><?= tr('ch_provision_amount') ?><input type="number" step="0.01" min="0" id="ch-prov-amount" class="pf-input"></label>
            <label><?= tr('ch_provision_date') ?><input type="date" id="ch-prov-date" class="pf-input"></label>
            <div class="ch-modal-btns"><button type="button" class="pf-btn" onclick="chCloseModals()"><?= tr('cancel') ?></button><button type="button" class="pf-btn pf-btn-primary" onclick="chSaveProvision()"><?= tr('save') ?></button></div>
        </div>
    </div>
<?php endif; ?>

    <div id="ch-proj-modal" class="ch-modal" hidden>
        <div class="ch-modal-box">
            <h3 id="ch-proj-title"><?= tr('ch_new') ?></h3>
            <label><?= tr('ch_name') ?><input type="text" id="ch-proj-name" class="pf-input" maxlength="150"></label>
            <div class="ch-modal-row ch-modal-row1">
                <label><?= tr('ch_status') ?><select id="ch-proj-status" class="pf-input">
                    <?php foreach ($STATUSES as $k => $s2): ?><option value="<?= $k ?>"><?= $s2[0] ?> <?= tr($s2[1]) ?></option><?php endforeach; ?></select></label>
            </div>
            <div class="ch-icon-field"><span><?= tr('ch_icon') ?></span>
                <input type="hidden" id="ch-proj-icon" value="🔨">
                <div class="ch-icons" id="ch-icons">
                    <?php foreach (['🔨','🏠','🌳','🌿','🪴','🚪','🪟','🛁','🚿','🚽','🍳','🛋️','🛏️','🧱','🪜','🪚','🎨','💡','🔌','🚰','🔥','❄️','🚗','🏗️','🧰','📦','🐕','🌸'] as $ic): ?>
                        <button type="button" class="ch-icon-opt" data-i="<?= $ic ?>"><?= $ic ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <label><?= tr('ch_target_budget') ?><input type="number" step="0.01" min="0" id="ch-proj-target" class="pf-input"></label>
            <div class="ch-modal-btns"><button type="button" class="pf-btn" onclick="chCloseModals()"><?= tr('cancel') ?></button><button type="button" class="pf-btn pf-btn-primary" onclick="chSaveProject()"><?= tr('save') ?></button></div>
        </div>
    </div>
</div>

<script>
const CH = {
    api: '/modules/budget/includes/api/projects.php',
    T: <?= json_encode($T, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
    L: <?= json_encode([
        'name' => tr('ch_f_name'), 'qty' => tr('ch_f_qty'), 'price' => tr('ch_f_price'), 'ht' => tr('ch_f_ht'), 'lot' => tr('ch_f_lot'), 'store' => tr('ch_f_store'),
        'url' => tr('ch_f_url'), 'vat' => tr('ch_f_vat'), 'unit' => tr('ch_f_unit'), 'note' => tr('ch_f_note'), 'bought' => tr('ch_f_bought'), 'paid' => tr('ch_f_paid'),
        'more' => tr('ch_f_more'), 'del' => tr('a11y_delete'), 'total' => tr('ch_total'), 'totalTtc' => tr('ch_total_ttc'), 'totalHt' => tr('ch_total_ht'),
        'labor' => tr('ch_incl_labor'), 'boughtTotal' => tr('ch_bought'), 'remaining' => tr('ch_remaining'), 'target' => tr('ch_target'), 'over' => tr('ch_over'),
        'byLot' => tr('ch_by_lot'), 'noLot' => tr('ch_no_lot'), 'tool' => tr('ch_tool_name'), 'lender' => tr('ch_lender'), 'title' => tr('ch_link_title'), 'address' => tr('ch_link_url'),
        'laborName' => tr('ch_labor_name'), 'open' => tr('ch_open'), 'linkErr' => tr('ch_link_err'),
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
<?php if ($project): ?>
    pid: <?= (int)$pid ?>,
    project: <?= json_encode($project, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
    items: <?= json_encode($items, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
    tools: <?= json_encode($tools, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
    links: <?= json_encode($links, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
    provisionAmount: <?= json_encode(round(max(0, projectTotals($pdo, $pid)['ttc'] - projectTotals($pdo, $pid)['bought_ttc']), 2)) ?>,
<?php endif; ?>
};
</script>
<script src="/modules/budget/chantiers.js?v=<?= @filemtime(__DIR__ . '/../chantiers.js') ?: 1 ?>"></script>
