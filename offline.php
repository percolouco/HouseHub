<?php
// offline.php
require_once __DIR__ . '/includes/i18n.php';

$pageTitle = tr('offline_title');
$activePage = "offline";
require __DIR__ . '/header.php';
?>

<div class="pf-container" style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 60vh; text-align: center; padding: 20px;">
    <div style="font-size: 5rem; margin-bottom: 20px; filter: grayscale(1); opacity: 0.7;">📡</div>
    <h1 style="color: var(--text-main); margin-bottom: 10px;"><?= tr('offline_heading') ?></h1>
    <p style="color: var(--text-muted); font-size: 1.1rem; max-width: 400px; margin-bottom: 30px;">
        <?= tr('offline_message') ?>
    </p>
    <button onclick="window.location.reload();" class="pf-btn">
        🔄 <?= tr('offline_retry_btn') ?>
    </button>
</div>

<?php require __DIR__ . '/footer.php'; ?>