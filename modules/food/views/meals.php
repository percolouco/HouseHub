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
        <h1 class="meals-title">
            <span class="meals-title-icon">🍽️</span> 
            <span data-i18n="meals_board_title"><?= tr('meals_board_title') ?></span>
        </h1>
        <div class="meals-actions">
            <!-- NOUVEAU : Bouton Import PDF -->
            <button type="button" class="pf-btn btn-secondary" onclick="document.getElementById('importMenuModal').classList.add('open'); document.body.classList.add('no-scroll');">
                📄 <?= tr('meal_btn_import_pdf') ?>
            </button>
        </div>
    </div>

    <!-- Barre contextuelle (Fixe en bas) -->
    <div class="context-toolbar" id="contextToolbar">
        <button class="ctx-btn" onmousedown="event.preventDefault(); applyToAllInRow()">➡️ <span data-i18n="btn_apply_all"><?= tr('btn_apply_all') ?></span></button>
        <button class="ctx-btn primary" onmousedown="event.preventDefault(); openCopySheetFromCell()">🔄 <span data-i18n="btn_copy_to"><?= tr('btn_copy_to') ?></span></button>
    </div>

    <div class="meals-wrapper">
        <?php 
        $weeks = array_chunk($dates, 7);
        foreach ($weeks as $weekIndex => $weekDates): 
            $startWeekStr = date('d/m', strtotime($weekDates[0]));
            $endWeekStr = date('d/m', strtotime(end($weekDates)));
        ?>
            <h2 class="meals-week-title"><?= tr('meal_week') ?> (<?= $startWeekStr ?> - <?= $endWeekStr ?>)</h2>
            <div class="meals-table-container">
                <table class="meals-table" id="mealsTable_<?= $weekIndex ?>">
                    <thead>
                        <tr>
                            <th class="col-sticky"></th>
                            <?php foreach ($members as $m): ?>
                                <th><?= htmlspecialchars(strtoupper($m['name'])) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($weekDates as $dateStr): 
                            $dayKey = strtolower(date('D', strtotime($dateStr)));
                            $dayName = tr('day_' . $dayKey);
                            $dayDate = date('d/m', strtotime($dateStr));
                            $fullDayName = tr('day_full_' . $dayKey) . ' ' . $dayDate;
                        ?>
                            <tr class="row-lunch">
                                <td class="col-sticky">
                                    <span class="day-label"><?= $dayName ?> <?= $dayDate ?></span>
                                    <div class="service-icon" onmousedown="event.preventDefault(); openCopySheetFromRow('<?= $dateStr ?>', 'lunch', '<?= addslashes($fullDayName) ?>')" title="<?= tr('meals_copy_lunch') ?>">☀️</div>
                                </td>
                                <?php foreach ($members as $m): 
                                    $val = $mealsData[$dateStr]['lunch'][$m['id']] ?? '';
                                ?>
                                    <td class="meal-input-cell">
                                        <textarea class="meal-input js-meal-input" data-d="<?= $dateStr ?>" data-s="lunch" data-p="<?= $m['id'] ?>" placeholder="-"><?= htmlspecialchars($val) ?></textarea>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            
                            <tr class="row-dinner">
                                <td class="col-sticky">
                                    <span class="day-label invisible"><?= $dayName ?> <?= $dayDate ?></span>
                                    <div class="service-icon" onmousedown="event.preventDefault(); openCopySheetFromRow('<?= $dateStr ?>', 'dinner', '<?= addslashes($fullDayName) ?>')" title="<?= tr('meals_copy_dinner') ?>">🌙</div>
                                </td>
                                <?php foreach ($members as $m): 
                                    $val = $mealsData[$dateStr]['dinner'][$m['id']] ?? '';
                                ?>
                                    <td class="meal-input-cell">
                                        <textarea class="meal-input js-meal-input" data-d="<?= $dateStr ?>" data-s="dinner" data-p="<?= $m['id'] ?>" placeholder="-"><?= htmlspecialchars($val) ?></textarea>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Bottom Sheet de Copie (Format Grille Ultra-compacte) -->
<div id="copyModal" class="pf-modal pf-bottom-sheet">
    <div class="pf-modal-content pf-bs-content copy-modal-large">
        <div class="bs-header">
            <h3 class="bs-title">🔄 <span data-i18n="modal_copy_title"><?= tr('modal_copy_title') ?></span></h3>
            <button class="bs-close" onclick="closeCopyModal()">&times;</button>
        </div>
        <div id="copySourceLabel" class="meals-copy-source">Source : ...</div>
        
        <div class="week-section-title" data-i18n="week_current"><?= tr('week_current') ?></div>
        <div class="copy-days-grid">
            <?php for($i=0; $i<7; $i++): $dStr = $dates[$i]; $dName = tr('day_full_' . strtolower(date('D', strtotime($dStr)))) . ' ' . date('d/m', strtotime($dStr)); ?>
                <div class="copy-day-card">
                    <div class="copy-day-name"><?= $dName ?></div>
                    <div class="copy-day-actions">
                        <button type="button" class="btn-target-service" onmousedown="event.preventDefault(); executeCopy('<?= $dStr ?>', 'lunch')" title="<?= tr('meal_lunch') ?>">☀️</button>
                        <button type="button" class="btn-target-service" onmousedown="event.preventDefault(); executeCopy('<?= $dStr ?>', 'dinner')" title="<?= tr('meal_dinner') ?>">🌙</button>
                    </div>
                </div>
            <?php endfor; ?>
        </div>

        <div class="week-section-title week-section-mt" data-i18n="week_next"><?= tr('week_next') ?></div>
        <div class="copy-days-grid">
            <?php for($i=7; $i<14; $i++): $dStr = $dates[$i]; $dName = tr('day_full_' . strtolower(date('D', strtotime($dStr)))) . ' ' . date('d/m', strtotime($dStr)); ?>
                <div class="copy-day-card">
                    <div class="copy-day-name"><?= $dName ?></div>
                    <div class="copy-day-actions">
                        <button type="button" class="btn-target-service" onmousedown="event.preventDefault(); executeCopy('<?= $dStr ?>', 'lunch')" title="<?= tr('meal_lunch') ?>">☀️</button>
                        <button type="button" class="btn-target-service" onmousedown="event.preventDefault(); executeCopy('<?= $dStr ?>', 'dinner')" title="<?= tr('meal_dinner') ?>">🌙</button>
                    </div>
                </div>
            <?php endfor; ?>
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
document.addEventListener('DOMContentLoaded', () => {
    // 1. Sauvegarde automatique AJAX à chaque modification
    document.querySelectorAll('.js-meal-input').forEach(input => {
        input.addEventListener('change', async function() {
            const fd = new FormData();
            fd.append('action', 'save_cell');
            fd.append('plan_date', this.dataset.d);
            fd.append('service', this.dataset.s);
            fd.append('person_id', this.dataset.p);
            fd.append('meal_name', this.value);

            try {
                await pachaFetch('/modules/food/includes/api/save-meals.php', { method: 'POST', body: fd });
                flashSuccess(this);
            } catch(e) {
                console.error("Save error", e);
            }
        });
    });

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
// Focus Context Bar & Gestion dynamique du clavier (Mobile)
// ============================================================================
let copyState = { type: null, sourceDate: null, service: null, personIdx: null, sourceElements: [], fullDayName: '' };

// Fonction de calcul pour maintenir la barre au-dessus du clavier virtuel
const updateToolbarPosition = () => {
    const toolbar = document.getElementById('contextToolbar');
    if (toolbar && toolbar.classList.contains('active') && window.visualViewport) {
        const layoutHeight = document.documentElement.clientHeight;
        const visualHeight = window.visualViewport.height;
        const offsetTop = window.visualViewport.offsetTop;
        
        // Calcul exact de l'espace masqué par le clavier
        const bottomOffset = layoutHeight - (visualHeight + offsetTop);
        toolbar.style.bottom = Math.max(0, bottomOffset) + 'px';
    }
};

// Écouteurs pour s'adapter au redimensionnement (ouverture clavier) et au scroll
if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', updateToolbarPosition);
    window.visualViewport.addEventListener('scroll', updateToolbarPosition);
}

document.addEventListener('focusin', (e) => {
    if(e.target.classList.contains('js-meal-input')) {
        copyState = {
            type: 'cell',
            sourceDate: e.target.getAttribute('data-d'),
            service: e.target.getAttribute('data-s'),
            personIdx: e.target.getAttribute('data-p'),
            sourceElements: [e.target],
            fullDayName: ''
        };
        document.getElementById('contextToolbar').classList.add('active');
        
        // On déclenche le repositionnement avec un léger délai pour laisser le clavier monter
        setTimeout(updateToolbarPosition, 150); 
    }
});

document.addEventListener('focusout', (e) => {
    setTimeout(() => {
        if(document.activeElement && !document.activeElement.classList.contains('js-meal-input')) {
            const toolbar = document.getElementById('contextToolbar');
            if (toolbar) {
                toolbar.classList.remove('active');
                toolbar.style.bottom = ''; // On retire le style JS pour redonner le contrôle au CSS (-80px)
            }
        }
    }, 150);
});

// Actions Toolbar
window.applyToAllInRow = async function() {
    if(copyState.type !== 'cell' || !copyState.sourceElements[0].value.trim()) return;
    const val = copyState.sourceElements[0].value;
    const inputs = document.querySelectorAll(`.js-meal-input[data-d="${copyState.sourceDate}"][data-s="${copyState.service}"]`);
    
    let updates = [];
    inputs.forEach(inp => {
        if(inp !== copyState.sourceElements[0]) {
            inp.value = val;
            flashSuccess(inp);
            updates.push({ plan_date: inp.dataset.d, service: inp.dataset.s, person_id: inp.dataset.p, meal_name: val });
        }
    });

    if (updates.length > 0) {
        const fd = new FormData();
        fd.append('action', 'save_bulk');
        fd.append('updates', JSON.stringify(updates));
        await pachaFetch('/modules/food/includes/api/save-meals.php', { method: 'POST', body: fd });
    }
};

window.openCopySheetFromCell = function() {
    if(!copyState.sourceElements[0] || !copyState.sourceElements[0].value.trim()) return;
    if(document.activeElement) document.activeElement.blur();
    
    const svcLabel = copyState.service === 'lunch' ? (window.I18N['meal_lunch'] || 'Midi') : (window.I18N['meal_dinner'] || 'Soir');
    const dObj = new Date(copyState.sourceDate);
    const dayKey = dObj.toLocaleDateString('en-US', {weekday: 'short'}).toLowerCase();
    const dayName = window.I18N['day_full_' + dayKey] || dObj.toLocaleDateString();
    
    document.getElementById('copySourceLabel').innerText = `Source : ${window.I18N['source_individual'] || 'Individuel'} (${dayName} ${svcLabel})`;
    
    document.getElementById('copyModal').classList.add('open');
    document.body.classList.add('no-scroll');
};

window.openCopySheetFromRow = function(dateStr, service, fullDayName) {
    const inputs = document.querySelectorAll(`.js-meal-input[data-d="${dateStr}"][data-s="${service}"]`);
    if (!Array.from(inputs).some(inp => inp.value.trim() !== '')) return;

    copyState = { type: 'row', sourceDate: dateStr, service: service, sourceElements: Array.from(inputs), fullDayName: fullDayName };
    const svcLabel = service === 'lunch' ? (window.I18N['meal_lunch'] || 'Midi') : (window.I18N['meal_dinner'] || 'Soir');
    document.getElementById('copySourceLabel').innerText = `Source : ${window.I18N['source_row'] || 'Repas commun'} (${fullDayName} ${svcLabel})`;
    
    document.getElementById('copyModal').classList.add('open');
    document.body.classList.add('no-scroll');
};

window.closeCopyModal = function() {
    document.getElementById('copyModal').classList.remove('open');
    document.body.classList.remove('no-scroll');
};

window.executeCopy = async function(targetDate, targetService) {
    let updates = [];

    if(copyState.type === 'cell') {
        const val = copyState.sourceElements[0].value;
        const targetInput = document.querySelector(`.js-meal-input[data-d="${targetDate}"][data-s="${targetService}"][data-p="${copyState.personIdx}"]`);
        if(targetInput) {
            targetInput.value = val;
            flashSuccess(targetInput);
            updates.push({ plan_date: targetDate, service: targetService, person_id: copyState.personIdx, meal_name: val });
        }
    } else if (copyState.type === 'row') {
        const targetInputs = document.querySelectorAll(`.js-meal-input[data-d="${targetDate}"][data-s="${targetService}"]`);
        copyState.sourceElements.forEach((inp, idx) => {
            if(inp.value.trim() !== '') {
                targetInputs[idx].value = inp.value;
                flashSuccess(targetInputs[idx]);
                updates.push({ plan_date: targetDate, service: targetService, person_id: targetInputs[idx].dataset.p, meal_name: inp.value });
            }
        });
    }

    closeCopyModal();

    if (updates.length > 0) {
        const fd = new FormData();
        fd.append('action', 'save_bulk');
        fd.append('updates', JSON.stringify(updates));
        await pachaFetch('/modules/food/includes/api/save-meals.php', { method: 'POST', body: fd });
    }
};

function flashSuccess(input) {
    input.style.transition = 'background 0.3s';
    input.style.background = 'var(--alert-success-bg)';
    setTimeout(() => input.style.background = 'transparent', 400);
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
    // On ne s'active que si on colle dans un textarea de repas
    if (!e.target.classList.contains('js-meal-input')) return;

    const clipboardData = e.clipboardData || window.clipboardData;
    const pastedText = clipboardData.getData('text/plain');

    // Si le texte ne contient ni tabulation ni saut de ligne, c'est un collage simple.
    // On laisse le navigateur faire son comportement par défaut.
    if (!pastedText.includes('\t') && !pastedText.includes('\n')) return;

    // C'est une grille ! On bloque le comportement par défaut
    e.preventDefault();

    const rows = pastedText.split(/\r?\n/);
    const startTd = e.target.closest('td');
    const startTr = e.target.closest('tr');
    const startColIndex = Array.from(startTr.children).indexOf(startTd);

    let currentTr = startTr;
    let updates = [];

    for (let i = 0; i < rows.length; i++) {
        if (!currentTr) break; // Fin du tableau visuel atteinte
        
        // Ignorer la dernière ligne vide souvent générée par les tableurs lors d'une copie
        if (rows[i].trim() === '' && i === rows.length - 1) continue;

        const cells = rows[i].split('\t');
        const trChildren = Array.from(currentTr.children);

        for (let j = 0; j < cells.length; j++) {
            const targetTd = trChildren[startColIndex + j];
            if (!targetTd) continue; // Fin de la colonne visuelle atteinte

            const targetTextarea = targetTd.querySelector('.js-meal-input');
            if (targetTextarea) {
                const val = cells[j].trim();
                targetTextarea.value = val;
                
                // Animation de succès visuel
                if (typeof flashSuccess === 'function') flashSuccess(targetTextarea);
                
                // Préparation pour le Bulk Save
                updates.push({
                    plan_date: targetTextarea.dataset.d,
                    service: targetTextarea.dataset.s,
                    person_id: targetTextarea.dataset.p,
                    meal_name: val
                });
            }
        }
        // Descendre d'une ligne dans le tableau (ex: passer de Midi à Soir, ou de Soir au Midi du lendemain)
        currentTr = currentTr.nextElementSibling;
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
</script>