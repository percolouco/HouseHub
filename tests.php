<?php
require_once 'includes/auth.php'; 
require_once 'includes/db.php';
require_once 'includes/i18n.php';
require_once 'header.php';
?>

<style>
    /* Styles spécifiques au Laboratoire (Look IDE) */
    .lab-select {
        padding: 6px 32px 6px 12px; /* Espace à droite pour la flèche */
        font-size: 0.85rem;
        font-weight: 600;
        color: #334155;
        background-color: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        cursor: pointer;
        appearance: none; /* Supprime la flèche par défaut du navigateur */
        -webkit-appearance: none;
        -moz-appearance: none;
        /* Ajout d'une flèche SVG sur-mesure */
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 8px center;
        background-size: 14px;
        transition: all 0.2s ease;
        box-shadow: 0 1px 2px rgba(0,0,0,0.02);
    }
    
    .lab-select:hover {
        border-color: #94a3b8;
    }
    
    .lab-select:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .lab-container {
        max-width: 1400px; 
        margin: 0 auto; 
        display: flex; 
        gap: 20px;
        height: 75vh; /* Prend une bonne partie de l'écran */
    }
    .lab-panel {
        flex: 1; 
        display: flex; 
        flex-direction: column;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }
    .lab-header {
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        padding: 10px 15px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }
    .lab-title { margin: 0; font-size: 1.1rem; color: #334155; display: flex; align-items: center; gap: 8px; }
    
    /* Boutons de contrôle minimalistes */
    .lab-controls { display: flex; gap: 8px; }
    .btn-lab {
        padding: 6px 12px;
        font-size: 0.85rem;
        border-radius: 6px;
        cursor: pointer;
        border: none;
        font-weight: 600;
        transition: 0.2s;
        display: flex; align-items: center; gap: 5px;
    }
    .btn-play { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
    .btn-play:hover { background: #dbeafe; }
    .btn-copy { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
    .btn-copy:hover { background: #dcfce7; }

    /* Terminal Console */
    .lab-console {
        flex: 1;
        width: 100%;
        padding: 15px;
        background: #1e1e1e;
        color: #10b981;
        font-family: 'Courier New', Courier, monospace;
        font-size: 0.9rem;
        border: none;
        resize: none;
        outline: none;
    }
</style>

<section class="pf-section lab-container">
    
    <div class="lab-panel">
        <div class="lab-header">
            <h2 class="lab-title">🧪 <?= tr('tests_title') ?></h2>
            <div class="lab-controls" style="display: flex; align-items: center; gap: 10px;">
                <select id="test-selector" class="lab-select">
                    <optgroup label="💰 Module Budget">
                        <option value="budget_suivi">Suivi Mensuel (Dépenses)</option>
                        <option value="budget_prev">Budget Prévisionnel</option>
                        <option value="budget_epargne" disabled>Épargne (Bientôt...)</option>
                        <option value="budget_recap" disabled>Récapitulatif (Bientôt...)</option>
                    </optgroup>
                    <optgroup label="📅 Autres Modules">
                        <option value="calendar" disabled>Calendrier Familial (Bientôt...)</option>
                        <option value="holidays" disabled>Voyages & Roadtrips (Bientôt...)</option>
                        <option value="gifts" disabled>Liste de Cadeaux (Bientôt...)</option>
                    </optgroup>
                </select>
                
                <button id="btn-run-test" class="btn-lab btn-play">▶ Lancer le test</button>
                <button id="btn-test-all" class="btn-lab btn-play" style="opacity: 0.5; cursor: not-allowed;" title="Bientôt disponible">▶ Tout lancer</button>
                <button id="btn-copy-report" class="btn-lab btn-copy">📋 <?= tr('tests_copy_report') ?></button>
            </div>
        </div>
        <textarea id="test-report" class="lab-console" readonly><?= tr('tests_waiting') ?></textarea>
    </div>

    <div class="lab-panel">
        <div class="lab-header" style="background: #f1f5f9; justify-content: center;">
            <span style="font-size: 0.8rem; color: #64748b; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;">Live Preview</span>
        </div>
        <iframe id="test-arena" style="width: 100%; height: 100%; border: none;"></iframe>
    </div>

</section>

<script src="test-engine.js"></script>

<?php require_once 'footer.php'; ?>