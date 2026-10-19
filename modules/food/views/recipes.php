<?php
// modules/food/views/recipes.php
if (!isset($pdo)) exit;

$stmt =$pdo->query("SELECT * FROM pf_recipes ORDER BY is_favorite DESC, title ASC");
$recipes =$stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="recipe-library-header">
    <div style="display:flex; align-items:center; gap: 10px;">
        <input type="text" id="recipeSearch" class="pf-input" placeholder="<?= tr('recipe_search_ph') ?>" onkeyup="filterRecipes()">
        <button class="pf-btn btn-secondary" onclick="filterFavorites()" id="btnFavFilter">❤️</button>
    </div>
    <button class="pf-btn" onclick="openRecipeModal()"><?= tr('recipe_btn_add') ?></button>
</div>

<?php if (empty($recipes)): ?>
    <div class="pf-empty-state">
        <div style="font-size: 3rem; opacity: 0.5;">👨‍🍳</div>
        <p><?= tr('recipe_empty_state') ?></p>
        <button class="pf-btn" onclick="openRecipeModal()"><?= tr('recipe_btn_add') ?></button>
    </div>
<?php else: ?>
    <div class="recipe-grid" id="recipeGrid">
        <?php foreach ($recipes as $r):$img = !empty($r['image_url']) ? htmlspecialchars($r['image_url']) : '/modules/food/assets/img/default_recipe.jpg';
        ?>
        <div class="recipe-card" data-title="<?= strtolower(htmlspecialchars($r['title'])) ?>" data-fav="<?= $r['is_favorite'] ?>">
            <div class="recipe-card-img" style="background-image: url('<?= $img ?>');" onclick="window.location.href='?tab=recipe_detail&id=<?= $r['id'] ?>'">
                <button class="recipe-fav-btn <?= $r['is_favorite'] ? 'active' : '' ?>" onclick="event.stopPropagation(); toggleFav(<?= $r['id'] ?>, this)">❤️</button>
            </div>
            <div class="recipe-card-body" onclick="window.location.href='?tab=recipe_detail&id=<?= $r['id'] ?>'">
                <h3 class="recipe-card-title"><?= htmlspecialchars($r['title']) ?></h3>
                <div class="recipe-card-meta">
                    <span>⏱️ <?= $r['prep_time'] +$r['cook_time'] ?> min</span>
                    <span><?= str_repeat('⭐', $r['difficulty']) ?></span>
                </div>
            </div>
            <div class="recipe-card-actions">
                <button class="btn-icon-action edit" onclick='event.stopPropagation(); editRecipe(<?= json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>✏️</button>
                <button class="btn-icon-action delete" onclick="event.stopPropagation(); deleteRecipe(<?= $r['id'] ?>)">🗑️</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- MODALE CRÉATION/ÉDITION -->
<div id="recipeModal" class="pf-modal">
    <div class="pf-modal-content" style="max-width: 800px; max-height: 90vh; display: flex; flex-direction: column;">
        <div class="pf-modal-header" style="flex-shrink: 0;">
            <h3 class="pf-modal-title" id="recipeModalTitle" style="margin:0; border:none; padding:0;"><?= tr('recipe_modal_title_add') ?></h3>
            <button class="pf-modal-close" onclick="closeRecipeModal()">×</button>
        </div>
        
        <!-- ✨ ZONE IMPORT MAGIQUE ✨ -->
        <div id="magicImportZone" style="background: linear-gradient(135deg, var(--bg-soft), var(--bg-panel)); border: 1px dashed var(--primary); padding: 15px; border-radius: 8px; margin-bottom: 20px; flex-shrink: 0;">
            <label class="pf-label" style="color: var(--primary);">✨ <?= tr('recipe_magic_import') ?></label>
            <div style="display: flex; gap: 10px; align-items: flex-start;">
                <textarea id="magicImportInput" class="pf-input" rows="2" placeholder="<?= tr('recipe_magic_ph') ?>" style="flex: 1; resize: vertical;"></textarea>
                <button type="button" class="pf-btn" id="btnMagicImport" onclick="executeMagicImport()">✨ Analyser</button>
            </div>
            <small style="color: var(--text-muted); display: block; margin-top: 5px;"><?= tr('recipe_magic_desc') ?></small>
        </div>

        <form id="recipeForm" onsubmit="saveRecipe(event)" style="overflow-y: auto; padding-right: 5px; flex: 1;">
            <input type="hidden" name="action" value="save_recipe">
            <input type="hidden" name="id" id="recipe_id">

            <div class="pf-form-group">
                <label class="pf-label"><?= tr('recipe_lbl_title') ?> *</label>
                <input type="text" name="title" id="recipe_title" class="pf-input" required>
            </div>
            
            <div class="pf-form-group">
                <label class="pf-label"><?= tr('recipe_lbl_image_url') ?></label>
                <input type="url" name="image_url" id="recipe_image" class="pf-input" placeholder="https://...">
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px; margin-bottom: 20px;">
                <div><label class="pf-label"><?= tr('recipe_prep_time') ?> (m)</label><input type="number" name="prep_time" id="recipe_prep" class="pf-input" value="0"></div>
                <div><label class="pf-label"><?= tr('recipe_cook_time') ?> (m)</label><input type="number" name="cook_time" id="recipe_cook" class="pf-input" value="0"></div>
                <div><label class="pf-label"><?= tr('recipe_rest_time') ?> (m)</label><input type="number" name="rest_time" id="recipe_rest" class="pf-input" value="0"></div>
                <div><label class="pf-label"><?= tr('recipe_servings') ?></label><input type="number" name="base_servings" id="recipe_servings" class="pf-input" value="4"></div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                <div>
                    <label class="pf-label"><?= tr('recipe_difficulty') ?></label>
                    <select name="difficulty" id="recipe_diff" class="pf-input">
                        <option value="1"><?= tr('recipe_diff_1') ?></option>
                        <option value="2"><?= tr('recipe_diff_2') ?></option>
                        <option value="3"><?= tr('recipe_diff_3') ?></option>
                    </select>
                </div>
                <div>
                    <label class="pf-label"><?= tr('recipe_cost') ?></label>
                    <select name="cost" id="recipe_cost" class="pf-input">
                        <option value="1"><?= tr('recipe_cost_1') ?></option>
                        <option value="2"><?= tr('recipe_cost_2') ?></option>
                        <option value="3"><?= tr('recipe_cost_3') ?></option>
                    </select>
                </div>
            </div>

            <hr style="border:0; border-top:1px solid var(--border-light); margin: 20px 0;">
            
            <h4 class="pf-card-h2" style="margin-bottom:10px;"><?= tr('recipe_ingredients') ?></h4>
            <div id="ingContainer"></div>
            <button type="button" class="pf-btn btn-secondary pf-btn-sm" style="margin-bottom: 20px;" onclick="addIngRow()">+ Ajouter un ingrédient</button>

            <h4 class="pf-card-h2" style="margin-bottom:10px;"><?= tr('recipe_steps') ?></h4>
            <div id="stepContainer"></div>
            <button type="button" class="pf-btn btn-secondary pf-btn-sm" style="margin-bottom: 20px;" onclick="addStepRow()">+ Ajouter une étape</button>

            <hr style="border:0; border-top:1px solid var(--border-light); margin: 20px 0;">

            <div class="pf-form-group">
                <label class="pf-label">💡 <?= tr('recipe_chef_tip') ?></label>
                <textarea name="chef_tip" id="recipe_tip" class="pf-input" rows="3"></textarea>
            </div>

            <div class="modal-footer" style="flex-shrink: 0;">
                <button type="button" class="pf-btn btn-secondary" onclick="closeRecipeModal()"><?= tr('btn_cancel') ?></button>
                <button type="submit" class="pf-btn"><?= tr('btn_save') ?></button>
            </div>
        </form>
    </div>
</div>

<script>
let showOnlyFav = false;

function filterRecipes() {
    const q = document.getElementById('recipeSearch').value.toLowerCase();
    document.querySelectorAll('.recipe-card').forEach(card => {
        const title = card.getAttribute('data-title');
        const isFav = card.getAttribute('data-fav') === '1';
        const matchQ = title.includes(q);
        const matchFav = showOnlyFav ? isFav : true;
        card.style.display = (matchQ && matchFav) ? 'flex' : 'none';
    });
}

function filterFavorites() {
    showOnlyFav = !showOnlyFav;
    document.getElementById('btnFavFilter').style.background = showOnlyFav ? 'var(--bg-danger-soft)' : '';
    filterRecipes();
}

async function toggleFav(id, btn) {
    const isFav = btn.classList.contains('active') ? 0 : 1;
    btn.classList.toggle('active');
    btn.closest('.recipe-card').setAttribute('data-fav', isFav);
    
    const fd = new FormData();
    fd.append('action', 'toggle_favorite'); fd.append('id', id); fd.append('state', isFav);
    await pachaFetch('/modules/food/includes/api/api-recipes.php', { method: 'POST', body: fd });
    if(showOnlyFav) filterRecipes();
}

function openRecipeModal() {
    document.getElementById('recipeForm').reset();
    document.getElementById('recipe_id').value = '';
    document.getElementById('recipeModalTitle').innerText = window.I18N['recipe_modal_title_add'] || 'Ajouter';
    document.getElementById('ingContainer').innerHTML = '';
    document.getElementById('stepContainer').innerHTML = '';
    document.getElementById('magicImportInput').value = '';
    document.getElementById('magicImportZone').style.display = 'block'; // Affiche l'import en création
    addIngRow(); addStepRow();
    document.getElementById('recipeModal').classList.add('open');
    document.body.classList.add('no-scroll');
}

async function executeMagicImport() {
    const input = document.getElementById('magicImportInput').value.trim();
    if(!input) return;
    
    const btn = document.getElementById('btnMagicImport');
    const originalText = btn.innerText;
    btn.innerText = '⏳ Analyse...';
    btn.disabled = true;

    const fd = new FormData();
    fd.append('import_data', input);

    try {
        const res = await pachaFetch('/modules/food/includes/api/api-recipes-import.php', { method: 'POST', body: fd });
        
        if (res.success && res.data) {
            const data = res.data;
            if(data.title) document.getElementById('recipe_title').value = data.title;
            if(data.image_url) document.getElementById('recipe_image').value = data.image_url;
            if(data.prep_time) document.getElementById('recipe_prep').value = data.prep_time;
            if(data.cook_time) document.getElementById('recipe_cook').value = data.cook_time;
            if(data.base_servings) document.getElementById('recipe_servings').value = data.base_servings;
            if(data.difficulty) document.getElementById('recipe_diff').value = data.difficulty;
            if(data.chef_tip) document.getElementById('recipe_tip').value = data.chef_tip;

            document.getElementById('ingContainer').innerHTML = '';
            if (data.ingredients && data.ingredients.length > 0) {
                data.ingredients.forEach(i => addIngRow(i.qty, i.unit, i.name));
            } else { addIngRow(); }

            document.getElementById('stepContainer').innerHTML = '';
            if (data.steps && data.steps.length > 0) {
                data.steps.forEach(s => addStepRow(s.title, s.instruction));
            } else { addStepRow(); }

            if(window.showToast) showToast("Import réussi !", "success");
            document.getElementById('magicImportInput').value = ''; // On vide pour faire propre
        } else {
            alert(res.error || "Erreur d'analyse.");
        }
    } catch(err) {
        alert("Erreur réseau ou technique.");
    } finally {
        btn.innerText = originalText;
        btn.disabled = false;
    }
}

function closeRecipeModal() {
    document.getElementById('recipeModal').classList.remove('open');
    document.body.classList.remove('no-scroll');
}

function addIngRow(qty='', unit='', name='') {
    const html = `
        <div style="display:flex; gap:8px; margin-bottom:8px; align-items:center;">
            <input type="number" step="0.01" name="ing_qty[]" class="pf-input" style="width:80px;" placeholder="Qté" value="${qty}">
            <input type="text" name="ing_unit[]" class="pf-input" style="width:80px;" placeholder="Unité" value="${unit}">
            <input type="text" name="ing_name[]" class="pf-input" style="flex:1;" placeholder="Ingrédient..." value="${name}" required>
            <button type="button" class="btn-icon-action delete" onclick="this.parentElement.remove()">×</button>
        </div>`;
    document.getElementById('ingContainer').insertAdjacentHTML('beforeend', html);
}

function addStepRow(title='', instruction='') {
    const html = `
        <div style="background:var(--bg-subtle); padding:10px; border-radius:8px; border:1px solid var(--border-light); margin-bottom:10px;">
            <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                <input type="text" name="step_title[]" class="pf-input pf-input-sm" placeholder="Titre (Optionnel)" value="${title}" style="flex:1;">
                <button type="button" class="btn-icon-action delete" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
            <textarea name="step_instruction[]" class="pf-input" rows="2" placeholder="Instructions de l'étape..." required>${instruction}</textarea>
        </div>`;
    document.getElementById('stepContainer').insertAdjacentHTML('beforeend', html);
}

function editRecipe(r) {
    document.getElementById('recipe_id').value = r.id;
    document.getElementById('recipe_title').value = r.title;
    document.getElementById('recipe_image').value = r.image_url;
    document.getElementById('recipe_prep').value = r.prep_time;
    document.getElementById('recipe_cook').value = r.cook_time;
    document.getElementById('recipe_rest').value = r.rest_time;
    document.getElementById('recipe_servings').value = r.base_servings;
    document.getElementById('recipe_diff').value = r.difficulty;
    document.getElementById('recipe_cost').value = r.cost;
    document.getElementById('recipe_tip').value = r.chef_tip;
    document.getElementById('recipeModalTitle').innerText = window.I18N['recipe_modal_title_edit'] || 'Modifier';

    document.getElementById('ingContainer').innerHTML = '';
    document.getElementById('stepContainer').innerHTML = '';
    document.getElementById('magicImportZone').style.display = 'none';
    
    // On doit charger les ingrédients via une sous-requête JS rapide ou recharger la page
    // Pour cet exemple, on recharge la page détaillée pour modifier, ou on fetch
    // On gère l'édition en redirigeant vers la page détaillée et en modifiant là-bas, 
    // OU on fetch les inrédients ici. Pour faire simple et robuste :
    window.location.href = '?tab=recipe_detail&id=' + r.id + '&edit=1';
}

async function saveRecipe(e) {
    e.preventDefault();
    const fd = new FormData(e.target);
    try {
        const res = await pachaFetch('/modules/food/includes/api/api-recipes.php', {method: 'POST', body: fd});
        if(res.success) window.location.reload();
    } catch(err) { console.error(err); }
}

async function deleteRecipe(id) {
    if(!confirm(window.I18N['recipe_confirm_delete'] || 'Supprimer ?')) return;
    const fd = new FormData(); fd.append('action', 'delete_recipe'); fd.append('id', id);
    await pachaFetch('/modules/food/includes/api/api-recipes.php', {method: 'POST', body: fd});
    window.location.reload();
}
</script>