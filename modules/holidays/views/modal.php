<div id="holidayModal" class="pf-modal">
    <div class="pf-modal-content hol-modal-content">
        <h3 id="modalTitle" class="pf-modal-title"><?= tr('hdl_modal_title') ?></h3>
        
        <form action="/modules/holidays/includes/api/save_holiday.php" method="POST" id="holidayForm">
            <input type="hidden" name="id" id="inp_id">
            <input type="hidden" name="action" value="save">

            <div class="form-row">
                <div class="hol-flex-2">
                    <label class="pf-label"><?= tr('hdl_label_name') ?></label>
                    <input type="text" name="title" id="inp_title" class="pf-input" placeholder="<?= tr('hdl_ph_name') ?>" required>
                </div>
                <div class="hol-flex-1">
                    <label class="pf-label"><?= tr('hdl_label_status') ?></label>
                    <select name="status" id="inp_status" class="pf-input">
                        <option value="draft"><?= tr('hdl_status_draft') ?> ✏️</option>
                        <option value="planned"><?= tr('hdl_status_planned') ?> 📅</option>
                        <option value="booked"><?= tr('hdl_status_booked') ?> ✅</option>
                        <option value="passed"><?= tr('hdl_status_passed') ?> 👋</option>
                        <option value="archived"><?= tr('hdl_status_archived') ?> 🗄️</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div>
                    <label class="pf-label"><?= tr('hdl_label_period') ?></label>
                    <input type="text" name="period_hint" id="inp_period" class="pf-input" placeholder="<?= tr('hdl_ph_period') ?>">
                </div>
                <div class="hol-date-range-group">
                    <div class="hol-flex-1">
                        <label class="pf-label"><?= tr('hdl_label_from') ?></label>
                        <input type="date" name="start_date" id="inp_start" class="pf-input">
                    </div>
                    <div class="hol-flex-1">
                        <label class="pf-label"><?= tr('hdl_label_to') ?></label>
                        <input type="date" name="end_date" id="inp_end" class="pf-input">
                    </div>
                </div>
            </div>

            <hr class="hol-divider">

            <div class="hol-columns-wrapper">
                <div class="hol-col">
                    <div class="hol-col-header">
                        <h4 class="hol-cat-transport">🚗 <?= tr('hdl_cat_transport') ?></h4>
                        <button type="button" class="btn-add-item" onclick="addItem('transport')" title="<?= tr('hdl_add_transport') ?>">＋</button>
                    </div>
                    <div id="list_transport" class="dynamic-list"></div>
                </div>

                <div class="hol-col">
                    <div class="hol-col-header">
                        <h4 class="hol-cat-accommodation">🏨 <?= tr('hdl_cat_accommodation') ?></h4>
                        <button type="button" class="btn-add-item" onclick="addItem('accommodation')" title="<?= tr('hdl_add_accommodation') ?>">＋</button>
                    </div>
                    <div id="list_accommodation" class="dynamic-list"></div>
                </div>

                <div class="hol-col">
                    <div class="hol-col-header">
                        <h4 class="hol-cat-activity">🎫 <?= tr('hdl_cat_activity') ?></h4>
                        <button type="button" class="btn-add-item" onclick="addItem('activity')" title="<?= tr('hdl_add_activity') ?>">＋</button>
                    </div>
                    <div id="list_activity" class="dynamic-list"></div>
                </div>
            </div>

            <hr class="hol-divider">

            <div class="form-row">
                <div>
                    <label class="pf-label">🍔 <?= tr('hdl_label_budget_food') ?></label>
                    <input type="number" step="0.01" name="budget_food" id="inp_food" class="pf-input" placeholder="0.00">
                </div>
                <div>
                    <label class="pf-label">🎁 <?= tr('hdl_label_budget_extras') ?></label>
                    <input type="number" step="0.01" name="budget_extra" id="inp_extra" class="pf-input" placeholder="0.00">
                </div>
            </div>

            <div class="form-group">
                <label class="pf-label"><?= tr('hdl_label_notes') ?></label>
                <textarea name="notes" id="inp_notes" class="pf-input" rows="2" placeholder="<?= tr('hdl_ph_notes') ?>"></textarea>
            </div>

            <div class="modal-footer">
                <button type="button" onclick="deleteHoliday()" id="btn_delete" class="pf-btn btn-secondary hol-btn-delete"><?= tr('btn_delete') ?></button>
                <button type="button" onclick="closeHolidayModal()" class="pf-btn btn-secondary"><?= tr('btn_cancel') ?></button>
                <button type="submit" class="pf-btn"><?= tr('btn_save') ?></button>
            </div>
        </form>
    </div>
</div>