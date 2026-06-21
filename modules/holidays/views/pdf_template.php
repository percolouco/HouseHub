<?php
// Calcul du budget global pour la page de synthèse
$totalGeneral = $holiday['budget_food'] + $holiday['budget_extra'];
foreach ($generalItems as $gi) {
    $totalGeneral += $gi['amount'];
}
$totalSteps = 0;
foreach ($steps as $step) {
    $totalSteps += $step['total_amount'];
}
$grandTotal = $totalGeneral + $totalSteps;
?>

<div style="display: none;">
    <div id="travelBookTemplate" style="padding: 40px; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #1e293b; background: white; width: 100%; max-width: 800px; margin: 0 auto;">
        
        <div style="text-align: center; padding-top: 50px; margin-bottom: 60px;">
            <div style="font-size: 4rem; margin-bottom: 20px;">🗺️</div>
            <h1 style="font-size: 2.5rem; color: #0f172a; margin-bottom: 10px;"><?= htmlspecialchars($holiday['title'] ?? $holiday['name'] ?? 'Mon Voyage') ?></h1>
            <h2 style="font-size: 1.5rem; color: #64748b; font-weight: normal; margin-top: 0;">
                <?php if (!empty($holiday['start_date']) && !empty($holiday['end_date'])): ?>
                    Du <?= date('d/m/Y', strtotime($holiday['start_date'])) ?> au <?= date('d/m/Y', strtotime($holiday['end_date'])) ?>
                <?php else: ?>
                    Dates à définir
                <?php endif; ?>
            </h2>
        </div>

        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 40px; page-break-inside: avoid;">
            <h3 style="margin-top: 0; color: #0f172a; border-bottom: 2px solid #cbd5e1; padding-bottom: 10px;">💰 Budget Prévisionnel : <?= number_format($grandTotal, 2, ',', ' ') ?> €</h3>
            <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                <tr>
                    <td style="padding: 8px 0; border-bottom: 1px solid #e2e8f0; color: #475569;">Frais Généraux (Vols, Locations globales...)</td>
                    <td style="padding: 8px 0; border-bottom: 1px solid #e2e8f0; text-align: right; font-weight: bold;"><?= number_format($totalGeneral, 2, ',', ' ') ?> €</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #475569;">Étapes (Hébergements, Activités...)</td>
                    <td style="padding: 8px 0; text-align: right; font-weight: bold;"><?= number_format($totalSteps, 2, ',', ' ') ?> €</td>
                </tr>
            </table>
        </div>

        <?php if (!empty($generalItems) || $holiday['budget_food'] > 0 || $holiday['budget_extra'] > 0): ?>
        <div style="margin-bottom: 40px; page-break-inside: avoid;">
            <h3 style="color: #0f172a; border-bottom: 2px solid #0ea5e9; padding-bottom: 5px;">🌍 Réservations & Frais Généraux</h3>
            <ul style="list-style-type: none; padding-left: 0; margin-top: 15px;">
                <?php if ($holiday['budget_food'] > 0): ?>
                    <li style="padding: 8px 0; border-bottom: 1px solid #f1f5f9;">🍔 Budget Nourriture : <strong><?= number_format($holiday['budget_food'], 2, ',', ' ') ?> €</strong></li>
                <?php endif; ?>
                <?php if ($holiday['budget_extra'] > 0): ?>
                    <li style="padding: 8px 0; border-bottom: 1px solid #f1f5f9;">🎁 Extras & Souvenirs : <strong><?= number_format($holiday['budget_extra'], 2, ',', ' ') ?> €</strong></li>
                <?php endif; ?>
                <?php foreach ($generalItems as $gi): 
                    $icon = match($gi['category']) { 'transport' => '🚗', 'accommodation' => '🏨', 'activity' => '🎫', default => '🏷️' };
                ?>
                    <li style="padding: 8px 0; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between;">
                        <span><?= $icon ?> <?= htmlspecialchars($gi['name']) ?></span>
                        <span><strong><?= number_format($gi['amount'], 2, ',', ' ') ?> €</strong> <span style="font-size: 0.85em; color: #64748b;"><?= $gi['is_paid'] ? '(Payé ✓)' : '(À payer ⏳)' ?></span></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div style="page-break-before: always;"></div>
        <h2 style="color: #0f172a; font-size: 2rem; border-bottom: 3px solid #0f172a; padding-bottom: 10px; margin-bottom: 30px;">📍 Itinéraire Détaillé</h2>

        <?php foreach ($steps as $index => $step): ?>
            
            <?php if ($index > 0): ?>
                <div style="text-align: center; padding: 10px 0; color: #94a3b8; page-break-inside: avoid;">
                    <div style="border-left: 2px dashed #cbd5e1; height: 25px; margin: 0 auto; width: 2px;"></div>
                    <div style="margin: 5px 0; font-size: 0.9rem;">🚗 <em>En route vers l'étape suivante...</em></div>
                    <div style="border-left: 2px dashed #cbd5e1; height: 25px; margin: 0 auto; width: 2px;"></div>
                </div>
            <?php endif; ?>

            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-left: 6px solid #0ea5e9; border-radius: 8px; padding: 20px; margin-bottom: 0px; page-break-inside: avoid;">
                
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                    <div>
                        <h3 style="margin: 0; font-size: 1.4rem; color: #0f172a;"><?= htmlspecialchars($step['location_name']) ?></h3>
                        <?php if (!empty($step['step_start_date']) && !empty($step['step_end_date'])): ?>
                            <div style="color: #0ea5e9; margin-top: 5px; font-weight: bold; font-size: 0.95rem;">
                                📅 Du <?= date('d/m', strtotime($step['step_start_date'])) ?> au <?= date('d/m', strtotime($step['step_end_date'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div style="text-align: right;">
                        <span style="background: #f1f5f9; padding: 5px 10px; border-radius: 6px; font-weight: bold; color: #0f172a;">
                            <?= number_format($step['total_amount'], 2, ',', ' ') ?> €
                        </span>
                    </div>
                </div>

                <?php $checkpoints = isset($step['items']) ? $step['items'] : []; ?>

                <?php if (!empty($checkpoints)): ?>
                    <div style="margin-top: 15px; border-top: 1px solid #f8fafc; padding-top: 15px;">
                        <h4 style="margin-top: 0; margin-bottom: 10px; color: #475569; font-size: 1rem;">📋 Planning & Activités :</h4>
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                            <?php foreach ($checkpoints as $cp): 
                                $cpIcon = match($cp['category']) { 'transport' => '🚗', 'accommodation' => '🏨', 'activity' => '🎫', default => '🏷️' };
                                // Sécurité sur la date d'activité (item_date ou date)
                                $cpDate = !empty($cp['item_date']) ? $cp['item_date'] : (!empty($cp['date']) ? $cp['date'] : null);
                            ?>
                                <tr>
                                    <td style="padding: 6px 0; border-bottom: 1px solid #f8fafc; color: #64748b; width: 80px;">
                                        <?= $cpDate ? date('d/m', strtotime($cpDate)) : '---' ?>
                                    </td>
                                    <td style="padding: 6px 0; border-bottom: 1px solid #f8fafc; color: #334155;">
                                        <?= $cpIcon ?> <?= htmlspecialchars($cp['name']) ?>
                                    </td>
                                    <td style="padding: 6px 0; border-bottom: 1px solid #f8fafc; text-align: right; font-weight: bold; color: #0f172a;">
                                        <?= number_format($cp['amount'], 2, ',', ' ') ?> €
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                <?php endif; ?>

            </div>
        <?php endforeach; ?>

        <div style="page-break-before: always;"></div>
        <h2 style="color: #0f172a; font-size: 2rem; border-bottom: 3px solid #0f172a; padding-bottom: 10px; margin-bottom: 30px;">📝 Notes & Informations utiles</h2>
        
        <p style="color: #64748b; margin-bottom: 40px; font-style: italic;">Espace réservé pour vos numéros d'urgence, codes de cadenas, adresses locales...</p>
        
        <?php for ($i = 0; $i < 15; $i++): ?>
            <div style="border-bottom: 1px dotted #cbd5e1; height: 35px; width: 100%;"></div>
        <?php endfor; ?>

        <div style="text-align: center; margin-top: 60px; font-size: 0.85rem; color: #94a3b8;">
            Carnet de route généré automatiquement par <strong>HouseHub</strong>. Bon voyage ! ✈️
        </div>

    </div>
</div>