<?php
// modules/gift-list/gift-list.php

require __DIR__ . '/includes/auth.php';
require_login('/login.php');
require __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/i18n.php';
require __DIR__ . '/modules/gift-list/ensure-schema.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
gift_ensure_schema($pdo);
// Écran d'application installée (iOS) : ne jamais garder une ancienne version de la page
header('Cache-Control: no-cache, must-revalidate');

// --- 1. CONFIGURATION DE BASE ---
$year       = (int)date('Y');
$pageTitle  = tr('gift_page_title');
$activePage = "gift-list";
$mainClass  = "pf-gift-list";
$pageCss    = "/modules/gift-list/gift-list.css";

// --- 2. RÉCUPÉRATION DYNAMIQUE DES DONNÉES ---
// Enfants du foyer + proches enfants
$stmt = $pdo->query("SELECT name FROM pf_people WHERE role IN ('enfant', 'proche_enfant') ORDER BY name ASC");
$children = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: ['Aucun enfant configuré'];

// Adultes du foyer + proches adultes (sans nounou ni enfants)
$stmt = $pdo->query("SELECT name FROM pf_people WHERE role NOT IN ('enfant', 'proche_enfant', 'nounou') ORDER BY name ASC");
$allAdultsList = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: ['Aucun adulte configuré'];

// Occasions actives (avec icône)
$stmt = $pdo->query("SELECT code, name, icon, month_date FROM pf_gift_occasions WHERE is_active = 1 ORDER BY sort_order ASC, month_date ASC, id ASC");
$activeOccasions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($activeOccasions)) {
    $activeOccasions = [['code' => 'DEMO', 'name' => 'Fête par défaut', 'icon' => '🎁', 'month_date' => null]];
}
$allowedOccasionCodes = array_column($activeOccasions, 'code');

// Fête affichée par défaut : la prochaine à venir (sinon la première)
$defaultOcc = $allowedOccasionCodes[0];
$today = date('m-d');
$bestDelta = null;
foreach ($activeOccasions as $o) {
    if (!preg_match('/^\d{2}-\d{2}$/', (string)$o['month_date'])) continue;
    $delta = strcmp($o['month_date'], $today) >= 0 ? 0 : 1;
    $key = $delta . $o['month_date'];
    if ($bestDelta === null || $key < $bestDelta) { $bestDelta = $key; $defaultOcc = $o['code']; }
}

// --- 3. CHARGEMENT DES CADEAUX ---
$inMarks = implode(',', array_fill(0, count($allowedOccasionCodes), '?'));
$sql = "SELECT id, occasion, child_name, adult_name, payer_name, gift_description, product_link, amount, is_bought
        FROM pf_gifts WHERE year = ? AND occasion IN ($inMarks) ORDER BY child_name ASC, id ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge([$year], $allowedOccasionCodes));
$giftsJs = array_map(function ($g) {
    return [
        'id'     => (int)$g['id'],
        'occ'    => $g['occasion'],
        'child'  => $g['child_name'],
        'adult'  => $g['adult_name'],
        'payer'  => ($g['payer_name'] ?: $g['adult_name']),
        'desc'   => $g['gift_description'],
        'link'   => $g['product_link'] ?: '',
        'amount' => (float)$g['amount'],
        'bought' => (int)($g['is_bought'] ?? 0),
    ];
}, $stmt->fetchAll(PDO::FETCH_ASSOC));

// Idées en vrac (onglet Notes)
$ideasJs = [];
try {
    foreach ($pdo->query("SELECT id, child_name, idea FROM pf_gift_ideas ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $ideasJs[] = ['id' => (int)$r['id'], 'child' => $r['child_name'], 'text' => $r['idea']];
    }
} catch (Throwable $e) {}

$me = $_SESSION['user']['display_name'] ?? ($_SESSION['user']['username'] ?? '');

$glData = [
    'year'       => $year,
    'gifts'      => $giftsJs,
    'ideas'      => $ideasJs,
    'children'   => array_values($children),
    'adults'     => array_values($allAdultsList),
    'occasions'  => array_map(fn($o) => ['code' => $o['code'], 'name' => $o['name'], 'icon' => $o['icon'] ?: '🎁'], $activeOccasions),
    'defaultOcc' => $defaultOcc,
    'me'         => $me,
    'cur'        => defined('CURRENCY') ? CURRENCY : '€',
    't'          => [
        'ideas' => tr('gl_tab_ideas'), 'accounts' => tr('gl_tab_accounts'),
        'for' => tr('gl_for'), 'by' => tr('gl_by'), 'status' => tr('gl_status'),
        'toBuy' => tr('gl_to_buy'), 'bought' => tr('gl_bought'),
        'spent' => tr('gl_spent'), 'planned' => tr('gl_planned'), 'remaining' => tr('gl_remaining'),
        'addTitle' => tr('gl_add_title'), 'editTitle' => tr('gl_edit_title'),
        'paidBy' => tr('gift_paid_by'), 'paid' => tr('gl_paid'), 'share' => tr('gl_share'),
        'owes' => tr('gift_owes'), 'to' => tr('gl_to'),
        'whoOwes' => tr('gl_who_owes'), 'settled' => tr('gl_settled'), 'basedOn' => tr('gl_based_on'),
        'emptyOcc' => tr('gl_empty_occ'), 'noMatch' => tr('gl_no_match'), 'reset' => tr('gl_reset'),
        'confirmDelete' => tr('gift_confirm_delete'), 'matrix' => tr('gift_view_matrix'),
        'debtor' => tr('gift_debtor'), 'creditor' => tr('gift_creditor'), 'per' => tr('gl_per_person'),
        'noteAdd' => tr('gl_note_add'), 'notePh' => tr('gl_note_ph'), 'noteEmpty' => tr('gl_note_empty'), 'noteHint' => tr('gl_note_hint'), 'noteDel' => tr('gl_note_del'),
        'sameAsAdult' => tr('gl_same_payer'), 'plannedLeft' => tr('gl_planned_left'), 'markBought' => tr('gl_mark_bought'),
    ],
];

require __DIR__ . '/header.php';
?>

<div class="pf-container gl-app" id="glApp">

    <div class="gl-top">
        <div class="gl-titlerow">
            <h1>🎁 <?= sprintf(tr('gift_main_title'), $year) ?></h1>
            <button id="btn-open-gift-settings" class="btn-settings-gear gl-gear" title="<?= tr('gift_settings') ?>" aria-label="<?= tr('gift_settings') ?>">⚙️</button>
        </div>

        <div class="gl-seg" role="tablist">
            <button type="button" class="gl-seg-btn is-active" data-view="ideas" role="tab">🎁 <?= tr('gl_tab_ideas') ?></button>
            <button type="button" class="gl-seg-btn" data-view="notes" role="tab">💡 <?= tr('gl_tab_notes') ?></button>
            <button type="button" class="gl-seg-btn" data-view="accounts" role="tab">⚖️ <?= tr('gl_tab_accounts') ?></button>
        </div>
    </div>

    <div id="glIdeas">
        <nav class="gl-tabs" id="glTabs" aria-label="Fêtes"></nav>
        <div class="gl-summary" id="glSummary"></div>
        <div class="gl-filters" id="glFilters"></div>
        <div id="glList"></div>
    </div>

    <div id="glNotes" hidden></div>

    <div id="glAccounts" hidden></div>
</div>

<button type="button" class="gl-fab" id="glFab" aria-label="<?= tr('gl_add_title') ?>">+</button>

<!-- Fiche d'ajout / d'édition (bottom sheet) -->
<div class="gl-sheet-back" id="glSheet" hidden>
    <form class="gl-sheet" id="giftForm" method="post" action="/modules/gift-list/save-gift.php" autocomplete="off">
        <div class="gl-grab"></div>
        <div class="gl-sheet-head">
            <h3 id="glSheetTitle"></h3>
            <button type="button" class="gl-x" data-close aria-label="<?= tr('btn_cancel') ?>">&times;</button>
        </div>

        <input type="hidden" name="year" value="<?= $year ?>">
        <input type="hidden" name="action" id="modalAction" value="create">
        <input type="hidden" name="gift_id" id="modalGiftId" value="">

        <input type="text" name="gift_description" id="modalDesc" class="gl-big" placeholder="<?= tr('gl_what') ?>" required enterkeyhint="done">

        <div class="gl-row">
            <label class="gl-price-in"><input type="text" name="amount" id="modalAmount" inputmode="decimal" placeholder="0,00"><span><?= defined('CURRENCY') ? htmlspecialchars(CURRENCY) : '€' ?></span></label>
            <label class="gl-switch"><input type="checkbox" name="is_bought" id="modalBought" value="1"><span class="gl-switch-ui"></span><span><?= tr('gl_bought_done') ?></span></label>
        </div>

        <div class="gl-field"><span class="gl-field-label"><?= tr('gl_for') ?></span><div class="gl-chips" id="modalChildChips"></div></div>
        <div class="gl-field"><span class="gl-field-label"><?= tr('gl_by') ?></span><div class="gl-chips" id="modalAdultChips"></div></div>

        <details class="gl-more">
            <summary><?= tr('gl_more') ?></summary>
            <div class="gl-more-body">
                <label class="gl-lab"><?= tr('gift_modal_payer') ?><select name="payer_name" id="modalPayer" class="pf-input"></select></label>
                <label class="gl-lab"><?= tr('gl_occasion') ?><select name="occasion" id="modalOccasion" class="pf-input"></select></label>
                <label class="gl-lab"><?= tr('gift_modal_link') ?><input type="url" name="product_link" id="modalLink" class="pf-input" placeholder="https://"></label>
            </div>
        </details>

        <div class="gl-sheet-actions">
            <button type="button" class="gl-btn-del" id="glDelete" hidden>🗑️ <?= tr('gl_delete') ?></button>
            <button type="submit" class="gl-btn-save"><?= tr('btn_save') ?></button>
        </div>
    </form>
</div>

<!-- 2. Modale Paramètres (Occasions & Proches) -->
<div id="modal-gift-settings" class="pf-modal">
    <div class="pf-modal-content" style="max-width: 600px; width: 90%;">
        
        <div class="pf-modal-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3 class="pf-modal-title" style="margin: 0;">⚙️ <?= tr('gift_settings') ?></h3>
            <button type="button" class="pf-modal-close" style="background:none; border:none; font-size:1.5rem; cursor:pointer;" onclick="closeGiftSettings()">&times;</button>
        </div>

        <div style="display: flex; gap: 4px; background: var(--bg-page); padding: 4px; border-radius: 8px; margin: 1rem 0;">
            <button type="button" id="tab-btn-occasions" class="bs-tab-btn active" style="flex: 1; padding: 8px 12px; font-weight: 600; border-radius: 6px; border: none; cursor: pointer; transition: 0.2s;" onclick="switchGLTab('occasions')"><?= tr('gift_tab_occasions') ?></button>
            <button type="button" id="tab-btn-proches" class="bs-tab-btn" style="flex: 1; padding: 8px 12px; font-weight: 600; border-radius: 6px; border: none; cursor: pointer; transition: 0.2s; background: transparent;" onclick="switchGLTab('proches')"><?= tr('gift_tab_proches') ?></button>
        </div>

        <div class="pf-modal-body" style="min-height: 450px; max-height: 60vh; overflow-y: auto; padding: 0 1rem 1rem 1rem; display: flex; flex-direction: column;">
            
            <div id="gl-pane-occasions" class="gl-pane active" style="display: block; animation: fadeIn 0.3s ease;">
                <div id="occasions-list" style="margin-bottom: 20px;">⏳ <?= tr('loading') ?></div>
                
                <hr style="border:0; border-top: 1px solid var(--border-light); margin: 20px 0;">
                
                <h5 style="margin: 0 0 15px 0; font-size: 1rem; color: var(--text-main);"><?= tr('gift_settings_add_occ') ?></h5>
                <form id="form-add-occasion" style="display: flex; flex-direction: column; gap: 16px;">
                    <div>
                        <label class="pf-label" style="display: block; margin-bottom: 6px;"><?= tr('gift_settings_icon') ?></label>
                        <input type="hidden" name="icon" id="add-occ-icon" value="🎁">
                        <div class="emoji-picker" style="display: flex; gap: 8px; flex-wrap: wrap;">
                            <div class="emoji-option selected" onclick="selectEmoji(this, 'add-occ-icon', '🎁')">🎁</div>
                            <div class="emoji-option" onclick="selectEmoji(this, 'add-occ-icon', '🎄')">🎄</div>
                            <div class="emoji-option" onclick="selectEmoji(this, 'add-occ-icon', '🎂')">🎂</div>
                            <div class="emoji-option" onclick="selectEmoji(this, 'add-occ-icon', '🎉')">🎉</div>
                            <div class="emoji-option" onclick="selectEmoji(this, 'add-occ-icon', '🎓')">🎓</div>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 150px;">
                            <label class="pf-label" style="display: block; margin-bottom: 6px;"><?= tr('gift_modal_gift_name') ?> *</label>
                            <input type="text" name="name" id="add-occ-name" class="pf-input" style="width: 100%;" required>
                        </div>
                        <div style="width: 110px;">
                            <label class="pf-label" style="display: block; margin-bottom: 6px;"><?= tr('gift_settings_date') ?></label>
                            <input type="text" name="month_date" id="add-occ-date" class="pf-input" style="width: 100%;" placeholder="MM-DD">
                        </div>
                    </div>
                    
                    <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 5px;">
                        <button type="button" class="pf-btn btn-secondary" onclick="document.getElementById('form-add-occasion').reset()"><?= tr('btn_cancel') ?></button>
                        <button type="submit" class="pf-btn pf-btn-primary"><?= tr('btn_add') ?></button>
                    </div>
                </form>
            </div>

            <div id="gl-pane-proches" class="gl-pane" style="display: none; animation: fadeIn 0.3s ease;">
                <div id="proches-list" style="margin-bottom: 20px;">⏳ <?= tr('loading') ?></div>

                <hr style="border:0; border-top: 1px solid var(--border-light); margin: auto 0 20px 0;">
                
                <h5 style="margin: 0 0 15px 0; font-size: 1rem; color: var(--text-main);"><?= tr('gift_settings_add_proche') ?></h5>
                <form id="form-add-proche" style="display: flex; flex-direction: column; gap: 16px;">
                    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 150px;">
                            <label class="pf-label" style="display: block; margin-bottom: 6px;"><?= tr('gift_ph_proche_name') ?> *</label>
                            <input type="text" name="name" id="add-proche-name" class="pf-input" style="width: 100%;" required>
                        </div>
                        <div style="flex: 1; min-width: 150px;">
                            <label class="pf-label" style="display: block; margin-bottom: 6px;"><?= tr('gift_role_label') ?> *</label>
                            <select name="role" id="add-proche-role" class="pf-input" style="width: 100%;" required>
                                <option value="proche_adulte"><?= tr('gift_role_proche_adulte') ?></option>
                                <option value="proche_enfant"><?= tr('gift_role_proche_enfant') ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 5px;">
                        <button type="button" class="pf-btn btn-secondary" onclick="document.getElementById('form-add-proche').reset()"><?= tr('btn_cancel') ?></button>
                        <button type="submit" class="pf-btn pf-btn-primary"><?= tr('btn_add') ?></button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>


<script>
// ==========================================
// 1. LISTE DES CADEAUX (rendu côté navigateur à partir de GL)
// ==========================================
const GL = <?= json_encode($glData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
const T = GL.t;

const $id = id => document.getElementById(id);
const esc = s => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const money = n => n.toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ' + GL.cur;
const store = {
    get(k, d) { try { const v = localStorage.getItem(k); return v === null ? d : JSON.parse(v); } catch (e) { return d; } },
    set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} }
};
const hue = name => { let h = 0; for (const c of String(name)) h = (h * 31 + c.charCodeAt(0)) % 360; return h; };
const avatar = name => `<span class="gl-av" style="--h:${hue(name)}">${esc(String(name).trim().charAt(0).toUpperCase())}</span>`;

const saved = store.get('gl-state', {});
const state = {
    view: ['accounts', 'notes'].includes(saved.view) ? saved.view : 'ideas',
    occ: GL.occasions.some(o => o.code === saved.occ) ? saved.occ : GL.defaultOcc,
    fChild: [], fAdult: [], fStatus: ''
};
const closedGroups = new Set(store.get('gl-closed', []));
const persist = () => store.set('gl-state', {view: state.view, occ: state.occ});

const giftsOf = occ => GL.gifts.filter(g => g.occ === occ);
function filtered(occ) {
    return giftsOf(occ).filter(g =>
        (!state.fChild.length || state.fChild.includes(g.child)) &&
        (!state.fAdult.length || state.fAdult.includes(g.adult)) &&
        (!state.fStatus || (state.fStatus === 'bought') === !!g.bought));
}

function renderTabs() {
    $id('glTabs').innerHTML = GL.occasions.map(o => {
        const all = giftsOf(o.code), done = all.filter(g => g.bought).length;
        return `<button type="button" class="gl-tab${o.code === state.occ ? ' is-active' : ''}" data-occ="${esc(o.code)}">
            <span class="gl-tab-ic">${esc(o.icon)}</span><span class="gl-tab-nm">${esc(o.name)}</span>
            <span class="gl-tab-ct">${all.length ? done + '/' + all.length : '0'}</span></button>`;
    }).join('');
    const active = $id('glTabs').querySelector('.is-active');
    if (active) active.scrollIntoView({inline: 'center', block: 'nearest'});
}

function chipGroup(label, key, values, selected) {
    if (values.length < 2) return '';
    return `<div class="gl-frow"><span class="gl-flabel">${esc(label)}</span><div class="gl-chips gl-scroll">${
        values.map(v => `<button type="button" class="gl-chip${selected.includes(v) ? ' is-on' : ''}" data-f="${key}" data-v="${esc(v)}">${esc(v)}</button>`).join('')
    }</div></div>`;
}

function renderFilters() {
    const all = giftsOf(state.occ);
    const children = [...new Set(all.map(g => g.child))];
    const adults = [...new Set(all.map(g => g.adult))];
    state.fChild = state.fChild.filter(v => children.includes(v));
    state.fAdult = state.fAdult.filter(v => adults.includes(v));
    const statusChips = [['todo', T.toBuy], ['bought', T.bought]].map(([k, l]) =>
        `<button type="button" class="gl-chip${state.fStatus === (k === 'todo' ? 'todo' : 'bought') ? ' is-on' : ''}" data-f="status" data-v="${k}">${esc(l)}</button>`).join('');
    const active = state.fChild.length || state.fAdult.length || state.fStatus;
    $id('glFilters').innerHTML = all.length === 0 ? '' :
        chipGroup(T.for, 'child', children, state.fChild) +
        chipGroup(T.by, 'adult', adults, state.fAdult) +
        `<div class="gl-frow"><span class="gl-flabel">${esc(T.status)}</span><div class="gl-chips gl-scroll">${statusChips}${
            active ? `<button type="button" class="gl-chip gl-chip-reset" data-f="reset">${esc(T.reset)} ✕</button>` : ''}</div></div>`;
}

function renderSummary() {
    const list = filtered(state.occ);
    if (!giftsOf(state.occ).length) { $id('glSummary').innerHTML = ''; return; }
    const done = list.filter(g => g.bought);
    const spent = done.reduce((s, g) => s + g.amount, 0);
    const left = list.filter(g => !g.bought).reduce((s, g) => s + g.amount, 0);
    const pct = list.length ? Math.round(done.length * 100 / list.length) : 0;
    $id('glSummary').innerHTML = `
        <div class="gl-sum-top"><strong>${done.length}/${list.length}</strong> ${esc(T.bought.toLowerCase())}
            <span class="gl-sum-pct">${pct}%</span></div>
        <div class="gl-bar"><i style="width:${pct}%"></i></div>
        <div class="gl-sum-vals"><div><span>${esc(T.spent)}</span><b>${money(spent)}</b></div><div><span>${esc(T.remaining)}</span><b>${money(left)}</b></div></div>`;
}

function cardHtml(g) {
    const payer = g.payer && g.payer !== g.adult ? `<span class="gl-tag gl-tag-pay">💳 ${esc(g.payer)}</span>` : '';
    const link = g.link ? `<a class="gl-link" href="${esc(g.link)}" target="_blank" rel="noopener noreferrer" aria-label="Lien">🔗</a>` : '';
    return `<article class="gl-card${g.bought ? ' is-bought' : ''}" data-id="${g.id}">
        <button type="button" class="gl-check" data-act="toggle" aria-pressed="${g.bought ? 'true' : 'false'}" aria-label="${esc(T.markBought)}">✓</button>
        <div class="gl-card-main" data-act="edit">
            <div class="gl-card-title">${esc(g.desc)}</div>
            <div class="gl-card-meta"><span class="gl-tag">${avatar(g.adult)}${esc(g.adult)}</span>${payer}</div>
        </div>
        ${link}
        <div class="gl-price${g.amount ? '' : ' is-zero'}">${g.amount ? money(g.amount) : '—'}</div>
    </article>`;
}

function renderList() {
    const all = giftsOf(state.occ);
    if (!all.length) { $id('glList').innerHTML = `<p class="gl-empty">${esc(T.emptyOcc)}</p>`; return; }
    const list = filtered(state.occ);
    if (!list.length) { $id('glList').innerHTML = `<p class="gl-empty">${esc(T.noMatch)}</p>`; return; }
    const order = [...GL.children, ...new Set(list.map(g => g.child))].filter((v, i, a) => a.indexOf(v) === i);
    $id('glList').innerHTML = order.map(child => {
        const items = list.filter(g => g.child === child);
        if (!items.length) return '';
        const key = state.occ + '|' + child, done = items.filter(g => g.bought).length;
        const total = items.reduce((s, g) => s + g.amount, 0);
        return `<details class="gl-group" data-key="${esc(key)}"${closedGroups.has(key) ? '' : ' open'}>
            <summary>${avatar(child)}<span class="gl-gname">${esc(child)}</span>
                <span class="gl-gmeta">${done}/${items.length} · ${money(total)}</span><span class="gl-chev"></span></summary>
            <div class="gl-cards">${items.map(cardHtml).join('')}</div></details>`;
    }).join('');
}

function renderIdeas() { renderTabs(); renderSummary(); renderFilters(); renderList(); }

// ==========================================
// 2. COMPTES : qui a payé quoi, qui doit quoi (cadeaux achetés)
// ==========================================
function renderAccounts() {
    const bought = GL.gifts.filter(g => g.bought && g.amount > 0);
    const planned = GL.gifts.filter(g => !g.bought).reduce((s, g) => s + g.amount, 0);
    const people = [...new Set([...GL.adults, ...bought.map(g => g.adult), ...bought.map(g => g.payer)])].filter(Boolean).sort();
    const paid = {}, share = {}, matrix = {};
    people.forEach(p => { paid[p] = 0; share[p] = 0; matrix[p] = {}; people.forEach(q => matrix[p][q] = 0); });
    bought.forEach(g => {
        paid[g.payer] += g.amount; share[g.adult] += g.amount;
        if (g.adult !== g.payer) matrix[g.adult][g.payer] += g.amount;
    });
    const settle = [];
    for (let i = 0; i < people.length; i++) for (let j = i + 1; j < people.length; j++) {
        const a = people[i], b = people[j], net = matrix[a][b] - matrix[b][a];
        if (net > 0.01) settle.push({from: a, to: b, amt: net});
        else if (net < -0.01) settle.push({from: b, to: a, amt: -net});
    }
    settle.sort((x, y) => y.amt - x.amt);
    const active = people.filter(p => paid[p] || share[p]);
    const max = Math.max(1, ...active.map(p => Math.max(paid[p], share[p])));

    const settleHtml = settle.length ? settle.map(s => `
        <div class="gl-settle">${avatar(s.from)}<div class="gl-settle-txt"><b>${esc(s.from)}</b> ${esc(T.owes)} <span>${esc(T.to)}</span> <b>${esc(s.to)}</b></div>
        <div class="gl-settle-amt">${money(s.amt)}</div></div>`).join('')
        : `<p class="gl-ok">✅ ${esc(T.settled)}</p>`;

    const personHtml = active.map(p => {
        const net = paid[p] - share[p];
        const cls = net > 0.01 ? 'is-pos' : net < -0.01 ? 'is-neg' : '';
        return `<div class="gl-person">
            <div class="gl-person-top">${avatar(p)}<b>${esc(p)}</b><span class="gl-net ${cls}">${net > 0.01 ? '+' : ''}${money(net)}</span></div>
            <div class="gl-pbar"><span>${esc(T.paid)}</span><div class="gl-track"><i class="is-paid" style="width:${paid[p] * 100 / max}%"></i></div><em>${money(paid[p])}</em></div>
            <div class="gl-pbar"><span>${esc(T.share)}</span><div class="gl-track"><i class="is-share" style="width:${share[p] * 100 / max}%"></i></div><em>${money(share[p])}</em></div>
        </div>`;
    }).join('');

    const matrixHtml = `<div class="gl-mwrap"><table class="gl-matrix"><thead><tr><th>${esc(T.debtor)} \\ ${esc(T.creditor)}</th>${active.map(p => `<th>${esc(p)}</th>`).join('')}</tr></thead><tbody>${
        active.map(d => `<tr><th>${esc(d)}</th>${active.map(c => {
            const v = matrix[d][c];
            return `<td class="${v > 0.01 ? 'is-due' : ''}">${d === c || v < 0.01 ? '—' : money(v)}</td>`;
        }).join('')}</tr>`).join('')}</tbody></table></div>`;

    $id('glAccounts').innerHTML = `
        <section class="gl-card-block"><h2>⚖️ ${esc(T.whoOwes)}</h2>${settleHtml}
            <p class="gl-note">${esc(T.basedOn)}${planned > 0 ? ' · ' + esc(T.plannedLeft) + ' ' + money(planned) : ''}</p></section>
        <section class="gl-card-block"><h2>👥 ${esc(T.per)}</h2>${personHtml || `<p class="gl-note">—</p>`}</section>
        ${active.length > 1 ? `<details class="gl-card-block gl-matrix-d"><summary>${esc(T.matrix)}</summary>${matrixHtml}</details>` : ''}`;
}

// ==========================================
// 2 bis. NOTES : idées en vrac par enfant (un clic = fiche d'ajout de cadeau pré-remplie)
// ==========================================
const IDEAS_API = '/modules/gift-list/ideas-api.php';
const closedNotes = new Set(store.get('gl-notes-closed', []));
const ideasOf = child => GL.ideas.filter(i => i.child === child);

function renderNotes() {
    const kids = [...new Set([...GL.children, ...GL.ideas.map(i => i.child)])];
    $id('glNotes').innerHTML = `<p class="gl-note-hint">${esc(T.noteHint)}</p>` + kids.map(child => {
        const list = ideasOf(child);
        return `<details class="gl-group gl-notes-group" data-child="${esc(child)}"${closedNotes.has(child) ? '' : ' open'}>
            <summary>${avatar(child)}<span class="gl-gname">${esc(child)}</span>
                <span class="gl-gmeta">${list.length}</span><span class="gl-chev"></span></summary>
            <form class="gl-note-add" data-child="${esc(child)}" autocomplete="off">
                <input type="text" class="gl-note-input" placeholder="${esc(T.notePh)}" maxlength="500" enterkeyhint="done" aria-label="${esc(T.notePh)}">
                <button type="submit" class="gl-note-btn" aria-label="${esc(T.noteAdd)}">＋</button>
            </form>
            <div class="gl-notes">${list.length ? list.map(i => `<div class="gl-note" data-id="${i.id}">
                <button type="button" class="gl-note-txt" data-act="use">${esc(i.text)}</button>
                <button type="button" class="gl-note-del" data-act="del" aria-label="${esc(T.noteDel)}">×</button></div>`).join('') : `<p class="gl-note-empty">${esc(T.noteEmpty)}</p>`}</div>
        </details>`;
    }).join('');
}

async function ideasCall(fields) {
    const fd = new FormData();
    for (const k in fields) fd.append(k, fields[k]);
    const r = await fetch(IDEAS_API, {method: 'POST', body: fd});
    const j = await r.json().catch(() => ({}));
    if (!r.ok || !j.success) throw new Error(j.error || r.status);
    return j;
}

document.addEventListener('submit', async e => {
    const f = e.target.closest('.gl-note-add');
    if (!f) return;
    e.preventDefault();
    const input = f.querySelector('.gl-note-input'), text = input.value.trim();
    if (!text) return;
    input.disabled = true;
    try {
        const j = await ideasCall({action: 'add', child_name: f.dataset.child, idea: text});
        GL.ideas.push({id: j.id, child: f.dataset.child, text});
        renderNotes();
        const again = $id('glNotes').querySelector(`.gl-note-add[data-child="${CSS.escape(f.dataset.child)}"] .gl-note-input`);
        if (again) again.focus();
    } catch (err) { console.error(err); input.disabled = false; }
});

document.addEventListener('click', async e => {
    const row = e.target.closest('.gl-note');
    if (!row) return;
    const idea = GL.ideas.find(i => i.id === +row.dataset.id);
    if (!idea) return;
    if (e.target.closest('[data-act="del"]')) {
        try {
            await ideasCall({action: 'delete', id: idea.id});
            GL.ideas = GL.ideas.filter(i => i.id !== idea.id);
            renderNotes();
        } catch (err) { console.error(err); }
        return;
    }
    if (e.target.closest('[data-act="use"]')) openSheet(null, {desc: idea.text, child: idea.child});
});

document.addEventListener('toggle', e => {
    const d = e.target;
    if (!d.classList || !d.classList.contains('gl-notes-group')) return;
    if (d.open) closedNotes.delete(d.dataset.child); else closedNotes.add(d.dataset.child);
    store.set('gl-notes-closed', [...closedNotes]);
}, true);

function setView(v) {
    state.view = v; persist();
    document.querySelectorAll('.gl-seg-btn').forEach(b => b.classList.toggle('is-active', b.dataset.view === v));
    $id('glIdeas').hidden = v !== 'ideas';
    $id('glNotes').hidden = v !== 'notes';
    $id('glAccounts').hidden = v !== 'accounts';
    $id('glFab').hidden = v === 'accounts';
    if (v === 'ideas') renderIdeas(); else if (v === 'notes') renderNotes(); else renderAccounts();
}

// ==========================================
// 3. INTERACTIONS (onglets, filtres, coche, ouverture de la fiche)
// ==========================================
document.addEventListener('click', async e => {
    const seg = e.target.closest('.gl-seg-btn');
    if (seg) { setView(seg.dataset.view); return; }

    const tab = e.target.closest('.gl-tab');
    if (tab) { state.occ = tab.dataset.occ; state.fChild = []; state.fAdult = []; state.fStatus = ''; persist(); renderIdeas(); return; }

    const chip = e.target.closest('.gl-chip[data-f]');
    if (chip) {
        const f = chip.dataset.f, v = chip.dataset.v;
        if (f === 'reset') { state.fChild = []; state.fAdult = []; state.fStatus = ''; }
        else if (f === 'status') { state.fStatus = state.fStatus === (v === 'todo' ? 'todo' : 'bought') ? '' : (v === 'todo' ? 'todo' : 'bought'); }
        else {
            const arr = f === 'child' ? state.fChild : state.fAdult;
            const i = arr.indexOf(v); if (i >= 0) arr.splice(i, 1); else arr.push(v);
        }
        renderFilters(); renderSummary(); renderList();
        return;
    }

    const card = e.target.closest('.gl-card');
    if (card) {
        const g = GL.gifts.find(x => x.id === +card.dataset.id);
        if (!g) return;
        if (e.target.closest('[data-act="toggle"]')) { toggleBought(g); return; }
        if (e.target.closest('.gl-link')) return;
        if (e.target.closest('[data-act="edit"]') || e.target.closest('.gl-price')) openSheet(g);
        return;
    }

    if (e.target.closest('#glFab')) { openSheet(null); return; }
    if (e.target.closest('[data-close]') || e.target === $id('glSheet')) { closeSheet(); return; }
});

document.addEventListener('toggle', e => {
    const d = e.target;
    if (!d.classList || !d.classList.contains('gl-group')) return;
    if (d.open) closedGroups.delete(d.dataset.key); else closedGroups.add(d.dataset.key);
    store.set('gl-closed', [...closedGroups]);
}, true);

async function toggleBought(g) {
    g.bought = g.bought ? 0 : 1;
    renderTabs(); renderSummary(); renderList();
    const fd = new FormData();
    fd.append('action', 'toggle_bought'); fd.append('gift_id', g.id); fd.append('is_bought', g.bought);
    try {
        const r = await fetch('/modules/gift-list/save-gift.php', {method: 'POST', body: fd});
        if (!r.ok) throw new Error(r.status);
    } catch (err) {
        g.bought = g.bought ? 0 : 1;   // annulation visuelle si le serveur n'a pas suivi
        renderTabs(); renderSummary(); renderList();
    }
}

// ==========================================
// 4. FICHE D'AJOUT / ÉDITION (bottom sheet)
// ==========================================
const sheet = $id('glSheet'), form = $id('giftForm');

function radioChips(el, name, values, current) {
    el.innerHTML = values.map(v => `<label class="gl-chip"><input type="radio" name="${name}" value="${esc(v)}"${v === current ? ' checked' : ''} required><span>${esc(v)}</span></label>`).join('');
}

function openSheet(g, prefill) {
    const last = store.get('gl-last', {});
    const occ = g ? g.occ : state.occ;
    const child = g ? g.child : (prefill && GL.children.includes(prefill.child) ? prefill.child : state.fChild.length === 1 ? state.fChild[0] : (GL.children.includes(last.child) ? last.child : GL.children[0]));
    const adult = g ? g.adult : (GL.adults.includes(last.adult) ? last.adult : (GL.adults.includes(GL.me) ? GL.me : GL.adults[0]));

    $id('glSheetTitle').textContent = g ? T.editTitle : T.addTitle;
    $id('modalAction').value = g ? 'update' : 'create';
    $id('modalGiftId').value = g ? g.id : '';
    $id('modalDesc').value = g ? g.desc : (prefill ? prefill.desc : '');
    $id('modalAmount').value = g && g.amount ? String(g.amount).replace('.', ',') : '';
    $id('modalBought').checked = g ? !!g.bought : false;
    $id('modalLink').value = g ? g.link : '';
    radioChips($id('modalChildChips'), 'child_name', GL.children, child);
    radioChips($id('modalAdultChips'), 'adult_name', GL.adults, adult);

    const payer = $id('modalPayer');
    payer.innerHTML = '<option value="">' + esc(T.sameAsAdult || '=') + '</option>' + GL.adults.map(a => `<option value="${esc(a)}">${esc(a)}</option>`).join('');
    payer.value = g && g.payer && g.payer !== g.adult ? g.payer : '';
    $id('modalOccasion').innerHTML = GL.occasions.map(o => `<option value="${esc(o.code)}">${esc(o.icon + ' ' + o.name)}</option>`).join('');
    $id('modalOccasion').value = occ;

    form.querySelector('.gl-more').open = !!(g && (g.link || (g.payer && g.payer !== g.adult)));
    $id('glDelete').hidden = !g;
    sheet.hidden = false;
    document.body.classList.add('no-scroll');
    if (!g && !prefill) setTimeout(() => $id('modalDesc').focus(), 60);
}

function closeSheet() { sheet.hidden = true; document.body.classList.remove('no-scroll'); }
document.addEventListener('keydown', e => { if (e.key === 'Escape' && !sheet.hidden) closeSheet(); });

form.addEventListener('submit', async e => {
    e.preventDefault();
    const btn = form.querySelector('.gl-btn-save');
    btn.disabled = true;
    const fd = new FormData(form);
    try {
        const r = await fetch(form.getAttribute('action'), {method: 'POST', body: fd});
        if (!r.ok) throw new Error(r.status);
        store.set('gl-last', {child: fd.get('child_name'), adult: fd.get('adult_name')});
        state.occ = fd.get('occasion'); state.view = 'ideas'; persist();
        window.location.reload();
    } catch (err) {
        console.error(err);
        btn.disabled = false;
    }
});

$id('glDelete').addEventListener('click', async () => {
    const id = $id('modalGiftId').value;
    const ok = typeof pachaConfirm === 'function' ? await pachaConfirm('🗑️', T.confirmDelete) : confirm(T.confirmDelete);
    if (!ok) return;
    const fd = new FormData();
    fd.append('action', 'delete'); fd.append('gift_id', id);
    try {
        await fetch('/modules/gift-list/save-gift.php', {method: 'POST', body: fd});
        window.location.reload();
    } catch (err) { console.error(err); }
});

setView(state.view);


// ==========================================
// 3. GESTION DES PARAMÈTRES (MODALE FÊTES & PROCHES)
// ==========================================

function closeGiftSettings() {
    document.getElementById('modal-gift-settings').classList.remove('open');
    document.body.classList.remove('no-scroll');
    // Fêtes et proches modifiés : on recharge pour que la liste, les filtres et la fiche d'ajout les prennent en compte
    if (typeof hasSettingsChanged !== 'undefined' && hasSettingsChanged) window.location.reload();
}

document.getElementById('btn-open-gift-settings')?.addEventListener('click', async (e) => {
    e.preventDefault();
    document.getElementById('modal-gift-settings').classList.add('open');
    document.body.classList.add('no-scroll');
    await loadSettingsData();
});

document.querySelectorAll('.btn-close-settings').forEach(btn => btn.addEventListener('click', closeGiftSettings));

function switchGLTab(tabId) {
    // Gestion du contenu (Afficher/Cacher)
    document.getElementById('gl-pane-occasions').style.display = (tabId === 'occasions') ? 'block' : 'none';
    document.getElementById('gl-pane-proches').style.display = (tabId === 'proches') ? 'block' : 'none';

    // Gestion du style des boutons d'onglet
    const btnOcc = document.getElementById('tab-btn-occasions');
    const btnProche = document.getElementById('tab-btn-proches');

    if (tabId === 'occasions') {
        btnOcc.style.background = 'var(--bg-panel)';
        btnOcc.style.color = 'var(--primary)';
        btnOcc.style.boxShadow = 'var(--shadow-sm)';
        
        btnProche.style.background = 'transparent';
        btnProche.style.color = 'var(--text-muted)';
        btnProche.style.boxShadow = 'none';
    } else {
        btnProche.style.background = 'var(--bg-panel)';
        btnProche.style.color = 'var(--primary)';
        btnProche.style.boxShadow = 'var(--shadow-sm)';
        
        btnOcc.style.background = 'transparent';
        btnOcc.style.color = 'var(--text-muted)';
        btnOcc.style.boxShadow = 'none';
    }
}

let hasSettingsChanged = false;

async function loadSettingsData() {
    try {
        const res = await pachaFetch('/modules/gift-list/api-settings.php?action=get_all&_=' + Date.now());
        if (!res.success) throw new Error(res.error);
        
        renderOccasions(res.data.occasions);
        renderProches(res.data.proches);
    } catch (e) {
        document.getElementById('occasions-list').innerHTML = `<div class="pf-alert pf-alert--error">Erreur : ${e.message}</div>`;
        document.getElementById('proches-list').innerHTML = `<div class="pf-alert pf-alert--error">Erreur : ${e.message}</div>`;
    }
}

function renderOccasions(occasions) {
    const container = document.getElementById('occasions-list');
    if (!occasions.length) { container.innerHTML = `<em class="gift-empty-state">Vide</em>`; return; }

    let html = '';
    occasions.forEach(occ => {
        const isChecked = occ.is_active == 1 ? 'checked' : '';
        // ⚠️ Remarque l'ajout de id="row-occ-${occ.id}" ici :
        html += `
            <div class="gl-list-item" id="row-occ-${occ.id}">
                <div>
                    <span style="font-size:1.2rem; margin-right:8px;">${occ.icon || '🎁'}</span>
                    <strong>${occ.name}</strong> 
                    <small style="color:var(--text-muted); margin-left:8px;">${occ.month_date || ''}</small>
                </div>
                <div class="pf-flex-gap-8 align-center">
                    <label style="display:flex; align-items:center; cursor:pointer;">
                        <input type="checkbox" class="pf-checkbox-lg" ${isChecked} onchange="toggleOccasion(${occ.id}, this.checked)">
                    </label>
                    <button type="button" class="btn-icon-action edit" onclick="transformRowToEditOccasion('row-occ-${occ.id}', ${occ.id}, '${occ.name.replace(/'/g, "\\'")}', '${occ.icon}', '${occ.month_date || ''}')" title="${tr('btn_edit')}">✏️</button>
                    <button type="button" class="btn-icon-action delete" onclick="deleteOccasion(${occ.id})" title="${tr('btn_delete')}">🗑️</button>
                </div>
            </div>
        `;
    });
    container.innerHTML = html;
}

function renderProches(proches) {
    const container = document.getElementById('proches-list');
    if (!proches.length) { container.innerHTML = `<em class="gift-empty-state">Aucun proche configuré.</em>`; return; }

    let html = '';
    proches.forEach(p => {
        const roleLabel = p.role === 'proche_adulte' ? (tr('gift_role_proche_adulte') || 'Adulte') : (tr('gift_role_proche_enfant') || 'Enfant');
        html += `
            <div class="gl-list-item" id="row-proche-${p.id}">
                <div><strong>${p.name}</strong> <small style="color:var(--text-muted);">— ${roleLabel}</small></div>
                <div class="pf-flex-gap-8 align-center">
                    <button type="button" class="btn-icon-action edit" onclick="transformRowToEditProche('row-proche-${p.id}', ${p.id}, '${p.name.replace(/'/g, "\\'")}', '${p.role}')" title="${tr('btn_edit')}">✏️</button>
                    <button type="button" class="btn-icon-action delete" onclick="deleteProche(${p.id})" title="${tr('btn_delete')}">🗑️</button>
                </div>
            </div>
        `;
    });
    container.innerHTML = html;
}

// ==========================================
// CRUD : OCCASIONS
// ==========================================

// 1. Ajouter une occasion
document.getElementById('form-add-occasion')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    btn.disabled = true;
    hasSettingsChanged = true;
    
    const fd = new FormData(e.target); 
    fd.append('action', 'save_occasion');
    
    try {
        await pachaFetch('/modules/gift-list/api-settings.php', { method: 'POST', body: fd });
        
        // On vide le formulaire
        e.target.reset();
        
        // On remet l'émoji par défaut visuellement
        document.querySelectorAll('#form-add-occasion .emoji-option').forEach(el => el.classList.remove('selected'));
        document.querySelector('#form-add-occasion .emoji-option').classList.add('selected');
        document.getElementById('add-occ-icon').value = '🎁';
        
        showToast("Fête ajoutée avec succès !", "success");
        loadSettingsData(); // Recharge la liste
    } catch (err) {
        showToast("Erreur lors de l'ajout", "error");
        console.error(err);
    } finally {
        btn.disabled = false;
    }
});

// 2. Sauvegarder la modification (In-place)
async function saveInlineOccasion(id) {
    hasSettingsChanged = true;
    const fd = new FormData();
    fd.append('action', 'save_occasion');
    fd.append('id', id);
    fd.append('icon', document.getElementById(`inline-occ-icon-${id}`).value);
    fd.append('name', document.getElementById(`inline-occ-name-${id}`).value);
    fd.append('month_date', document.getElementById(`inline-occ-date-${id}`).value);

    try {
        await pachaFetch('/modules/gift-list/api-settings.php', { method: 'POST', body: fd });
        showToast("Fête modifiée avec succès !", "success");
        loadSettingsData(); 
    } catch (err) {
        showToast("Erreur lors de la modification", "error");
        console.error(err);
    }
}

// 3. Supprimer une occasion
async function deleteOccasion(id) {
    if (!confirm(tr('confirm_delete') || 'Supprimer définitivement ?')) return;
    hasSettingsChanged = true;
    const fd = new FormData(); 
    fd.append('action', 'delete_occasion'); 
    fd.append('id', id);
    
    try {
        await pachaFetch('/modules/gift-list/api-settings.php', { method: 'POST', body: fd });
        showToast("Fête supprimée.", "success");
        loadSettingsData();
    } catch (err) {
        showToast("Erreur lors de la suppression", "error");
    }
}

// 4. Activer / Désactiver (Checkbox)
async function toggleOccasion(id, state) {
    hasSettingsChanged = true;
    const fd = new FormData();
    fd.append('action', 'toggle_occasion');
    fd.append('id', id);
    fd.append('state', state ? 1 : 0);
    
    try {
        await pachaFetch('/modules/gift-list/api-settings.php', { method: 'POST', body: fd });
        showToast(state ? "Fête activée" : "Fête masquée", "success");
    } catch (err) {
        showToast("Erreur de mise à jour", "error");
    }
}

// ==========================================
// CRUD : PROCHES
// ==========================================

// 1. Ajouter un proche
document.getElementById('form-add-proche')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    btn.disabled = true;
    hasSettingsChanged = true;
    
    const fd = new FormData(e.target); 
    fd.append('action', 'save_proche');
    
    try {
        await pachaFetch('/modules/gift-list/api-settings.php', { method: 'POST', body: fd });
        e.target.reset();
        showToast("Proche ajouté avec succès !", "success");
        loadSettingsData();
    } catch (err) {
        showToast("Erreur lors de l'ajout", "error");
    } finally {
        btn.disabled = false;
    }
});

// 2. Sauvegarder la modification (In-place)
async function saveInlineProche(id) {
    hasSettingsChanged = true;
    const fd = new FormData();
    fd.append('action', 'save_proche');
    fd.append('id', id);
    fd.append('name', document.getElementById(`inline-proche-name-${id}`).value);
    fd.append('role', document.getElementById(`inline-proche-role-${id}`).value);

    try {
        await pachaFetch('/modules/gift-list/api-settings.php', { method: 'POST', body: fd });
        showToast("Proche mis à jour !", "success");
        loadSettingsData(); 
    } catch (err) {
        showToast("Erreur lors de la mise à jour", "error");
    }
}

// 3. Supprimer un proche
async function deleteProche(id) {
    if (!confirm(tr('confirm_delete') || 'Supprimer ce proche ?')) return;
    hasSettingsChanged = true;
    const fd = new FormData(); 
    fd.append('action', 'delete_proche'); 
    fd.append('id', id);
    
    try {
        await pachaFetch('/modules/gift-list/api-settings.php', { method: 'POST', body: fd });
        showToast("Proche retiré de la liste.", "success");
        loadSettingsData();
    } catch (err) {
        showToast("Erreur lors de la suppression", "error");
    }
}

// Rechargement intelligent lors de la fermeture si modifications
document.getElementById('modal-gift-settings').addEventListener('click', (e) => {
    if (e.target.id === 'modal-gift-settings' || e.target.classList.contains('btn-close-settings')) {
        if (hasSettingsChanged) {
            window.location.reload();
        } else {
            closeGiftSettings();
        }
    }
});

// Fonction pour gérer la sélection dans la grille d'émojis (formulaire d'ajout)
function selectEmoji(element, targetInputId, emoji) {
    document.getElementById(targetInputId).value = emoji;
    // Mise à jour visuelle de la sélection
    const siblings = element.parentNode.querySelectorAll('.emoji-option');
    siblings.forEach(el => el.classList.remove('selected'));
    element.classList.add('selected');
}

// ==========================================
// LOGIQUE D'ÉDITION IN-PLACE (OCCASIONS)
// ==========================================

// On crée une mémoire pour stocker le HTML d'origine de chaque ligne
const rowStates = {};

function transformRowToEditOccasion(rowId, id, name, icon, date) {
    const row = document.getElementById(rowId);
    
    // On sauvegarde l'état proprement en JS (fini les bugs d'affichage !)
    rowStates[rowId] = row.innerHTML; 

    row.innerHTML = `
        <div style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center; width: 100%; background: var(--bg-subtle); padding: 8px; border-radius: 8px; border: 1px solid var(--border-light);">
            <select id="inline-occ-icon-${id}" class="pf-input" style="width: 60px; padding: 6px; margin: 0;">
                <option value="🎁" ${icon==='🎁'?'selected':''}>🎁</option>
                <option value="🎄" ${icon==='🎄'?'selected':''}>🎄</option>
                <option value="🎂" ${icon==='🎂'?'selected':''}>🎂</option>
                <option value="🎉" ${icon==='🎉'?'selected':''}>🎉</option>
                <option value="🎓" ${icon==='🎓'?'selected':''}>🎓</option>
            </select>
            <input type="text" id="inline-occ-name-${id}" value="${name}" class="pf-input" style="flex: 1; min-width: 100px; padding: 6px; margin: 0;">
            <input type="text" id="inline-occ-date-${id}" value="${date}" class="pf-input" style="width: 70px; padding: 6px; margin: 0;" placeholder="MM-JJ">
            
            <div style="display: flex; gap: 4px; margin-left: auto;">
                <button type="button" onclick="saveInlineOccasion(${id})" class="pf-btn pf-btn-primary" style="padding: 6px 10px;">💾</button>
                <button type="button" onclick="cancelInlineEdit('${rowId}')" class="pf-btn btn-secondary" style="padding: 6px 10px;">❌</button>
            </div>
        </div>
    `;
}

async function saveInlineOccasion(id) {
    hasSettingsChanged = true;
    const fd = new FormData();
    fd.append('action', 'save_occasion');
    fd.append('id', id);
    fd.append('icon', document.getElementById(`inline-occ-icon-${id}`).value);
    fd.append('name', document.getElementById(`inline-occ-name-${id}`).value);
    fd.append('month_date', document.getElementById(`inline-occ-date-${id}`).value);

    // On met à jour en BDD puis on recharge la liste !
    await pachaFetch('/modules/gift-list/api-settings.php', { method: 'POST', body: fd });
    loadSettingsData(); 
}

// ==========================================
// LOGIQUE D'ÉDITION IN-PLACE (PROCHES)
// ==========================================

function transformRowToEditProche(rowId, id, name, role) {
    const row = document.getElementById(rowId);
    rowStates[rowId] = row.innerHTML; 

    const roleAdulte = window.I18N ? window.I18N['gift_role_proche_adulte'] : 'Adulte';
    const roleEnfant = window.I18N ? window.I18N['gift_role_proche_enfant'] : 'Enfant';

    row.innerHTML = `
        <div style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center; width: 100%; background: var(--bg-subtle); padding: 8px; border-radius: 8px; border: 1px solid var(--border-light);">
            <input type="text" id="inline-proche-name-${id}" value="${name}" class="pf-input" style="flex: 1; min-width: 100px; padding: 6px; margin: 0;">
            <select id="inline-proche-role-${id}" class="pf-input" style="width: 130px; padding: 6px; margin: 0;">
                <option value="proche_adulte" ${role==='proche_adulte'?'selected':''}>${roleAdulte}</option>
                <option value="proche_enfant" ${role==='proche_enfant'?'selected':''}>${roleEnfant}</option>
            </select>
            
            <div style="display: flex; gap: 4px; margin-left: auto;">
                <button type="button" onclick="saveInlineProche(${id})" class="pf-btn pf-btn-primary" style="padding: 6px 10px;">💾</button>
                <button type="button" onclick="cancelInlineEdit('${rowId}')" class="pf-btn btn-secondary" style="padding: 6px 10px;">❌</button>
            </div>
        </div>
    `;
}

async function saveInlineProche(id) {
    hasSettingsChanged = true;
    const fd = new FormData();
    fd.append('action', 'save_proche');
    fd.append('id', id);
    fd.append('name', document.getElementById(`inline-proche-name-${id}`).value);
    fd.append('role', document.getElementById(`inline-proche-role-${id}`).value);

    await pachaFetch('/modules/gift-list/api-settings.php', { method: 'POST', body: fd });
    loadSettingsData(); 
}

// Fonction pour restaurer la ligne (sans casser le HTML)
function cancelInlineEdit(rowId) {
    document.getElementById(rowId).innerHTML = rowStates[rowId];
}


</script>

<?php require __DIR__ . '/footer.php'; ?>
