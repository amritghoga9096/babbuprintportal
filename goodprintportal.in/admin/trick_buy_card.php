<?php
include('../includes/session.php');
include('../includes/config.php');
include('trick_buy_common.php');
include('../template/ahkweb/header.php');

header('Content-Type: text/html; charset=UTF-8');

$services = trick_get_services();
$trickEnabled = isset($webdata['trick_service_enabled']) ? (int)$webdata['trick_service_enabled'] : 1;

$isAdmin = false;
if (!empty($udata)) {
    $role = strtolower(trim((string)($udata['role'] ?? $udata['type'] ?? '')));
    $isAdmin = ($role === 'admin' || $role === 'super admin' || $role === 'super_admin' || !empty($udata['is_admin']));
}
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Nunito:wght@500;700;800&display=swap" rel="stylesheet">

<style>
body {
  background: #f4f6f8;
  font-family: 'Inter', sans-serif;
  color: #222;
  margin: 0;
  padding: 0;
}

.page-wrapper {
  padding: 22px 28px;
}

.header-row {
  display: flex;
  align-items: center;
  gap: 18px;
  margin-bottom: 12px;
  flex-wrap: wrap;
}

.go-back {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: #6366f1;
  color: #fff;
  padding: 10px 16px;
  border-radius: 8px;
  text-decoration: none;
  font-weight: 600;
  box-shadow: 0 6px 18px rgba(99, 102, 241, 0.18);
  font-size: 15px;
}
.go-back:hover { color: #fff; text-decoration: none; }

.services-wrap {
  width: 100%;
  box-sizing: border-box;
  padding: 12px 8px;
}

.services-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 50px;
  justify-content: flex-start;
  align-items: flex-start;
  padding-top: 12px;
}

.service-item {
  width: 220px;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}

.service-circle {
  width: 200px;
  height: 200px;
  border-radius: 25%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(180deg, #eef2ff, #e0e7ff);
  box-shadow: 0 12px 28px rgba(18, 24, 34, 0.08);
  border: 6px solid rgba(255, 255, 255, 0.55);
  overflow: hidden;
  transition: transform .28s ease, box-shadow .28s ease;
}
.service-circle img {
  width: 150px;
  height: 150px;
  object-fit: contain;
  display: block;
  pointer-events: none;
  user-select: none;
  transition: transform .3s ease;
}
.service-link { text-decoration: none; color: inherit; }
.service-link:hover .service-circle {
  transform: translateY(-10px);
  box-shadow: 0 24px 48px rgba(0, 0, 0, 0.14);
}
.service-link:hover .service-circle img { transform: scale(1.08); }

.service-label {
  margin-top: 16px;
  font-size: 16px;
  font-weight: 600;
  color: #222;
  max-width: 210px;
  line-height: 1.3;
}
.service-price {
  margin-top: 8px;
  font-size: 16px;
  font-weight: 800;
  color: #16a34a;
  font-family: 'Nunito', sans-serif;
}
.service-link:focus .service-circle {
  outline: 3px solid rgba(99, 102, 241, 0.18);
  transform: translateY(-6px);
}

.trick-admin-box {
  margin-top: 40px;
  padding: 18px 20px;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
  background: #ffffff;
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

@media (max-width: 1200px) {
  .service-item { width: 180px; }
  .service-circle { width: 160px; height: 160px; }
  .service-circle img { width: 120px; height: 120px; }
}

@media (max-width: 768px) {
  .page-wrapper { padding: 18px 12px; }
  .services-grid { gap: 24px; justify-content: center; }
  .service-item { width: 46%; max-width: 220px; }
}

@media (max-width: 480px) {
  .header-row { flex-wrap: wrap; }
  .service-item { width: 100%; max-width: none; }
  .service-circle { width: 180px; height: 180px; }
}
</style>

<div class="page-wrapper">
  <div class="header-row">
    <a class="go-back" href="javascript:void(0)" onclick="history.back();">
      <span>&#8592;</span>
      <span>Go Back</span>
    </a>
    <div style="margin-left:8px; color:#555; font-weight:600;">
      Hi, <?php echo htmlspecialchars($udata['name'] ?? 'User'); ?>
    </div>
  </div>

  <div class="services-wrap">
    <div class="d-flex flex-wrap justify-content-between align-items-start mb-3" style="gap:14px;">
      <div>
        <h2 style="font-family:'Nunito', sans-serif; font-weight:800; color:#222; margin:6px 0 4px;">
          TRICK Buy Services
        </h2>
        <p style="color:#e53935; margin-top:0; margin-bottom:0;">
          Remote trick services — Admin works via AnyDesk, delivery on WhatsApp.
        </p>
      </div>
      <a href="trick_buy_list.php" class="btn btn-outline-primary">
        <i class="bi bi-list-ul me-1"></i> My Orders
        <?php $pendingCount = 0;
        if (!empty($udata)) {
            $phone = $udata['phone'] ?? '';
            if ($phone !== '') {
                $r = @mysqli_query($ahk_conn, "SELECT COUNT(*) c FROM trick_orders WHERE status IN ('pending','processing') AND user_phone='".mysqli_real_escape_string($ahk_conn,$phone)."'");
                if ($r && ($rw = mysqli_fetch_assoc($r))) $pendingCount = (int)$rw['c'];
            }
        }
        if ($pendingCount > 0): ?>
          <span class="badge bg-warning text-dark ms-1"><?= $pendingCount ?> Pending</span>
        <?php endif; ?>
      </a>
    </div>

    <?php if ($trickEnabled !== 1): ?>
      <div class="alert alert-warning mt-3"><i class="bi bi-slash-circle me-2"></i> Trick Buy service is temporarily <b>OFF</b>. Admin can enable it from Settings.</div>
    <?php endif; ?>

    <div class="services-grid">
      <?php
      $logo = 'https://goodprintportal.in/trick.png';
      foreach ($services as $k => $svc):
          $fee  = trick_service_fee($k, $webdata);
          $link = ($trickEnabled === 1) ? ('trick_buy.php?svc=' . urlencode($k)) : 'javascript:alert("Trick service temporarily OFF — check back later.");';
          $name = htmlspecialchars($svc['label']);
      ?>
        <div class="service-item">
          <a class="service-link" href="<?= $link ?>" aria-label="<?= $name ?>">
            <div class="service-circle">
              <img src="<?= $logo ?>" alt="<?= $name ?> icon">
            </div>
            <div class="service-label"><?= $name ?></div>
            <div class="service-price">&#8377;<?= number_format((float)$fee, 2) ?></div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ($isAdmin): ?>
      <div class="trick-admin-box">
        <h6 style="font-family:'Nunito',sans-serif;font-weight:800;margin:0 0 12px;">
          <i class="bi bi-shield-lock me-1"></i> Admin Actions
        </h6>
        <div class="d-flex flex-wrap gap-2">
          <a class="btn btn-primary" href="trick_buy_admin.php">
            <i class="bi bi-grid-3x3-gap me-1"></i> View All TRICK Orders
            <?php $count = trick_pending_count($ahk_conn); if ($count > 0): ?>
              <span class="badge bg-warning text-dark ms-2"><?= $count ?> Pending</span>
            <?php endif; ?>
          </a>
          <a class="btn btn-outline-secondary" href="settings.php#trick-buy-settings">
            <i class="bi bi-toggles me-1"></i> Settings (ON/OFF + Prices)
          </a>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include('footers.php'); ?>
