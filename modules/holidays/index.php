<?php
// modules/holidays/index.php

// ------------------------------------------------------------------
// 1. LOGIQUE PHP : RÉCUPÉRATION DES DONNÉES
// ------------------------------------------------------------------

if (!function_exists('hol_q')) {
    function hol_q(PDO $pdo, string $sql, array $params = []): array {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}

$ideaId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isDetailView = ($ideaId > 0);
$currentIdea = null; // Stockera les données si on est en vue détail

// Préparation des variables par défaut pour éviter les erreurs "undefined variable"
$transport = []; $lodging = []; $acts = []; $budget = [];
$fixedTotal = 0; $ppTotal = 0;
$planned = []; $ideas = []; $archived = [];

if ($isDetailView) {
    // --- MODE DÉTAIL ---
    
    // 1. Récupérer l'idée principale
    $rows = hol_q($pdo, "SELECT * FROM pf_holidays_ideas WHERE id = ?", [$ideaId]);
    $currentIdea = $rows[0] ?? null;

    if ($currentIdea) {
        // 2. Récupérer les données pour la CARTE (un seul point)
        // Le JS filtrera lat/lng invalides
        $mapIdeas = hol_q($pdo, "
            SELECT id, title, country, region, city,
                   CAST(lat AS DECIMAL(9,6)) AS lat,
                   CAST(lng AS DECIMAL(9,6)) AS lng,
                   status, desired_start_date, desired_end_date
            FROM pf_holidays_ideas
            WHERE id = ?
        ", [$ideaId]);

        // 3. Récupérer les sous-éléments (Transport, Logement, etc.)
        $transport = hol_q($pdo, "SELECT * FROM pf_holidays_transport WHERE idea_id = ? ORDER BY created_at DESC", [$ideaId]);
        $lodging   = hol_q($pdo, "SELECT * FROM pf_holidays_lodging WHERE idea_id = ? ORDER BY created_at DESC", [$ideaId]);
        $acts      = hol_q($pdo, "SELECT * FROM pf_holidays_activities WHERE idea_id = ? ORDER BY created_at DESC", [$ideaId]);
        $budget    = hol_q($pdo, "SELECT category, label, amount, per_person FROM pf_holidays_budget_items WHERE idea_id = ? ORDER BY created_at DESC", [$ideaId]);
        
        // 4. Calculs totaux budget
        $sumRow = hol_q($pdo, "
            SELECT 
                SUM(CASE WHEN per_person=0 THEN amount ELSE 0 END) AS fixed_total,
                SUM(CASE WHEN per_person=1 THEN amount ELSE 0 END) AS per_person_total
            FROM pf_holidays_budget_items WHERE idea_id = ?
        ", [$ideaId])[0] ?? ['fixed_total' => 0, 'per_person_total' => 0];
        
        $fixedTotal = (float)($sumRow['fixed_total'] ?? 0);
        $ppTotal    = (float)($sumRow['per_person_total'] ?? 0);
    }

} else {
    // --- MODE LISTE ---

    // 1. Données pour la CARTE (tous les points valides)
    $mapIdeas = hol_q($pdo, "
        SELECT id, title, country, region, city,
               CAST(lat AS DECIMAL(9,6)) AS lat,
               CAST(lng AS DECIMAL(9,6)) AS lng,
               status, desired_start_date, desired_end_date
        FROM pf_holidays_ideas
        WHERE status IN ('draft','shortlist','favorite','planned')
          AND lat IS NOT NULL
          AND lng IS NOT NULL
    ");

    // 2. Récupérer les listes
    $planned = hol_q($pdo, "
        SELECT * FROM pf_holidays_ideas
        WHERE status = 'planned'
        ORDER BY COALESCE(desired_start_date, created_at) DESC
    ");
    $ideas = hol_q($pdo, "
        SELECT * FROM pf_holidays_ideas
        WHERE status IN ('draft','shortlist','favorite')
        ORDER BY FIELD(status,'favorite','shortlist','draft'), created_at DESC
    ");
    $archived = hol_q($pdo, "
        SELECT * FROM pf_holidays_ideas
        WHERE status = 'archived'
        ORDER BY updated_at DESC
    ");
}
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

<?php if ($isDetailView): ?>

    <?php if (!$currentIdea): ?>
        <div class="pf-holidays__titlebar">
            <h1>Idée introuvable</h1>
            <div class="hol-title-actions">
                <a class="btn" href="/holidays.php">← Retour à la liste</a>
            </div>
        </div>
        <p>Cette idée de vacances n'existe pas ou a été supprimée.</p>
    <?php else: ?>
        <div class="pf-holidays__titlebar">
            <h1><?= htmlspecialchars($currentIdea['title']) ?></h1>
            <div class="hol-title-actions">
                <a class="btn" href="/holidays.php">← Retour</a>
                <button class="btn btn-edit" id="hol-edit-open" data-edit-id="<?= (int)$ideaId ?>">Éditer</button>
                <button class="btn btn-delete" id="hol-delete" data-del-id="<?= (int)$ideaId ?>">Supprimer</button>
                <button class="hol-map-toggle" id="hol-map-open">🌍 Carte</button>
            </div>
        </div>

        <p class="hol-idea-meta">
            <?= htmlspecialchars(trim(($currentIdea['city'] ? $currentIdea['city'] . ', ' : '') . ($currentIdea['region'] ? $currentIdea['region'] . ', ' : '') . ($currentIdea['country'] ?? ''))) ?>
            <?php if (!empty($currentIdea['desired_start_date'])): ?>
                • Dates: <?= htmlspecialchars($currentIdea['desired_start_date']) ?><?= !empty($currentIdea['desired_end_date']) ? ' → ' . htmlspecialchars($currentIdea['desired_end_date']) : '' ?>
            <?php elseif (!empty($currentIdea['season_hint'])): ?>
                • Saison: <?= htmlspecialchars($currentIdea['season_hint']) ?>
            <?php endif; ?>
            <?php if (!empty($currentIdea['ideal_days'])): ?>
                • Durée idéale: <?= (int)$currentIdea['ideal_days'] ?> j
            <?php endif; ?>
            • Statut: <strong><?= htmlspecialchars($currentIdea['status']) ?></strong>
        </p>

        <div class="hol-grid">
            <section class="pf-section pf-section--panel">
                <h2>Transport</h2>
                <form method="post" action="/modules/holidays/save.php" class="hol-inline-form">
                    <input type="hidden" name="action" value="add_transport">
                    <input type="hidden" name="idea_id" value="<?= (int)$ideaId ?>">
                    <select name="mode" required>
                        <option value="TRAIN">TRAIN</option><option value="PLANE">PLANE</option>
                        <option value="CAR">CAR</option><option value="BUS">BUS</option><option value="BOAT">BOAT</option>
                    </select>
                    <input type="number" name="duration_min" min="0" placeholder="Durée (min)">
                    <input type="number" step="0.01" name="cost" placeholder="Coût (€)">
                    <input type="number" step="0.01" name="co2_kg" placeholder="CO₂ (kg)">
                    <input type="url" name="link" placeholder="Lien">
                    <input type="text" name="notes" placeholder="Notes">
                    <button type="submit" class="btn">Ajouter</button>
                </form>
                <ul class="hol-list">
                    <?php foreach ($transport as $t): ?>
                        <li><?= htmlspecialchars($t['mode']) ?>
                            <?= $t['duration_min'] !== null ? ' • ' . (int)$t['duration_min'] . ' min' : '' ?>
                            <?= $t['cost'] !== null ? ' • ' . number_format((float)$t['cost'], 0, ',', ' ') . ' €' : '' ?>
                            <?php if (!empty($t['link'])): ?> • <a href="<?= htmlspecialchars($t['link']) ?>" target="_blank" rel="noopener">🔗</a><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>

            <section class="pf-section pf-section--panel">
                <h2>Hébergement</h2>
                <form method="post" action="/modules/holidays/save.php" class="hol-inline-form">
                    <input type="hidden" name="action" value="add_lodging">
                    <input type="hidden" name="idea_id" value="<?= (int)$ideaId ?>">
                    <select name="type" required>
                        <option value="HOTEL">HOTEL</option><option value="APT">APT</option>
                        <option value="HOUSE">HOUSE</option><option value="CAMPING">CAMPING</option><option value="OTHER">OTHER</option>
                    </select>
                    <input type="text" name="location_text" placeholder="Localisation">
                    <input type="number" step="0.01" name="price_per_n" placeholder="€ / nuit">
                    <input type="number" name="nights" placeholder="Nuits">
                    <label><input type="checkbox" name="free_cancel" value="1"> Annulation gratuite</label>
                    <label><input type="checkbox" name="family_friendly" value="1" checked> Family-friendly</label>
                    <input type="url" name="link" placeholder="Lien">
                    <input type="text" name="notes" placeholder="Notes">
                    <button type="submit" class="btn">Ajouter</button>
                </form>
                <ul class="hol-list">
                    <?php foreach ($lodging as $l): ?>
                        <li><?= htmlspecialchars($l['type']) ?>
                            <?= !empty($l['location_text']) ? ' • ' . htmlspecialchars($l['location_text']) : '' ?>
                            <?= $l['price_per_n'] !== null ? ' • ' . number_format((float)$l['price_per_n'], 0, ',', ' ') . ' €/nuit' : '' ?>
                            <?= $l['nights'] !== null ? ' × ' . (int)$l['nights'] . 'n' : '' ?>
                            <?php if (!empty($l['link'])): ?> • <a href="<?= htmlspecialchars($l['link']) ?>" target="_blank" rel="noopener">🔗</a><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>

            <section class="pf-section pf-section--panel">
                <h2>Activités</h2>
                <form method="post" action="/modules/holidays/save.php" class="hol-inline-form">
                    <input type="hidden" name="action" value="add_activity">
                    <input type="hidden" name="idea_id" value="<?= (int)$ideaId ?>">
                    <input type="text" name="name" placeholder="Nom" required>
                    <input type="text" name="kind" placeholder="Type (ex: PARK)">
                    <input type="number" step="0.01" name="cost_est" placeholder="€ estimé">
                    <label><input type="checkbox" name="need_booking" value="1"> Réservation</label>
                    <select name="weather">
                        <option value="ANY">ANY</option><option value="GOOD">GOOD</option><option value="RAIN">RAIN</option>
                    </select>
                    <input type="url" name="link" placeholder="Lien">
                    <input type="text" name="notes" placeholder="Notes">
                    <button type="submit" class="btn">Ajouter</button>
                </form>
                <ul class="hol-list">
                    <?php foreach ($acts as $a): ?>
                        <li><?= htmlspecialchars($a['name']) ?><?= !empty($a['kind']) ? ' (' . htmlspecialchars($a['kind']) . ')' : '' ?>
                            <?= $a['cost_est'] !== null ? ' • ' . number_format((float)$a['cost_est'], 0, ',', ' ') . ' €' : '' ?>
                            <?= !empty($a['need_booking']) ? ' • Réservation requise' : '' ?>
                            <?php if (!empty($a['link'])): ?> • <a href="<?= htmlspecialchars($a['link']) ?>" target="_blank" rel="noopener">🔗</a><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>

            <section class="pf-section pf-section--panel">
                <h2>Budget</h2>
                <form method="post" action="/modules/holidays/save.php" class="hol-inline-form">
                    <input type="hidden" name="action" value="add_budget">
                    <input type="hidden" name="idea_id" value="<?= (int)$ideaId ?>">
                    <select name="category" required>
                        <option>TRANSPORT</option><option>LODGING</option><option>FOOD</option>
                        <option>ACTIVITIES</option><option>LOCAL</option><option>INSURANCE</option><option>VISAS</option><option>OTHER</option>
                    </select>
                    <input type="text" name="label" placeholder="Label (facultatif)">
                    <input type="number" step="0.01" name="amount" placeholder="Montant (€)" required>
                    <label><input type="checkbox" name="per_person" value="1"> Par personne</label>
                    <button type="submit" class="btn">Ajouter</button>
                </form>

                <div class="hol-budget-summary">
                    <strong>Fixe:</strong> <?= number_format($fixedTotal, 0, ',', ' ') ?> €
                    • <strong>Par personne:</strong> <?= number_format($ppTotal, 0, ',', ' ') ?> €
                </div>

                <ul class="hol-list">
                    <?php foreach ($budget as $b): ?>
                        <li>[<?= htmlspecialchars($b['category']) ?>] <?= htmlspecialchars($b['label'] ?? '') ?> —
                            <?= number_format((float)$b['amount'], 0, ',', ' ') ?> €<?= $b['per_person'] ? ' /pers.' : '' ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        </div>
    <?php endif; ?>

<?php else: ?>
    <div class="pf-holidays__titlebar">
        <h1>Idées de vacances</h1>
        <div class="hol-title-actions">
            <button class="hol-add-btn" id="hol-add-open">+ Ajouter une idée</button>
            <button class="hol-map-toggle" id="hol-map-open">🌍 Carte</button>
        </div>
    </div>

    <section class="pf-section pf-section--panel">
        <h2>Vacances planifiées</h2>
        <p class="cl-legend">Dates souhaitées, prêtes à être réservées.</p>
        <div class="hol-ideas-grid">
            <?php foreach ($planned as $it): ?>
                <div class="hol-idea-card" data-id="<?= (int)$it['id'] ?>">
                    <div class="hol-idea-card__head">
                        <h3><?= htmlspecialchars($it['title']) ?></h3>
                        <span class="hol-status hol-status--planned">planned</span>
                    </div>
                    <p class="hol-idea-meta">
                        <?= htmlspecialchars(trim(($it['city'] ? $it['city'] . ', ' : '') . ($it['region'] ? $it['region'] . ', ' : '') . ($it['country'] ?? ''))) ?>
                        <?php if (!empty($it['desired_start_date'])): ?>
                            • Dates: <?= htmlspecialchars($it['desired_start_date']) ?><?= !empty($it['desired_end_date']) ? ' → ' . htmlspecialchars($it['desired_end_date']) : '' ?>
                        <?php endif; ?>
                        <?php if (!empty($it['ideal_days'])): ?>
                            • Durée idéale: <?= (int)$it['ideal_days'] ?> j
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($it['notes'])): ?>
                        <p class="hol-notes"><?= nl2br(htmlspecialchars(mb_strimwidth($it['notes'], 0, 160, '…'))) ?></p>
                    <?php endif; ?>
                    <div class="hol-card-actions">
                        <a class="btn" href="/holidays.php?id=<?= (int)$it['id'] ?>">Ouvrir</a>
                        <button class="btn btn-edit" data-edit-id="<?= (int)$it['id'] ?>">Éditer</button>
                        <button class="btn btn-delete" data-del-id="<?= (int)$it['id'] ?>">Supprimer</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="pf-section pf-section--panel">
        <h2>Idées</h2>
        <p class="cl-legend">Brouillons, favoris, shortlist.</p>
        <div class="hol-ideas-grid">
            <?php foreach ($ideas as $it): ?>
                <div class="hol-idea-card" data-id="<?= (int)$it['id'] ?>">
                    <div class="hol-idea-card__head">
                        <h3><?= htmlspecialchars($it['title']) ?></h3>
                        <span class="hol-status hol-status--<?= htmlspecialchars($it['status']) ?>"><?= htmlspecialchars($it['status']) ?></span>
                    </div>
                    <p class="hol-idea-meta">
                        <?= htmlspecialchars(trim(($it['city'] ? $it['city'] . ', ' : '') . ($it['region'] ? $it['region'] . ', ' : '') . ($it['country'] ?? ''))) ?>
                        <?php if (!empty($it['desired_start_date'])): ?>
                            • Dates: <?= htmlspecialchars($it['desired_start_date']) ?><?= !empty($it['desired_end_date']) ? ' → ' . htmlspecialchars($it['desired_end_date']) : '' ?>
                        <?php elseif (!empty($it['season_hint'])): ?>
                            • Saison: <?= htmlspecialchars($it['season_hint']) ?>
                        <?php endif; ?>
                        <?php if (!empty($it['ideal_days'])): ?>
                            • Durée idéale: <?= (int)$it['ideal_days'] ?> j
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($it['notes'])): ?>
                        <p class="hol-notes"><?= nl2br(htmlspecialchars(mb_strimwidth($it['notes'], 0, 160, '…'))) ?></p>
                    <?php endif; ?>
                    <div class="hol-card-actions">
                        <a class="btn" href="/holidays.php?id=<?= (int)$it['id'] ?>">Ouvrir</a>
                        <button class="btn btn-edit" data-edit-id="<?= (int)$it['id'] ?>">Éditer</button>
                        <button class="btn btn-delete" data-del-id="<?= (int)$it['id'] ?>">Supprimer</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="pf-section pf-section--panel">
        <h2>Archivées</h2>
        <div class="hol-ideas-grid hol-ideas-grid--archived">
            <?php foreach ($archived as $it): ?>
                <div class="hol-idea-card hol-idea-card--archived" data-id="<?= (int)$it['id'] ?>">
                    <h3><?= htmlspecialchars($it['title']) ?></h3>
                    <div class="hol-card-actions">
                        <button class="btn btn-edit" data-edit-id="<?= (int)$it['id'] ?>">Éditer</button>
                        <button class="btn btn-delete" data-del-id="<?= (int)$it['id'] ?>">Supprimer</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="hol-modal" id="hol-add-modal" aria-hidden="true">
        <div class="hol-backdrop"></div>
        <div class="hol-dialog" role="dialog" aria-modal="true" aria-labelledby="hol-add-title">
            <form method="post" action="/modules/holidays/save.php" class="hol-form">
                <h3 id="hol-add-title">Ajouter une idée</h3>
                <input type="hidden" name="action" value="create_idea">

                <label>Titre <input type="text" name="title" required></label>

                <div class="hol-inline">
                    <label>Pays   <input type="text" name="country"></label>
                    <label>Région <input type="text" name="region"></label>
                    <label>Ville  <input type="text" name="city" placeholder="Pour la carte"></label>
                </div>

                <div class="hol-inline">
                    <label>Lat <input type="number" step="0.000001" name="lat" placeholder="41.385064"></label>
                    <label>Lng <input type="number" step="0.000001" name="lng" placeholder="2.173404"></label>
                    <button type="button" class="btn hol-geocode-btn" data-scope="add">Géocoder</button>
                </div>

                <div class="hol-inline">
                    <label>Dates souhaitées (début) <input type="date" name="desired_start_date"></label>
                    <label>Dates souhaitées (fin)   <input type="date" name="desired_end_date"></label>
                </div>

                <div class="hol-inline">
                    <label>Saison (facultatif) <input type="text" name="season_hint" placeholder="Mai–Juin"></label>
                    <label>Durée idéale (jours) <input type="number" name="ideal_days" min="1" step="1"></label>
                </div>

                <label>Statut
                    <select name="status">
                        <option value="draft">draft</option>
                        <option value="shortlist">shortlist</option>
                        <option value="favorite">favorite</option>
                        <option value="planned">planned</option>
                        <option value="archived">archived</option>
                    </select>
                </label>

                <label>Notes <textarea name="notes" rows="4" placeholder="Activités phares, contraintes, liens..."></textarea></label>

                <div class="hol-actions">
                    <button type="button" class="hol-cancel">Annuler</button>
                    <button type="submit" class="hol-ok">Créer</button>
                </div>
            </form>
        </div>
    </div>

<?php endif; ?>


<div class="hol-modal" id="hol-edit-modal" aria-hidden="true">
    <div class="hol-backdrop"></div>
    <div class="hol-dialog" role="dialog" aria-modal="true" aria-labelledby="hol-edit-title">
        <form method="post" action="/modules/holidays/save.php" class="hol-form" id="hol-edit-form">
            <h3 id="hol-edit-title">Éditer l’idée</h3>
            <input type="hidden" name="action" value="update_idea">
            <input type="hidden" name="id" id="edit-id">

            <label>Titre <input type="text" name="title" id="edit-title" required></label>

            <div class="hol-inline">
                <label>Pays   <input type="text" name="country" id="edit-country"></label>
                <label>Région <input type="text" name="region" id="edit-region"></label>
                <label>Ville  <input type="text" name="city" id="edit-city"></label>
            </div>

            <div class="hol-inline">
                <label>Lat <input type="number" step="0.000001" name="lat" id="edit-lat"></label>
                <label>Lng <input type="number" step="0.000001" name="lng" id="edit-lng"></label>
                <button type="button" class="btn hol-geocode-btn" data-scope="edit">Géocoder</button>
            </div>

            <div class="hol-inline">
                <label>Début <input type="date" name="desired_start_date" id="edit-start"></label>
                <label>Fin   <input type="date" name="desired_end_date" id="edit-end"></label>
            </div>

            <div class="hol-inline">
                <label>Saison        <input type="text" name="season_hint" id="edit-season"></label>
                <label>Durée idéale  <input type="number" name="ideal_days" id="edit-days" min="1" step="1"></label>
            </div>

            <label>Statut
                <select name="status" id="edit-status">
                    <option value="draft">draft</option>
                    <option value="shortlist">shortlist</option>
                    <option value="favorite">favorite</option>
                    <option value="planned">planned</option>
                    <option value="archived">archived</option>
                </select>
            </label>

            <label>Notes <textarea name="notes" rows="4" id="edit-notes"></textarea></label>

            <div class="hol-actions">
                <button type="button" class="hol-cancel">Annuler</button>
                <button type="submit" class="hol-ok">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<div class="hol-modal" id="hol-map-modal" aria-hidden="true">
    <div class="hol-backdrop"></div>
    <div class="hol-dialog hol-dialog--map" role="dialog" aria-modal="true" aria-labelledby="hol-map-title">
        <div class="hol-map-header">
            <h3 id="hol-map-title">Carte des idées</h3>
            <button class="hol-cancel">Fermer</button>
        </div>
        <div id="hol-map" style="width: 100%; height: calc(100vh - 140px);"></div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    const HOL_MAP_DATA = <?= json_encode($mapIdeas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="/modules/holidays/holidays.js"></script>