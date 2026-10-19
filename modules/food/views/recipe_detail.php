<?php
// modules/food/views/recipe_detail.php
if (!isset($pdo)) exit;

$id = (int)($_GET['id'] ?? 0);
if (!$id) { echo "<p>Recette invalide.</p>"; return; }

$stmt =$pdo->prepare("SELECT * FROM pf_recipes WHERE id = ?");
$stmt->execute([$id]);
$recipe =$stmt->fetch(PDO::FETCH_ASSOC);

if (!$recipe) { echo "<p>Recette introuvable.</p>"; return; }

$ings =$pdo->prepare("SELECT * FROM pf_recipe_ingredients WHERE recipe_id = ? ORDER BY sort_order");
$ings->execute([$id]);
$ingredients =$ings->fetchAll(PDO::FETCH_ASSOC);

$steps =$pdo->prepare("SELECT * FROM pf_recipe_steps WHERE recipe_id = ? ORDER BY sort_order");
$steps->execute([$id]);
$instructions =$steps->fetchAll(PDO::FETCH_ASSOC);

$img = !empty($recipe['image_url']) ? htmlspecialchars($recipe['image_url']) : '/modules/food/assets/img/default_recipe.jpg';
$totalTime =$recipe['prep_time'] + $recipe['cook_time'] +$recipe['rest_time'];
?>

<div class="recipe-view-container">
    <div class="recipe-hero" style="background-image: url('<?= $img ?>');">
        <div class="recipe-hero-overlay"></div>
        <div class="recipe-hero-content">
            <a href="?tab=recipes" class="recipe-back-btn">◀ <?= tr('btn_back') ?></a>
            <h1 class="recipe-title"><?= htmlspecialchars($recipe['title']) ?></h1>
            <div class="recipe-tags">
                <span class="recipe-tag"><?= str_repeat('⭐', $recipe['difficulty']) ?> <?= tr('recipe_diff_' .$recipe['difficulty']) ?></span>
                <span class="recipe-tag"><?= str_repeat('€', $recipe['cost']) ?> <?= tr('recipe_cost_' .$recipe['cost']) ?></span>
                <span class="recipe-tag">⏱️ <?= $totalTime ?> min</span>
            </div>
            <div class="recipe-actions-bar">
                <button class="pf-btn" onclick="addToGroceries()">🛒 <?= tr('recipe_add_to_cart') ?></button>
                <button class="pf-btn btn-secondary" onclick="enterCookMode()">👨‍🍳 <?= tr('recipe_cook_mode') ?></button>
                <button class="pf-btn btn-secondary" onclick="window.location.href='?tab=recipes'">✏️ <?= tr('btn_edit') ?></button>
            </div>
        </div>
    </div>

    <div class="recipe-grid-layout">
        <!-- INGREDIENTS PANELS -->
        <aside class="recipe-panel">
            <h2 class="panel-title"><?= tr('recipe_ingredients') ?></h2>
            
            <div class="servings-adjuster">
                <button class="servings-btn" onclick="updateServings(-1)">-</button>
                <span class="servings-val"><span id="servings-count"><?= $recipe['base_servings'] ?></span> <?= strtolower(tr('recipe_servings')) ?></span>
                <button class="servings-btn" onclick="updateServings(1)">+</button>
            </div>

            <ul class="ingredient-list">
                <?php foreach($ingredients as$ing): ?>
                <li class="ingredient-item" onclick="toggleIng(this)">
                    <input type="checkbox" class="ingredient-checkbox" onclick="event.stopPropagation(); toggleIng(this.parentElement)">
                    <div>
                        <?php if($ing['quantity'] > 0): ?>
                            <span class="qty" data-base="<?= $ing['quantity'] ?>"><?= $ing['quantity'] ?></span>
                            <span class="unit"><?= htmlspecialchars($ing['unit']) ?></span>
                        <?php endif; ?>
                        <span class="ing"><?= htmlspecialchars($ing['name']) ?></span>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            
            <?php if (!empty($recipe['equipment'])): ?>
            <h2 class="panel-title" style="margin-top: 30px;"><?= tr('recipe_equipment') ?></h2>
            <ul class="ingredient-list">
                <?php $eqs = json_decode($recipe['equipment'], true) ?: []; foreach($eqs as$eq): ?>
                    <li class="ingredient-item"><span class="ing"><?= htmlspecialchars($eq) ?></span></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </aside>

        <!-- STEPS PANEL -->
        <main class="steps-container">
            <h2 class="panel-title"><?= tr('recipe_steps') ?></h2>
            <?php foreach($instructions as $idx =>$step): ?>
            <div class="step-item" onclick="this.classList.toggle('step-done')">
                <div class="step-number"><?= $idx + 1 ?></div>
                <div class="step-content">
                    <?php if($step['step_title']): ?><h4><?= htmlspecialchars($step['step_title']) ?></h4><?php endif; ?>
                    <p><?= nl2br(htmlspecialchars($step['instruction'])) ?></p>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if ($recipe['chef_tip']): ?>
            <div class="chef-tip">
                <h4>💡 <?= tr('recipe_chef_tip') ?></h4>
                <p><?= nl2br(htmlspecialchars($recipe['chef_tip'])) ?></p>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<script>
let baseServings = <?= $recipe['base_servings'] ?>;
let currentServings = baseServings;

function updateServings(delta) {
    if (currentServings + delta < 1) return;
    currentServings += delta;
    document.getElementById('servings-count').innerText = currentServings;

    const ratio = currentServings / baseServings;
    document.querySelectorAll('.qty').forEach(el => {
        const baseVal = parseFloat(el.getAttribute('data-base'));
        const newVal = baseVal * ratio;
        el.innerText = Number.isInteger(newVal) ? newVal : newVal.toFixed(1).replace('.0', '');
    });
}

function toggleIng(itemEl) {
    itemEl.classList.toggle('checked');
    const cb = itemEl.querySelector('.ingredient-checkbox');
    if (cb) cb.checked = itemEl.classList.contains('checked');
}

function enterCookMode() {
    document.querySelector('.recipe-hero').style.display = 'none';
    document.querySelector('.pf-header').style.display = 'none';
    document.querySelector('.recipe-grid-layout').style.gridTemplateColumns = '1fr';
    // Activation native du Wakelock si supporté
    if ('wakeLock' in navigator) navigator.wakeLock.request('screen');
}

async function addToGroceries() {
    // Collecte des ingrédients non cochés
    let itemsToAdd = [];
    document.querySelectorAll('.ingredient-item:not(.checked)').forEach(el => {
        let name = el.querySelector('.ing').innerText;
        let qty = el.querySelector('.qty') ? el.querySelector('.qty').innerText : '';
        let unit = el.querySelector('.unit') ? el.querySelector('.unit').innerText : '';
        itemsToAdd.push(`${qty} ${unit} ${name}`.trim());
    });

    if (itemsToAdd.length === 0) {
        alert("Tous les ingrédients sont cochés (déjà possédés).");
        return;
    }

    // Appels séquentiels à l'API Groceries existante
    try {
        for (let item of itemsToAdd) {
            await pachaFetch('/modules/groceries/api.php?action=items', {
                method: 'POST', body: JSON.stringify({ label: item })
            });
        }
        if(window.showToast) showToast("Ingrédients ajoutés aux courses !", "success");
    } catch(err) {
        alert("Erreur lors de l'ajout.");
    }
}
</script>