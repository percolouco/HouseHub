<?php
require __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/meta_db.php';

$family = $meta_pdo->prepare("SELECT * FROM families WHERE id = ?");
$family->execute([$_SESSION['user']['family_id']]);
$family = $family->fetch();

$members = $meta_pdo->prepare("SELECT display_name, username, created_at FROM users WHERE family_id = ? ORDER BY id");
$members->execute([$_SESSION['user']['family_id']]);
$members = $members->fetchAll();

$pageTitle = "Invitation — HouseHub";
$activePage = "invite";
require __DIR__ . '/header.php';
?>

<div class="pf-container" style="max-width:600px;margin:40px auto;padding:0 16px">

  <h1 style="font-size:1.4rem;font-weight:700;margin-bottom:8px">👨‍👩‍👧 Votre espace familial</h1>
  <p style="color:#64748b;margin-bottom:32px">Partagez le code ci-dessous pour inviter quelqu'un dans votre espace.</p>

  <div style="background:#f8fafc;border:2px dashed #cbd5e1;border-radius:12px;padding:24px;text-align:center;margin-bottom:32px">
    <p style="font-size:0.85rem;color:#64748b;margin-bottom:8px">Famille : <strong><?= htmlspecialchars($family['name']) ?></strong></p>
    <p style="font-size:0.8rem;color:#94a3b8;margin-bottom:16px">Code d'invitation</p>
    <code id="invite-code" style="font-size:1.4rem;font-weight:700;letter-spacing:2px;color:#1e40af;cursor:pointer"
      onclick="copyCode()" title="Cliquer pour copier">
      <?= htmlspecialchars($family['invite_code']) ?>
    </code>
    <p id="copy-msg" style="color:#16a34a;font-size:0.85rem;margin-top:8px;opacity:0;transition:opacity .3s">✓ Copié !</p>
    <p style="font-size:0.8rem;color:#94a3b8;margin-top:12px">
      Lien d'inscription :
      <a href="/register.php" style="color:var(--primary)"><?= $_SERVER['HTTP_HOST'] ?>/register.php</a>
    </p>
  </div>

  <h2 style="font-size:1.1rem;font-weight:600;margin-bottom:16px">Membres de l'espace (<?= count($members) ?>)</h2>
  <div style="display:flex;flex-direction:column;gap:8px">
    <?php foreach ($members as $m): ?>
    <div style="background:white;border:1px solid #e2e8f0;border-radius:8px;padding:12px 16px;display:flex;justify-content:space-between;align-items:center">
      <div>
        <strong><?= htmlspecialchars($m['display_name']) ?></strong>
        <span style="color:#94a3b8;font-size:0.85rem;margin-left:8px">@<?= htmlspecialchars($m['username']) ?></span>
      </div>
      <span style="font-size:0.8rem;color:#94a3b8"><?= date('d/m/Y', strtotime($m['created_at'])) ?></span>
    </div>
    <?php endforeach; ?>
  </div>

</div>

<script>
function copyCode() {
  const code = document.getElementById('invite-code').textContent.trim();
  navigator.clipboard.writeText(code).then(() => {
    const msg = document.getElementById('copy-msg');
    msg.style.opacity = '1';
    setTimeout(() => msg.style.opacity = '0', 2000);
  });
}
</script>

<?php require __DIR__ . '/footer.php'; ?>
