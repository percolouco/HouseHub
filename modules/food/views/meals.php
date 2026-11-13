<?php
// modules/meals/main.php

// Récupération stricte des membres du foyer (Exclusion des intervenants et proches)
$stmt = $pdo->query("SELECT id, name FROM pf_people WHERE role NOT IN ('helper', 'nounou', 'proche_adulte', 'proche_enfant', 'relative') AND is_active = 1 ORDER BY id ASC");
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($members)) {
    echo "<div class='pf-alert pf-alert--warning'>" . tr('meals_no_members') . "</div>";
    return;
}

// Calcul des 14 jours (Lundi semaine courante -> Dimanche semaine prochaine)
$todayStr = date('Y-m-d');
$start_date = new DateTime();
$start_date->modify('monday this week');
$dates = [];
for ($i = 0; $i < 14; $i++) {
    $d = clone $start_date;
    $d->modify("+$i days");
    $dates[] = $d->format('Y-m-d');
}

$stmtMeals = $pdo->prepare("SELECT plan_date, service, person_id, meal_name FROM pf_meals_plan WHERE plan_date BETWEEN ? AND ?");
$stmtMeals->execute([$dates[0], end($dates)]);
$mealsData = [];
while ($row = $stmtMeals->fetch(PDO::FETCH_ASSOC)) {
    $mealsData[$row['plan_date']][$row['service']][$row['person_id']] = $row['meal_name'];
}
?>

<div class="pf-container meals-container">
    <div class="meals-header-bar">
        <h2 class="meals-title">
            <span class="meals-title-icon">🍽️</span> 
            <span data-i18n="meals_board_title"><?= tr('meals_board_title') ?></span>
        </h2>
        <div class="meals-actions">
            <!-- Liste de courses depuis le menu -->
            <button type="button" class="pf-btn btn-secondary" id="mealShopBtn">
                🛒 <?= tr('meal_shop_btn') ?>
            </button>
            <!-- NOUVEAU : Bouton Import PDF -->
            <button type="button" class="pf-btn btn-secondary" onclick="document.getElementById('importMenuModal').classList.add('open'); document.body.classList.add('no-scroll');">
                📄 <?= tr('meal_btn_import_pdf') ?>
            </button>
        </div>
    </div>

    <div class="meals-wrapper" id="mealsBoard">
        <!-- Bandeau du mode « copie » : on touche ensuite les repas de destination -->
        <div class="meal-paste-banner" id="mealPasteBanner" hidden>
            <span class="meal-paste-text" id="mealPasteText"></span>
            <button type="button" class="pf-btn meal-paste-done" onclick="endMealCopy()"><?= tr('meal_paste_done') ?></button>
        </div>

        <?php
        $svcDefs = ['lunch' => ['☀️', tr('meal_lunch')], 'dinner' => ['🌙', tr('meal_dinner')]];
        $weeks = array_chunk($dates, 7);
        foreach ($weeks as $weekIndex => $weekDates):
            $startWeekStr = date('d/m', strtotime($weekDates[0]));
            $endWeekStr = date('d/m', strtotime(end($weekDates)));
        ?>
            <h2 class="meals-week-title"><?= tr('meal_week') ?> (<?= $startWeekStr ?> - <?= $endWeekStr ?>)</h2>
            <div class="meals-week">
                <?php foreach ($weekDates as $dateStr):
                    $ts = strtotime($dateStr);
                    $dayKey = strtolower(date('D', $ts));
                    $dayName = tr('day_' . $dayKey);
                    $fullDayName = tr('day_full_' . $dayKey) . ' ' . date('d/m', $ts);
                    $dayCls = $dateStr === $todayStr ? ' is-today' : ($dateStr < $todayStr ? ' is-past' : '');
                ?>
                    <section class="meal-day<?= $dayCls ?>" data-d="<?= $dateStr ?>" aria-label="<?= htmlspecialchars($fullDayName) ?>">
                        <div class="meal-day-rail" title="<?= htmlspecialchars($fullDayName) ?>">
                            <span class="meal-day-name"><?= $dayName ?></span>
                            <span class="meal-day-num"><?= date('j', $ts) ?></span>
                            <span class="meal-day-month"><?= date('d/m', $ts) ?></span>
                        </div>
                        <div class="meal-day-slots">
                            <?php foreach ($svcDefs as $svc => [$svcIcon, $svcLabel]):
                                $vals = [];
                                foreach ($members as $m) { $vals[] = trim($mealsData[$dateStr][$svc][$m['id']] ?? ''); }
                                $isSplit = count(array_unique($vals)) > 1;
                            ?>
                                <div class="meal-slot<?= $isSplit ? ' is-split is-mixed' : '' ?>" data-d="<?= $dateStr ?>" data-s="<?= $svc ?>" data-label="<?= htmlspecialchars($fullDayName . ' · ' . $svcLabel) ?>">
                                    <span class="meal-slot-icon" title="<?= $svcLabel ?>" aria-hidden="true"><?= $svcIcon ?></span>
                                    <div class="meal-slot-body">
                                        <textarea class="meal-input js-meal-common" rows="1" aria-label="<?= htmlspecialchars($fullDayName . ' · ' . $svcLabel . ' · ' . tr('meal_all_family')) ?>" placeholder="<?= $svcLabel ?> · <?= mb_strtolower(tr('meal_all_family')) ?>"><?= $isSplit ? '' : htmlspecialchars($vals[0]) ?></textarea>
                                        <div class="meal-persons">
                                            <?php foreach ($members as $m):
                                                $val = $mealsData[$dateStr][$svc][$m['id']] ?? '';
                                            ?>
                                                <div class="meal-person<?= trim($val) === '' ? ' is-empty' : '' ?>">
                                                    <span class="meal-person-name"><?= htmlspecialchars($m['name']) ?></span>
                                                    <textarea class="meal-input js-meal-input" rows="1" data-d="<?= $dateStr ?>" data-s="<?= $svc ?>" data-p="<?= $m['id'] ?>" aria-label="<?= htmlspecialchars($fullDayName . ' · ' . $svcLabel . ' · ' . $m['name']) ?>" placeholder="-"><?= htmlspecialchars($val) ?></textarea>
                                                    <button type="button" class="meal-icon-btn js-apply-all" title="<?= tr('meal_apply_all') ?>" aria-label="<?= tr('meal_apply_all') ?>">
                                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12h9M10 8l4 4-4 4M17 6v12M21 6v12"/></svg>
                                                    </button>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="meal-slot-actions">
                                        <?php if (count($members) > 1): ?>
                                        <button type="button" class="meal-icon-btn js-toggle-split" data-t-split="<?= tr('meal_split') ?>" data-t-merge="<?= tr('meal_merge') ?>" title="<?= tr('meal_split') ?>" aria-label="<?= tr('meal_split') ?>">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3 19c0-3 2.7-5 6-5s6 2 6 5"/><circle cx="17" cy="9" r="2.3"/><path d="M16.5 14.2c2.7.2 4.5 1.9 4.5 4.3"/></svg>
                                        </button>
                                        <?php endif; ?>
                                        <button type="button" class="meal-icon-btn js-copy-slot" title="<?= tr('meal_copy_slot') ?>" aria-label="<?= tr('meal_copy_slot') ?>">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V6a2 2 0 0 1 2-2h9"/></svg>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modale « Liste de courses » : aperçu des aliments détectés (cantine exclue) -->
<div id="mealShopModal" class="pf-modal">
    <div class="pf-modal-content meal-shop">
        <div class="pf-modal-header meal-shop-head">
            <h3 class="pf-modal-title" style="margin:0;border:none;padding:0;">🛒 <?= tr('meal_shop_title') ?></h3>
            <button type="button" class="pf-modal-close meal-shop-close" id="mealShopClose" aria-label="Fermer">&times;</button>
        </div>
        <div class="meal-shop-range">
            <label><?= tr('meal_shop_from') ?> <input type="date" class="pf-input" id="mealShopFrom"></label>
            <label><?= tr('meal_shop_to') ?> <input type="date" class="pf-input" id="mealShopTo"></label>
        </div>
        <p class="meal-shop-hint"><?= tr('meal_shop_hint') ?></p>
        <div class="meal-shop-body" id="mealShopBody"></div>
        <div class="meal-shop-foot">
            <label class="meal-shop-listpick"><?= tr('meal_shop_list') ?>
                <select class="pf-input" id="mealShopList"></select>
            </label>
            <button type="button" class="pf-btn" id="mealShopAdd" disabled><?= tr('meal_shop_add') ?></button>
        </div>
    </div>
</div>

<!-- NOUVEAU : Modale d'import PDF Cantine -->
<div id="importMenuModal" class="pf-modal">
    <div class="pf-modal-content">
        <div class="pf-modal-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-light); padding-bottom: 15px; margin-bottom: 20px;">
            <h3 class="pf-modal-title" style="margin: 0; border: none; padding: 0;">📄 <?= tr('meal_import_title') ?></h3>
            <button type="button" class="pf-modal-close" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted);" onclick="document.getElementById('importMenuModal').classList.remove('open'); document.body.classList.remove('no-scroll');">&times;</button>
        </div>
        <form id="importMenuForm" onsubmit="importPdfMenu(event)" action="/modules/food/includes/api/import-pdf.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            
            <div class="pf-form-group">
                <label class="pf-label"><?= tr('meal_import_child') ?></label>
                <select name="person_id" class="pf-input" required>
                    <option value="" disabled selected>-- <?= tr('meal_select_child') ?> --</option>
                    <?php foreach ($members as $m): ?>
                        <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="pf-form-group">
                <label class="pf-label"><?= tr('meal_pdf_file') ?></label>
                <div class="meal-dropzone" id="mealDropzone" onclick="document.getElementById('canteenPdf').click()">
                    <span class="meal-dropzone-icon" id="mealDropzoneIcon">📥</span>
                    <span id="pdfFileName"><?= tr('meal_drop_pdf') ?></span>
                    <input type="file" id="canteenPdf" name="pdf_file" accept=".pdf" style="display: none;" required>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="pf-btn btn-secondary" onclick="document.getElementById('importMenuModal').classList.remove('open'); document.body.classList.remove('no-scroll');"><?= tr('btn_cancel') ?></button>
                <button type="submit" class="pf-btn" id="btnImportPdf"><?= tr('btn_import') ?></button>
            </div>
        </form>
    </div>
</div>

<script>
// Alerte de redondance : même personne, même jour, midi et soir
const MEAL_STOP = new Set(['avec','sans','aux','des','les','une','sur','dans','pour','de','du','la','le','et','au','en','sauce','roti','pommes','terre','petit','petits','grand']);
const MEAL_FAMILIES = {
    volaille: ['volaille','poulet','dinde','canard','pintade','chapon','poule','coq'],
    boeuf: ['boeuf','bœuf','steak','bavette','hache','haché','rumsteck'],
    porc: ['porc','jambon','saucisse','lardon','cote','côte','echine','échine'],
    poisson: ['poisson','cabillaud','colin','saumon','merlu','thon','lieu','truite','dorade','poissons'],
    puree: ['puree','purée'],
    pates: ['pates','pâtes','spaghetti','tagliatelles','coquillettes','macaroni','penne'],
    riz: ['riz'],
    pizza: ['pizza'],
};
function mealTokens(txt) {
    const norm = txt.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/œ/g, 'oe');
    const words = (norm.match(/[a-z]{3,}/g) || []).filter(w => !MEAL_STOP.has(w));
    const out = new Set(words.map(w => w.replace(/s$/, '')));
    for (const [fam, list] of Object.entries(MEAL_FAMILIES)) {
        const l = list.map(x => x.normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/œ/g, 'oe').replace(/s$/, ''));
        if (words.some(w => l.includes(w.replace(/s$/, '')))) out.add('#' + fam);
    }
    return out;
}
// ── Utilitaires de la vue « cartes » : un créneau (.meal-slot) = un jour + un service ──
const MEAL_API = '/modules/food/includes/api/save-meals.php';
const MEAL_T = (k, d) => (window.I18N && window.I18N[k]) || d;
const mealSlotOf = el => el.closest('.meal-slot');
const mealSlotInputs = slot => Array.from(slot.querySelectorAll('.js-meal-input'));
const MEAL_AUTOSIZE_JS = !(window.CSS && CSS.supports && CSS.supports('field-sizing', 'content'));

function mealAutosize(el) {
    if (!MEAL_AUTOSIZE_JS || !el.offsetParent) return;
    el.style.height = 'auto';
    el.style.height = el.scrollHeight + 'px';
}
function mealNotify(msg, type) {
    if (window.showToast) showToast(msg, type || 'success');
}
async function mealSaveBulk(updates) {
    if (!updates.length) return;
    const fd = new FormData();
    fd.append('action', 'save_bulk');
    fd.append('updates', JSON.stringify(updates));
    try {
        await pachaFetch(MEAL_API, { method: 'POST', body: fd });
    } catch (e) {
        console.error('Save error', e);
        mealNotify(MEAL_T('error_occured', 'Erreur'), 'error');
    }
}
// Synchronise l'affichage d'un créneau : champ commun si tout le monde mange pareil, détail sinon.
// mode 'auto' : replie/déplie selon les valeurs ; sinon on ne force que le dépliage si les repas diffèrent.
function refreshMealSlot(slot, mode) {
    const inputs = mealSlotInputs(slot);
    const first = inputs[0].value.trim();
    const uniform = inputs.every(i => i.value.trim() === first);
    if (mode === 'auto') slot.classList.toggle('is-split', !uniform);
    else if (!uniform) slot.classList.add('is-split');
    slot.classList.toggle('is-mixed', !uniform);
    // Détail replié et plus aucune différence (ex. exception effacée) : retour au champ commun
    if (uniform && !slot.classList.contains('is-open') && !slot.contains(document.activeElement)) slot.classList.remove('is-split');
    if (!slot.classList.contains('is-split')) slot.classList.remove('is-open');
    inputs.forEach(i => i.parentElement.classList.toggle('is-empty', i.value.trim() === ''));
    const common = slot.querySelector('.js-meal-common');
    if (uniform && document.activeElement !== common) common.value = inputs[0].value;
    const tg = slot.querySelector('.js-toggle-split');
    if (tg) {
        const t = (slot.classList.contains('is-open') && uniform) ? tg.dataset.tMerge : tg.dataset.tSplit;
        tg.title = t; tg.setAttribute('aria-label', t);
        tg.setAttribute('aria-pressed', slot.classList.contains('is-open') ? 'true' : 'false');
    }
    slot.querySelectorAll('.meal-input').forEach(mealAutosize);
}
function checkMealRedundancy() {
    const WARN = <?= json_encode(tr('meal_redundant_warn')) ?>;
    document.querySelectorAll('.meal-redundant').forEach(el => el.classList.remove('meal-redundant'));
    document.querySelectorAll('.js-meal-input, .js-meal-common, .meal-slot-icon').forEach(i => i.removeAttribute('title'));
    const byKey = {};
    document.querySelectorAll('.js-meal-input').forEach(i => {
        (byKey[i.dataset.d + '|' + i.dataset.p] = byKey[i.dataset.d + '|' + i.dataset.p] || {})[i.dataset.s] = i;
    });
    Object.values(byKey).forEach(g => {
        if (!g.lunch || !g.dinner || !g.lunch.value.trim() || !g.dinner.value.trim()) return;
        const a = mealTokens(g.lunch.value), b = mealTokens(g.dinner.value);
        const common = [...a].filter(t => b.has(t)).map(t => t.replace(/^#/, ''));
        if (common.length) {
            const msg = WARN + ' : ' + common.join(', ');
            [g.lunch, g.dinner].forEach(i => {
                const slot = mealSlotOf(i);
                i.parentElement.classList.add('meal-redundant');
                slot.classList.add('meal-redundant');
                i.title = msg;
                slot.querySelector('.js-meal-common').title = msg;
                slot.querySelector('.meal-slot-icon').title = msg;
            });
        }
    });
}
document.addEventListener('DOMContentLoaded', () => {
    checkMealRedundancy();
    document.querySelectorAll('.meal-slot').forEach(s => refreshMealSlot(s));

    // 1. Sauvegarde automatique AJAX à chaque modification (champ d'une personne)
    document.querySelectorAll('.js-meal-input').forEach(input => {
        input.addEventListener('input', function() {
            refreshMealSlot(mealSlotOf(this));
            checkMealRedundancy();
        });
        input.addEventListener('change', async function() {
            const fd = new FormData();
            fd.append('action', 'save_cell');
            fd.append('plan_date', this.dataset.d);
            fd.append('service', this.dataset.s);
            fd.append('person_id', this.dataset.p);
            fd.append('meal_name', this.value);

            try {
                await pachaFetch(MEAL_API, { method: 'POST', body: fd });
                flashSuccess(this);
                refreshMealSlot(mealSlotOf(this));
                checkMealRedundancy();
            } catch(e) {
                console.error("Save error", e);
            }
        });
    });

    // 1 bis. Champ commun « toute la famille » : une saisie = le même repas pour chaque membre
    document.querySelectorAll('.js-meal-common').forEach(common => {
        common.addEventListener('input', function() {
            mealSlotInputs(mealSlotOf(this)).forEach(i => { i.value = this.value; });
            mealAutosize(this);
            checkMealRedundancy();
        });
        common.addEventListener('change', async function() {
            const val = this.value;
            const updates = mealSlotInputs(mealSlotOf(this)).map(i => {
                i.value = val;
                return { plan_date: i.dataset.d, service: i.dataset.s, person_id: i.dataset.p, meal_name: val };
            });
            await mealSaveBulk(updates);
            flashSuccess(this);
        });
    });

    // Sur téléphone : on arrive directement sur le jour en cours
    const today = document.querySelector('.meal-day.is-today');
    if (today && window.matchMedia('(max-width: 768px)').matches && today.getBoundingClientRect().bottom > window.innerHeight) {
        today.scrollIntoView({ block: 'start' });
    }

    // 2. NOUVEAU : Initialisation du Drag & Drop pour l'import PDF
    const dropzone = document.getElementById('mealDropzone');
    const fileInput = document.getElementById('canteenPdf');
    
    if (dropzone && fileInput) {
        // Empêcher l'ouverture automatique du fichier par le navigateur
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, e => {
                e.preventDefault();
                e.stopPropagation();
            }, false);
        });

        // Feedback visuel au survol
        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, () => dropzone.classList.add('drag-over'), false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, () => dropzone.classList.remove('drag-over'), false);
        });

        // Récupération du fichier au lâcher (Drop)
        dropzone.addEventListener('drop', e => {
            if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                fileInput.files = e.dataTransfer.files;
                updateDropzoneState(fileInput.files[0]);
            }
        }, false);

        // Récupération au clic classique via l'input
        fileInput.addEventListener('change', function() {
            updateDropzoneState(this.files[0]);
        });
    }
});

// NOUVEAU : Fonction utilitaire pour gérer l'état visuel de la Dropzone
window.updateDropzoneState = function(file) {
    const dropzone = document.getElementById('mealDropzone');
    const fileNameDisplay = document.getElementById('pdfFileName');
    const dropzoneIcon = document.getElementById('mealDropzoneIcon');

    if (file) {
        fileNameDisplay.innerText = file.name;
        if (dropzone) dropzone.classList.add('has-file');
        if (dropzoneIcon) dropzoneIcon.innerText = '✅';
    } else {
        fileNameDisplay.innerText = window.I18N['meal_drop_pdf'] || 'Cliquer pour ajouter le PDF';
        if (dropzone) dropzone.classList.remove('has-file');
        if (dropzoneIcon) dropzoneIcon.innerText = '📥';
    }
};

// ============================================================================
// Actions des créneaux : détailler / regrouper, appliquer à tous, copier vers…
// (boutons intégrés à chaque repas : plus de barre flottante ni de modale)
// ============================================================================
let mealCopyState = null; // { slot, values: [{ p, v }] }

document.addEventListener('click', (e) => {
    const board = document.getElementById('mealsBoard');
    if (!board || !board.contains(e.target)) return;
    const slot = e.target.closest('.meal-slot');

    // Mode copie : chaque repas touché reçoit la copie
    if (mealCopyState) {
        if (e.target.closest('.meal-paste-banner')) return;
        e.preventDefault();
        if (slot && slot !== mealCopyState.slot) pasteMealInto(slot);
        return;
    }
    if (!slot) return;

    if (e.target.closest('.js-toggle-split')) {
        // Repas commun -> détail ; détail identique -> regroupé ; repas différents -> affiche/masque les personnes sans plat
        const wasCommon = !slot.classList.contains('is-split');
        if (slot.classList.contains('is-mixed')) {
            slot.classList.toggle('is-open');
        } else if (wasCommon) {
            slot.classList.add('is-split', 'is-open');
        } else {
            slot.classList.remove('is-split', 'is-open');
        }
        refreshMealSlot(slot);
        if (wasCommon) slot.querySelector('.js-meal-input').focus();
    } else if (e.target.closest('.js-apply-all')) {
        applyMealToAll(e.target.closest('.meal-person').querySelector('.js-meal-input'));
    } else if (e.target.closest('.js-copy-slot')) {
        startMealCopy(slot);
    }
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && mealCopyState) endMealCopy();
});

// « Appliquer à tous » : le plat de cette personne devient le repas de toute la famille
window.applyMealToAll = async function(source) {
    const val = source.value;
    if (!val.trim()) return;
    const slot = mealSlotOf(source);
    const updates = mealSlotInputs(slot).map(inp => {
        inp.value = val;
        return { plan_date: inp.dataset.d, service: inp.dataset.s, person_id: inp.dataset.p, meal_name: val };
    });
    refreshMealSlot(slot, 'auto');
    flashSuccess(slot.querySelector('.js-meal-common'));
    await mealSaveBulk(updates);
};

// « Copier vers… » : on mémorise le repas, puis on touche les repas de destination dans la page
window.startMealCopy = function(slot) {
    const values = mealSlotInputs(slot).map(i => ({ p: i.dataset.p, v: i.value })).filter(x => x.v.trim() !== '');
    if (!values.length) { mealNotify(MEAL_T('meal_copy_empty', 'Rien à copier'), 'error'); return; }
    if (document.activeElement && document.activeElement.blur) document.activeElement.blur();

    mealCopyState = { slot, values };
    const names = [...new Set(values.map(x => x.v.trim()))];
    const summary = names[0] + (names.length > 1 ? ' (+' + (names.length - 1) + ')' : '');
    document.getElementById('mealPasteText').textContent =
        `${MEAL_T('meal_copy_of', 'Copie de')} « ${summary} » (${slot.dataset.label}) : ${MEAL_T('meal_paste_hint', 'touchez les repas où le coller')}`;
    document.getElementById('mealPasteBanner').hidden = false;
    document.getElementById('mealsBoard').classList.add('is-pasting');
    slot.classList.add('is-copy-source');
};

window.pasteMealInto = async function(target) {
    const updates = [];
    mealCopyState.values.forEach(({ p, v }) => {
        const inp = target.querySelector(`.js-meal-input[data-p="${p}"]`);
        if (!inp) return;
        inp.value = v;
        updates.push({ plan_date: inp.dataset.d, service: inp.dataset.s, person_id: p, meal_name: v });
    });
    refreshMealSlot(target, 'auto');
    target.classList.add('is-pasted');
    checkMealRedundancy();
    await mealSaveBulk(updates);
};

window.endMealCopy = function() {
    mealCopyState = null;
    document.getElementById('mealPasteBanner').hidden = true;
    document.getElementById('mealsBoard').classList.remove('is-pasting');
    document.querySelectorAll('.is-copy-source, .is-pasted').forEach(el => el.classList.remove('is-copy-source', 'is-pasted'));
};

function flashSuccess(input) {
    checkMealRedundancy();
    input.style.transition = 'background 0.3s';
    input.style.background = 'var(--alert-success-bg)';
    setTimeout(() => input.style.background = '', 400);
}

// Fonction Javascript d'Import PDF mise à jour
window.importPdfMenu = async function(e) {
    e.preventDefault();
    const form = e.target;
    const btn = document.getElementById('btnImportPdf');
    const oldText = btn.innerText;
    btn.disabled = true;
    btn.innerText = '⏳...';

    const fd = new FormData(form);
    const actionUrl = form.getAttribute('action');

    try {
        const res = await pachaFetch(actionUrl, { method: 'POST', body: fd });
        if (res && res.success) {
            document.getElementById('importMenuModal').classList.remove('open');
            document.body.classList.remove('no-scroll');
            if (window.showToast) showToast(window.I18N['meal_import_success'] || 'Menus importés !', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            if (window.showToast) showToast(res.error || window.I18N['error_occured'], 'error');
            else alert(res.error);
        }
    } catch(err) {
        if (window.showToast) showToast(window.I18N['error_occured'], 'error');
    } finally {
        btn.disabled = false;
        btn.innerText = oldText;
        form.reset();
        updateDropzoneState(null); 
    }
};

// NOUVEAU : Interception du collage depuis Excel / Google Sheets
document.addEventListener('paste', async function(e) {
    // On ne s'active que si on colle dans un champ de repas (commun ou d'une personne)
    const isCommon = e.target.classList.contains('js-meal-common');
    if (!isCommon && !e.target.classList.contains('js-meal-input')) return;

    const clipboardData = e.clipboardData || window.clipboardData;
    const pastedText = clipboardData.getData('text/plain');

    // Si le texte ne contient ni tabulation ni saut de ligne, c'est un collage simple.
    // On laisse le navigateur faire son comportement par défaut.
    if (!pastedText.includes('\t') && !pastedText.includes('\n')) return;

    // C'est une grille ! On bloque le comportement par défaut
    e.preventDefault();

    // Lignes de la grille = créneaux successifs (Midi, Soir, Midi du lendemain…),
    // colonnes = membres de la famille, à partir du champ où l'on colle.
    const rows = pastedText.split(/\r?\n/);
    const slots = Array.from(document.querySelectorAll('.meal-slot'));
    const startSlot = mealSlotOf(e.target);
    const startColIndex = isCommon ? 0 : mealSlotInputs(startSlot).indexOf(e.target);
    let slotIdx = slots.indexOf(startSlot);
    let updates = [];

    for (let i = 0; i < rows.length; i++, slotIdx++) {
        const slot = slots[slotIdx];
        if (!slot) break; // Fin du planning atteinte

        // Ignorer la dernière ligne vide souvent générée par les tableurs lors d'une copie
        if (rows[i].trim() === '' && i === rows.length - 1) continue;

        const cells = rows[i].split('\t');
        const inputs = mealSlotInputs(slot);
        // Une seule colonne collée dans un champ commun : le plat vaut pour toute la famille
        const targets = (isCommon && cells.length === 1)
            ? inputs.map(inp => [inp, cells[0]])
            : cells.map((c, j) => [inputs[startColIndex + j], c]);

        targets.forEach(([inp, raw]) => {
            if (!inp) return; // Fin des colonnes atteinte
            const val = raw.trim();
            inp.value = val;
            updates.push({ plan_date: inp.dataset.d, service: inp.dataset.s, person_id: inp.dataset.p, meal_name: val });
        });
        const common = slot.querySelector('.js-meal-common');
        if (document.activeElement === common) common.blur();
        refreshMealSlot(slot, 'auto');
        flashSuccess(slot.classList.contains('is-split') ? inputs[0] : common);
    }

    // Sauvegarde groupée optimisée
    if (updates.length > 0) {
        const fd = new FormData();
        fd.append('action', 'save_bulk');
        fd.append('updates', JSON.stringify(updates));
        
        try {
            await pachaFetch('/modules/food/includes/api/save-meals.php', { method: 'POST', body: fd });
            if (window.showToast) showToast(window.I18N['meal_paste_success'] || 'Grille collée avec succès !', 'success');
        } catch(err) {
            console.error("Erreur de sauvegarde lors du collage", err);
            if (window.showToast) showToast(window.I18N['error_occured'], 'error');
        }
    }
});

// ── Liste de courses depuis le menu ──────────────────────────────────────────
(function () {
    const SHOP_API = '/modules/food/includes/api/meals-shopping.php';
    const LISTE_API = '/modules/food/includes/api/api-liste.php';
    const T = (k, d) => (window.I18N && window.I18N[k]) || d;
    const $ = id => document.getElementById(id);
    const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const modal = $('mealShopModal'), body = $('mealShopBody'), addBtn = $('mealShopAdd');
    if (!modal) return;
    const iso = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    let token = 0;

    function setCount() {
        const n = body.querySelectorAll('.js-shop-check:checked').length;
        addBtn.disabled = n === 0;
        addBtn.textContent = T('meal_shop_add', 'Ajouter à la liste') + (n ? ' (' + n + ')' : '');
    }
    function row(label, sub, checked, unknown, detail) {
        return '<label class="meal-shop-row' + (unknown ? ' is-unknown' : '') + '">'
            + '<input type="checkbox" class="js-shop-check"' + (checked ? ' checked' : '') + '>'
            + '<span class="meal-shop-main"><input type="text" class="js-shop-label" value="' + esc(label) + '" maxlength="120">'
            + (sub ? '<small' + (detail ? ' class="js-shop-sub" title="' + esc(detail) + '" data-detail="' + esc(detail) + '"' : '') + '>' + esc(sub) + '</small>' : '') + '</span></label>';
    }
    async function load() {
        const my = ++token;
        body.innerHTML = '<p class="meal-shop-empty">' + esc(T('meal_shop_loading', 'Analyse…')) + '</p>';
        addBtn.disabled = true;
        try {
            const data = await pachaFetch(SHOP_API + '?from=' + $('mealShopFrom').value + '&to=' + $('mealShopTo').value);
            if (my !== token) return;
            if (!data.success) { body.innerHTML = '<p class="meal-shop-empty">' + esc(data.error || '') + '</p>'; return; }
            const sel = $('mealShopList');
            if (!sel.options.length) {
                (data.lists.length ? data.lists : [{ id: 0, name: 'Ma liste' }]).forEach(l => sel.add(new Option(l.name, l.id)));
            }
            let h = '';
            if (!data.items.length) h += '<p class="meal-shop-empty">' + esc(T('meal_shop_none', 'Aucun aliment détecté.')) + '</p>';
            data.items.forEach(it => {
                h += row(it.label, (it.count > 1 ? it.count + ' ' + T('meal_shop_meals', 'repas') + ' · ' : '') + it.meals.slice(0, 3).join(', ') + (it.meals.length > 3 ? '…' : ''), true, false, it.detail.join('\n'));
            });
            if (data.unknown.length) {
                h += '<h4 class="meal-shop-sub">' + esc(T('meal_shop_unknown', 'Non reconnus')) + '</h4>';
                data.unknown.forEach(u => { h += row(u.meal, u.when, false, true); });
            }
            if (data.skipped.length) {
                h += '<h4 class="meal-shop-sub">' + esc(T('meal_shop_skipped', 'Ignorés')) + '</h4><ul class="meal-shop-skipped">';
                data.skipped.forEach(s => { h += '<li><b>' + esc(T(s.why === 'canteen' ? 'meal_shop_canteen' : 'meal_shop_out', s.why)) + '</b> ' + esc(s.when) + ' — ' + esc(s.meal) + '</li>'; });
                h += '</ul>';
            }
            body.innerHTML = h;
            body.querySelectorAll('.js-shop-sub').forEach(sm => { sm.dataset.short = sm.textContent; });
            setCount();
        } catch (e) {
            if (my === token) body.innerHTML = '<p class="meal-shop-empty">' + esc(T('error_occured', 'Erreur')) + '</p>';
        }
    }
    function open() {
        const t = new Date(), end = new Date(Array.from(document.querySelectorAll('.meal-day')).pop()?.dataset.d || t);
        $('mealShopFrom').value = iso(t);
        $('mealShopTo').value = iso(end < t ? new Date(t.getTime() + 13 * 864e5) : end);
        modal.classList.add('open'); document.body.classList.add('no-scroll');
        load();
    }
    function close() { modal.classList.remove('open'); document.body.classList.remove('no-scroll'); }
    $('mealShopBtn').addEventListener('click', open);
    $('mealShopClose').addEventListener('click', close);
    modal.addEventListener('click', e => { if (e.target === modal) close(); });
    $('mealShopFrom').addEventListener('change', load);
    $('mealShopTo').addEventListener('change', load);
    // survol (ordinateur) = infobulle ; toucher (mobile) = déplie la liste des repas concernés
    body.addEventListener('click', e => {
        const sm = e.target.closest('.js-shop-sub'); if (!sm) return;
        e.preventDefault();
        const open = sm.classList.toggle('is-open');
        sm.textContent = open ? sm.dataset.detail : sm.dataset.short;
    });
    body.addEventListener('change', e => { if (e.target.classList.contains('js-shop-check')) setCount(); });

    addBtn.addEventListener('click', async () => {
        const rows = Array.from(body.querySelectorAll('.meal-shop-row')).filter(r => r.querySelector('.js-shop-check').checked);
        const labels = rows.map(r => r.querySelector('.js-shop-label').value.trim()).filter(Boolean);
        if (!labels.length) return;
        addBtn.disabled = true;
        let listId = parseInt($('mealShopList').value, 10) || 0, added = 0, dups = 0;
        try {
            if (!listId) { const r = await pachaFetch(LISTE_API + '?action=lists'); listId = r.default_id; }
            for (const label of labels) { // un par un : l'API calcule la position et la catégorie
                const r = await pachaFetch(LISTE_API + '?action=items&list_id=' + listId, {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ list_id: listId, label })
                });
                if (r.duplicate) dups++; else if (r.id) added++;
            }
            if (window.showToast) showToast('🛒 ' + added + ' ' + T('meal_shop_added', 'ajouté(s)') + (dups ? ' · ' + dups + ' ' + T('meal_shop_dups', 'déjà présent(s)') : ''), 'success');
            close();
        } catch (e) {
            if (window.showToast) showToast(T('error_occured', 'Erreur'), 'error');
            addBtn.disabled = false;
        }
    });
})();
</script>