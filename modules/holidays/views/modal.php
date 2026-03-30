<div id="holidayModal" class="pf-modal">
    <div class="pf-modal-content" style="max-width: 800px; width: 95%;">
        <h3 id="modalTitle" class="pf-modal-title">Planifier le voyage</h3>
        
        <form action="/modules/holidays/includes/api/save_holiday.php" method="POST" id="holidayForm">
            <input type="hidden" name="id" id="inp_id">
            <input type="hidden" name="action" value="save">

            <div class="form-row">
                <div style="flex: 2;">
                    <label class="pf-label">Nom du voyage</label>
                    <input type="text" name="title" id="inp_title" class="pf-input" placeholder="Ex: Octobre - Portugal" required>
                </div>
                <div style="flex: 1;">
                    <label class="pf-label">Statut</label>
                    <select name="status" id="inp_status" class="pf-input">
                        <option value="draft">Brouillon ✏️</option>
                        <option value="planned">Planifié 📅</option>
                        <option value="booked">Réservé ✅</option>
                        <option value="passed">Passé 👋</option>
                        <option value="archived">Archivé 🗄️</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div>
                    <label class="pf-label">Période (Texte libre)</label>
                    <input type="text" name="period_hint" id="inp_period" class="pf-input" placeholder="Ex: Octobre 2026">
                </div>
                <div style="display:flex; gap:10px;">
                    <div style="flex:1">
                        <label class="pf-label">Du</label>
                        <input type="date" name="start_date" id="inp_start" class="pf-input" lang="fr">
                    </div>
                    <div style="flex:1">
                        <label class="pf-label">Au</label>
                        <input type="date" name="end_date" id="inp_end" class="pf-input" lang="fr">
                    </div>
                </div>
            </div>

            <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;">

            <div class="hol-columns-wrapper">
                <div class="hol-col">
                    <div class="hol-col-header">
                        <h4 style="color:#2563eb;">🚗 Transport</h4>
                        <button type="button" class="btn-add-item" onclick="addItem('transport')" title="Ajouter un transport">＋</button>
                    </div>
                    <div id="list_transport" class="dynamic-list"></div>
                </div>

                <div class="hol-col">
                    <div class="hol-col-header">
                        <h4 style="color:#059669;">🏨 Hébergement</h4>
                        <button type="button" class="btn-add-item" onclick="addItem('accommodation')" title="Ajouter un hébergement">＋</button>
                    </div>
                    <div id="list_accommodation" class="dynamic-list"></div>
                </div>

                <div class="hol-col">
                    <div class="hol-col-header">
                        <h4 style="color:#d97706;">🎫 Activité</h4>
                        <button type="button" class="btn-add-item" onclick="addItem('activity')" title="Ajouter une activité">＋</button>
                    </div>
                    <div id="list_activity" class="dynamic-list"></div>
                </div>
            </div>

            <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;">

            <div class="form-row">
                <div>
                    <label class="pf-label">🍔 Budget Food & Bev (€)</label>
                    <input type="number" step="0.01" name="budget_food" id="inp_food" class="pf-input" placeholder="0.00">
                </div>
                <div>
                    <label class="pf-label">🎁 Budget Extras (€)</label>
                    <input type="number" step="0.01" name="budget_extra" id="inp_extra" class="pf-input" placeholder="0.00">
                </div>
            </div>

            <div class="form-group">
                <label class="pf-label">Notes</label>
                <textarea name="notes" id="inp_notes" class="pf-input" rows="2" placeholder="Idées en vrac..."></textarea>
            </div>

            <div class="modal-footer">
                <button type="button" onclick="deleteHoliday()" id="btn_delete" class="pf-btn btn-secondary" style="color:#ef4444; border-color:#fca5a5; margin-right:auto; display:none;">Supprimer</button>
                <button type="button" onclick="closeHolidayModal()" class="pf-btn btn-secondary">Annuler</button>
                <button type="submit" class="pf-btn">Enregistrer</button>
            </div>
        </form>
    </div>
</div>