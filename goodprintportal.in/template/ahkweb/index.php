<?php 
include('header.php');

// UTF-8 safe output
header('Content-Type: text/html; charset=UTF-8');

// ================== NOTIFICATION ==================
$query = "SELECT * FROM notification WHERE id='1'";
$res = mysqli_query($ahk_conn, $query);
$row = mysqli_fetch_assoc($res);
$message = $row ? $row['message'] : "No message found";

// ================== DEPOSIT NOTICE WHATSAPP NUMBER + PENDING CHECK ==================
$_depositSettings = fetchRow("SELECT * FROM settings WHERE id=1");
$_depositWa = '';
if (!empty($_depositSettings['whatsapp_number'])) {
    $_depositWa = preg_replace('/[^0-9]/', '', (string)$_depositSettings['whatsapp_number']);
}
if (empty($_depositWa) && !empty($_depositSettings['phone'])) {
    $_depositWa = preg_replace('/[^0-9]/', '', (string)$_depositSettings['phone']);
}
$_depositWaMsg = rawurlencode("Hello! Deposit amount nahi mila hai. Please check aur bataiye. (Transaction ID & Date share karein)");
$_depositWaLink = "https://wa.me/" . $_depositWa . "?text=" . $_depositWaMsg;
$_depositWaDisplay = !empty($_depositSettings['whatsapp_number']) ? $_depositSettings['whatsapp_number'] : ($_depositSettings['phone'] ?? '');

// ---- Sirf tabhi notice dikhao jab current user ke paas PENDING deposit ho ----
$_hasPendingDeposit = false;
$_pendingDepositCount = 0;
$_pendingDepositTotal = 0;
$_pendingTxns = [];
if (!empty($udata['phone'])) {
    $_safeUserPhone = mysqli_real_escape_string($ahk_conn, (string)$udata['phone']);
    $_pendRes = @mysqli_query($ahk_conn,
        "SELECT id, txn_id, amount, date, PAYMENTMODE, BANKTXNID
         FROM wallet
         WHERE phone = '{$_safeUserPhone}'
           AND status = 'pending'
           AND `date` >= DATE_SUB(NOW(), INTERVAL 5 DAY)
         ORDER BY id DESC
         LIMIT 10"
    );
    if ($_pendRes && mysqli_num_rows($_pendRes) > 0) {
        $_hasPendingDeposit = true;
        while ($_pr = mysqli_fetch_assoc($_pendRes)) {
            $_pendingTxns[] = $_pr;
            $_pendingDepositCount++;
            $_pendingDepositTotal += (float)$_pr['amount'];
        }
    }
    // Also check wallet_transactions table (paytmUPI flow) PENDING entries by phone
    $_pendRes2 = @mysqli_query($ahk_conn,
        "SELECT id, order_id, amount
         FROM wallet_transactions
         WHERE phone = '{$_safeUserPhone}'
           AND txn_status = 'PENDING'
           AND created_at >= DATE_SUB(NOW(), INTERVAL 5 DAY)
         ORDER BY id DESC
         LIMIT 10"
    );
    if ($_pendRes2 && mysqli_num_rows($_pendRes2) > 0) {
        $_hasPendingDeposit = true;
        while ($_pr2 = mysqli_fetch_assoc($_pendRes2)) {
            $_pendingTxns[] = [
                'txn_id' => $_pr2['order_id'],
                'amount' => $_pr2['amount'],
                'date'   => '',
                'PAYMENTMODE' => 'PaytmUPI',
                'BANKTXNID' => ''
            ];
            $_pendingDepositCount++;
            $_pendingDepositTotal += (float)$_pr2['amount'];
        }
    }
}
?>

<!-- Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Nunito:wght@500;700&display=swap" rel="stylesheet">

<style>
/* ===== Global Theme ===== */
body {
  background: #f5f7fa;
  font-family: 'Inter', sans-serif;
  color: #333;
  line-height: 1.6;
}

/* ===== Typography ===== */
h1, h2, h3, .service-title, .contact-banner {
  font-family: 'Nunito', sans-serif;
  font-weight: 700;
  color: #222;
}
.metric-value { font-family: 'Inter', sans-serif; font-weight: 700; }

/* ===== Dashboard Cards ===== */
.dashboard-card {
  border-radius: 14px;
  background: #fff;
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
  padding: 1.2rem;
  transition: all 0.3s ease;
  border: 1px solid #eaeaea;
}
.dashboard-card:hover {
  transform: translateY(-6px);
  box-shadow: 0 10px 22px rgba(0,0,0,0.12);
}
.card-icon {
  font-size: 34px;
  color: #007bff;
}
.small-title { font-size: 14px; font-weight: 600; opacity: 0.7; }
.metric-value { font-size: 24px; font-weight: 700; }

/* ===== Banner (Premium Colorful — THIN / Compact) ===== */
.contact-banner {
  border-radius: 14px;
  overflow: hidden;
  position: relative;
  padding: 10px 14px;
  display: flex;
  align-items: stretch;
  justify-content: space-between;
  gap: 10px;
  background:
    radial-gradient(1000px 400px at 0% 0%, rgba(255,255,255,0.18) 0%, transparent 55%),
    linear-gradient(135deg, #6366f1 0%, #8b5cf6 25%, #ec4899 55%, #f59e0b 85%, #10b981 100%);
  color: #fff;
  box-shadow:
    0 20px 50px -20px rgba(139, 92, 246, 0.55),
    0 10px 25px -10px rgba(236, 72, 153, 0.35),
    0 4px 10px rgba(0,0,0,0.08),
    inset 0 1px 0 rgba(255,255,255,0.3);
  min-height: 0;
  font-family: 'Nunito', system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
  border: 1px solid rgba(255,255,255,0.2);
}

/* animated shimmer overlay */
.contact-banner:before {
  content: "";
  position: absolute;
  inset: 0;
  background:
    radial-gradient(circle at 15% 25%, rgba(255,255,255,0.14), transparent 10%),
    radial-gradient(circle at 85% 75%, rgba(255,255,255,0.10), transparent 12%),
    linear-gradient(115deg, transparent 0%, transparent 40%, rgba(255,255,255,0.12) 50%, transparent 60%, transparent 100%);
  background-size: 200% 100%;
  animation: banner-shine 5s linear infinite;
  pointer-events: none;
  z-index: 1;
}
@keyframes banner-shine {
  0%   { background-position: 200% 0; }
  100% { background-position: -100% 0; }
}

/* decorative blobs */
.contact-banner:after {
  content: "";
  position: absolute;
  width: 200px; height: 200px;
  right: -60px; top: -60px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(255,255,255,0.12), transparent 70%);
  pointer-events: none;
  z-index: 1;
}

/* left-side text area */
.contact-banner .banner-text {
  z-index: 2;
  flex: 1 1 auto;
  text-align: left;
  min-width: 0;
  position: relative;
}
.contact-banner .banner-title {
  font-weight: 900;
  font-size: 20px;
  display: inline-flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 8px;
  padding: 6px 14px;
  background: rgba(255,255,255,0.18);
  border-radius: 999px;
  backdrop-filter: blur(4px);
  border: 1px solid rgba(255,255,255,0.25);
  box-shadow: 0 4px 12px rgba(0,0,0,0.10);
}

/* subtitle */
.contact-banner .banner-sub {
  font-size: 12.5px;
  line-height: 1.35;
  opacity: 0.98;
  margin-bottom: 5px;
  text-shadow: 0 1px 3px rgba(0,0,0,0.15);
}

/* note pill */
.contact-banner .note {
  display: inline-block;
  margin-top: 2px;
  font-size: 11.5px;
  font-weight: 800;
  background: linear-gradient(135deg, rgba(255,255,255,0.22), rgba(255,255,255,0.08));
  padding: 4px 9px;
  border-radius: 8px;
  border: 1px solid rgba(255,255,255,0.2);
  backdrop-filter: blur(3px);
}

/* whatsapp row */
.contact-banner .wa-row {
  margin-top: 8px;
  display: flex;
  gap: 8px;
  align-items: stretch;
  flex-wrap: wrap;
}

/* ===== Premium Color-Coded Buttons ===== */
.contact-banner .wa-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 9px;
  border-radius: 10px;
  text-decoration: none;
  color: #fff;
  font-weight: 800;
  transition: transform .18s ease, box-shadow .18s ease, filter .18s ease;
  position: relative;
  overflow: hidden;
  border: 1px solid rgba(255,255,255,0.25);
  backdrop-filter: blur(4px);
  flex: 1 1 140px;
  min-width: 120px;
  font-size: 11.5px;
}
.contact-banner .wa-link::before {
  content: "";
  position: absolute;
  inset: 0;
  background: linear-gradient(115deg, transparent 0%, transparent 40%, rgba(255,255,255,0.18) 50%, transparent 60%, transparent 100%);
  background-size: 250% 100%;
  animation: btn-shine 3.5s linear infinite;
  pointer-events: none;
}
@keyframes btn-shine {
  0%   { background-position: 250% 0; }
  100% { background-position: -100% 0; }
}
.contact-banner .wa-link:focus,
.contact-banner .wa-link:hover {
  transform: translateY(-3px) scale(1.02);
  filter: brightness(1.08);
  outline: none;
}

/* WhatsApp: Green */
.contact-banner .wa-link.wa-whatsapp {
  background: linear-gradient(135deg, #128c7e 0%, #25d366 60%, #34d399 100%);
  box-shadow:
    0 8px 20px rgba(37, 211, 102, 0.45),
    inset 0 1px 0 rgba(255,255,255,0.3);
}
.contact-banner .wa-link.wa-whatsapp:hover {
  box-shadow:
    0 14px 28px rgba(37, 211, 102, 0.55),
    inset 0 1px 0 rgba(255,255,255,0.35);
}

/* Telegram: Blue-Purple */
.contact-banner .wa-link.wa-telegram {
  background: linear-gradient(135deg, #0088cc 0%, #229ed9 50%, #7c3aed 100%);
  box-shadow:
    0 8px 20px rgba(34, 158, 217, 0.45),
    inset 0 1px 0 rgba(255,255,255,0.3);
}
.contact-banner .wa-link.wa-telegram:hover {
  box-shadow:
    0 14px 28px rgba(124, 58, 237, 0.5),
    inset 0 1px 0 rgba(255,255,255,0.35);
}

/* Member Group: Orange-Gold */
.contact-banner .wa-link.wa-group {
  background: linear-gradient(135deg, #ea580c 0%, #f59e0b 50%, #facc15 100%);
  box-shadow:
    0 8px 20px rgba(245, 158, 11, 0.45),
    inset 0 1px 0 rgba(255,255,255,0.3);
}
.contact-banner .wa-link.wa-group:hover {
  box-shadow:
    0 14px 28px rgba(234, 88, 12, 0.5),
    inset 0 1px 0 rgba(255,255,255,0.35);
}

.contact-banner .wa-icon {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
  display: inline-block;
  vertical-align: middle;
  filter: drop-shadow(0 1px 2px rgba(0,0,0,0.2));
}

.wa-timing.custom-color {
  color: #fef08a;
  font-size: 10.5px;
  font-weight: 700;
  text-shadow: 0 1px 2px rgba(0,0,0,0.2);
  line-height: 1.2;
}
.wa-number {
  font-size: 12px;
  line-height: 1.15;
  margin-bottom: 1px;
}

/* right-side stacked column (image + title below) */
.contact-banner .banner-right-col {
  z-index: 2;
  position: relative;
}
.contact-banner .banner-image {
  width: 72px;
  height: 72px;
  flex: 0 0 auto;
  border-radius: 14px;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  background:
    linear-gradient(145deg, rgba(255,255,255,0.25), rgba(255,255,255,0.05));
  box-shadow:
    0 8px 18px rgba(0,0,0,0.2),
    inset 0 1px 0 rgba(255,255,255,0.35);
  border: 2px solid rgba(255,255,255,0.3);
  backdrop-filter: blur(6px);
  z-index: 2;
  position: relative;
}
.contact-banner .banner-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.banner-care-img {
  border-radius: 14px;
}

/* responsive: hide entire right column on MOBILE + TABLET screens (< 992px)
   so banner becomes compact. Desktop stays as-is per user. */
@media (max-width: 991.98px) {
  .contact-banner .banner-right-col {
    display: none !important;
  }
}
@media (max-width: 991.98px) {
  .contact-banner {
    flex-direction: column;
    text-align: center;
    padding: 14px 16px;
    min-height: auto;
    gap: 0;
    border-radius: 14px;
  }
  .contact-banner .banner-text { width: 100%; }
  .contact-banner .wa-row { justify-content: center; }
  .contact-banner .wa-link {
    flex: 1 1 100%;
    justify-content: center;
    padding: 10px 14px;
  }
  .contact-banner .banner-sub { text-align: center; }
  .contact-banner .note { margin-left: auto; margin-right: auto; display: inline-block; }
}

/* NARROW DESKTOP / ZOOMED SCREEN (992px - 1199px):
   Agar laptop me zoom karne ke baad space kam ho jaye, to customer care column
   ko niche wrap hone se pehle hi hide kar do. Mobile layout nahi apply hoga,
   sirf right col gayab hoga aur baaki sab sidha rahega. */
@media (min-width: 992px) and (max-width: 1199.98px) {
  .contact-banner .banner-right-col {
    display: none !important;
  }
  .contact-banner {
    flex-wrap: nowrap !important;
  }
}

/* extra compact for small phones */
@media (max-width: 575.98px) {
  .contact-banner {
    padding: 12px 14px;
    border-radius: 12px;
  }
  .contact-banner .banner-sub { font-size: 13.5px; }
  .contact-banner .note { font-size: 12.5px; }
  .contact-banner .wa-row { margin-top: 12px; gap: 8px; }
  .contact-banner .wa-link { padding: 9px 12px; font-size: 13px; }
  .contact-banner .wa-timing.custom-color { font-size: 11.5px; }
  .contact-banner .wa-number { font-size: 13.5px; }
}


/* ===== Services ===== */
.services-section {
  padding: 50px 20px;
  width: 100%;          /* Full width */
  max-width: 100%;
  box-sizing: border-box;
  background: #f9fafe;
}
.services-section h2 {
  font-weight: 800;
  margin-bottom: 10px;
  font-size: 1.8rem;
}
.services-section h3 {
  font-weight: 600;
  color: #666;
  font-size: 1.1rem;
}

/* Custom 5-column grid */
.col-custom-5 {
  flex: 0 0 20%;
  max-width: 20%;
}
@media (max-width: 1200px) {
  .col-custom-5 { flex: 0 0 25%; max-width: 25%; } /* 4 per row */
}
@media (max-width: 992px) {
  .col-custom-5 { flex: 0 0 33.33%; max-width: 33.33%; } /* 3 per row */
}
@media (max-width: 768px) {
  .col-custom-5 { flex: 0 0 50%; max-width: 50%; } /* 2 per row */
}
@media (max-width: 576px) {
  .col-custom-5 { flex: 0 0 100%; max-width: 100%; } /* 1 per row */
}

/* ===== Service Card - PREMIUM COLORFUL ===== */
.service-card {
  border-radius: 18px;
  background:
    radial-gradient(circle at 100% 0%, rgba(139,92,246,0.05) 0%, transparent 45%),
    radial-gradient(circle at 0% 100%, rgba(236,72,153,0.05) 0%, transparent 45%),
    #ffffff;
  padding: 28px 20px;
  height: 220px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  transition: all 0.35s cubic-bezier(.2,.8,.2,1);
  position: relative;
  text-align: center;
  width: 100%;
  z-index: 1;
  overflow: hidden;
  /* Premium gradient border using pseudo-element trick */
}
/* Gradient border frame */
.service-card::before {
  content: "";
  position: absolute;
  inset: 0;
  border-radius: 18px;
  padding: 1.5px;
  background: linear-gradient(135deg,
      rgba(99,102,241,0.55) 0%,
      rgba(139,92,246,0.35) 35%,
      rgba(236,72,153,0.45) 70%,
      rgba(245,158,11,0.55) 100%);
  -webkit-mask:
      linear-gradient(#fff 0 0) content-box,
      linear-gradient(#fff 0 0);
  -webkit-mask-composite: xor;
          mask-composite: exclude;
  z-index: 1;
  pointer-events: none;
  transition: all 0.35s ease;
  opacity: 0.75;
}
/* Soft colorful shadow */
.service-card {
  box-shadow:
      0 6px 16px rgba(99,102,241,0.10),
      0 2px 6px rgba(0,0,0,0.05);
}

/* --- Hover state --- */
.service-card:hover {
  transform: translateY(-10px) scale(1.02);
  color: #fff;
  background:
      radial-gradient(600px 120px at 100% 0%, rgba(255,255,255,0.22) 0%, transparent 55%),
      linear-gradient(135deg, #6366f1 0%, #8b5cf6 40%, #ec4899 75%, #f59e0b 100%);
  box-shadow:
      0 14px 34px rgba(139,92,246,0.45),
      0 4px 12px rgba(236,72,153,0.28),
      inset 0 1px 0 rgba(255,255,255,0.4);
}
/* Hover: border becomes bright white */
.service-card:hover::before {
  opacity: 1;
  padding: 2px;
  background: linear-gradient(135deg,
      rgba(255,255,255,0.9),
      rgba(255,255,255,0.5),
      rgba(255,255,255,0.9));
}
/* Hover: shimmer overlay sweep */
.service-card::after {
  content: "";
  position: absolute;
  inset: 0;
  background: linear-gradient(115deg,
      transparent 0%, transparent 35%,
      rgba(255,255,255,0.22) 50%,
      transparent 65%, transparent 100%);
  background-size: 200% 100%;
  background-position: 200% 0;
  z-index: 2;
  pointer-events: none;
  border-radius: 18px;
  transition: background-position 0.6s ease;
  opacity: 0;
}
.service-card:hover::after {
  opacity: 1;
  background-position: -100% 0;
}

/* Service titles + badges on hover */
.service-card:hover .service-title {
  color: #fff;
  text-shadow: 0 1px 4px rgba(0,0,0,0.2);
}
.service-card:hover .price-badge {
  background: #fff;
  color: #7c3aed;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  transform: scale(1.05);
}
.service-card:hover .badge-new {
  background: #fff;
  color: #ef4444;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  transform: scale(1.05);
}

/* ===== Service Icon - Premium Colorful Ring ===== */
.service-icon {
  width: 100px;
  height: 100px;
  border-radius: 20px;
  padding: 1px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 14px;
  position: relative;
  z-index: 3;
  background: linear-gradient(135deg, #6366f1, #8b5cf6, #ec4899, #f59e0b);
  background-size: 300% 300%;
  animation: serviceIconGrad 8s ease infinite;
  box-shadow:
      0 6px 16px rgba(139,92,246,0.25),
      inset 0 1px 0 rgba(255,255,255,0.35);
  transition: all 0.35s cubic-bezier(.2,.8,.2,1);
}
@keyframes serviceIconGrad {
  0%   { background-position:   0% 50%; }
  50%  { background-position: 100% 50%; }
  100% { background-position:   0% 50%; }
}
.service-icon > img {
  width: 94px;
  height: 94px;
  object-fit: contain;
  border-radius: 18px;
  background:
      radial-gradient(circle at 100% 0%, rgba(139,92,246,0.08) 0%, transparent 40%),
      #ffffff;
  padding: 6px;
}
/* Hover: icon pop + rotate */
.service-card:hover .service-icon {
  transform: translateY(-4px) rotate(-3deg) scale(1.05);
  box-shadow:
      0 10px 24px rgba(236,72,153,0.4),
      inset 0 1px 0 rgba(255,255,255,0.5);
  background: #fff;
}
.service-card:hover .service-icon > img {
  background: rgba(255,255,255,0.95);
}

/* ===== Service Title ===== */
.service-title {
  font-size: 1rem;
  font-weight: 700;
  color: #1e293b;
  letter-spacing: 0.1px;
  position: relative;
  z-index: 3;
  transition: all 0.3s ease;
  font-family: 'Nunito','Inter', sans-serif;
}

/* ===== Price Badge - Premium ===== */
.price-badge {
  font-size: 0.78rem;
  font-weight: 800;
  padding: 5px 11px;
  border-radius: 999px;
  position: absolute;
  top: 12px;
  right: 12px;
  z-index: 4;
  background: linear-gradient(135deg, #2563eb, #7c3aed);
  color: #fff;
  box-shadow:
      0 4px 10px rgba(124,58,237,0.35),
      inset 0 1px 0 rgba(255,255,255,0.35);
  border: 1px solid rgba(255,255,255,0.35);
  transition: all 0.3s ease;
  letter-spacing: 0.2px;
}

/* ===== NEW Badge - Premium ===== */
.badge-new {
  font-size: 0.75rem;
  font-weight: 800;
  padding: 5px 11px;
  border-radius: 999px;
  position: absolute;
  top: 12px;
  left: 12px;
  z-index: 4;
  background: linear-gradient(135deg, #ef4444, #f97316);
  color: #fff;
  box-shadow:
      0 4px 10px rgba(239,68,68,0.38),
      inset 0 1px 0 rgba(255,255,255,0.35);
  border: 1px solid rgba(255,255,255,0.35);
  transition: all 0.3s ease;
  letter-spacing: 0.4px;
  text-transform: uppercase;
  animation: badgeNewPulse 2.4s ease-in-out infinite;
}
@keyframes badgeNewPulse {
  0%,100% { box-shadow:
      0 4px 10px rgba(239,68,68,0.38),
      0 0 0 0 rgba(239,68,68,0.35),
      inset 0 1px 0 rgba(255,255,255,0.35); }  50%     { box-shadow:
      0 4px 10px rgba(239,68,68,0.38),
      0 0 0 6px rgba(239,68,68,0),
      inset 0 1px 0 rgba(255,255,255,0.35); }
}
.wa-timing.custom-color {
  color: #fae102; /* pinkish red */
  font-size: 14px;
  font-weight: 500;
}

/* ===== DEPOSIT NOT RECEIVED NOTICE (ATTENTION GRABBER) ===== */
.deposit-alert-wrapper {
  margin-top: 18px;
  margin-bottom: 6px;
}
.deposit-alert {
  position: relative;
  border-radius: 16px;
  overflow: hidden;
  padding: 22px 24px;
  background: linear-gradient(120deg, #ff1a1a 0%, #ff4d00 45%, #ff8c00 100%);
  color: #fff;
  box-shadow:
    0 0 0 2px rgba(255,255,255,0.25) inset,
    0 10px 28px rgba(255,30,0,0.35),
    0 6px 16px rgba(0,0,0,0.18);
  border: 2px solid #ffe066;
  animation: deposit-pulse-border 1.6s ease-in-out infinite;
  font-family: 'Nunito', 'Inter', system-ui, sans-serif;
}
@keyframes deposit-pulse-border {
  0%, 100% {
    box-shadow:
      0 0 0 2px rgba(255,255,255,0.25) inset,
      0 10px 28px rgba(255,30,0,0.35),
      0 6px 16px rgba(0,0,0,0.18);
  }
  50% {
    box-shadow:
      0 0 0 4px rgba(255,230,102,0.55) inset,
      0 14px 38px rgba(255,30,0,0.55),
      0 10px 22px rgba(0,0,0,0.25);
  }
}

/* shimmering shine sweep */
.deposit-alert::before {
  content: "";
  position: absolute;
  inset: 0;
  background: linear-gradient(
    115deg,
    transparent 0%,
    transparent 35%,
    rgba(255,255,255,0.18) 50%,
    transparent 65%,
    transparent 100%
  );
  background-size: 250% 100%;
  animation: deposit-shine 2.6s linear infinite;
  pointer-events: none;
  z-index: 2;
}
@keyframes deposit-shine {
  0%   { background-position: 200% 0; }
  100% { background-position: -100% 0; }
}

/* diagonal red stripes for alert vibe */
.deposit-alert::after {
  content: "";
  position: absolute;
  inset: 0;
  background-image: repeating-linear-gradient(
    45deg,
    rgba(255,255,255,0.06) 0 10px,
    transparent 10px 22px
  );
  pointer-events: none;
  z-index: 1;
}

.deposit-alert-inner {
  position: relative;
  z-index: 3;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 22px;
  flex-wrap: wrap;
}

/* left: icon + text */
.deposit-alert-left {
  flex: 1 1 auto;
  min-width: 260px;
  display: flex;
  align-items: center;
  gap: 18px;
}
.deposit-alert-icon {
  flex: 0 0 auto;
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 6px 16px rgba(0,0,0,0.2);
  animation: deposit-bell 1.4s ease-in-out infinite;
}
@keyframes deposit-bell {
  0%, 100% { transform: rotate(0deg) scale(1); }
  15%      { transform: rotate(-14deg) scale(1.05); }
  30%      { transform: rotate(12deg)  scale(1.05); }
  45%      { transform: rotate(-10deg) scale(1.03); }
  60%      { transform: rotate(8deg)   scale(1.03); }
  75%      { transform: rotate(-4deg); }
}
.deposit-alert-icon svg {
  width: 34px;
  height: 34px;
}

.deposit-alert-text h2 {
  margin: 0 0 4px 0;
  font-size: 26px;
  font-weight: 900;
  letter-spacing: 0.3px;
  color: #fff;
  text-shadow: 0 2px 6px rgba(0,0,0,0.25);
  line-height: 1.15;
}
.deposit-alert-text h2 .blink {
  display: inline-block;
  animation: deposit-blink-text 0.9s steps(2,start) infinite;
  color: #fff200;
  text-transform: uppercase;
}
@keyframes deposit-blink-text {
  0%, 100% { opacity: 1; }
  50%      { opacity: 0.3; }
}

.deposit-alert-text p {
  margin: 0;
  font-size: 15px;
  font-weight: 600;
  color: #fff6d5;
  opacity: 0.98;
  line-height: 1.4;
}
.deposit-alert-text .deposit-sub {
  margin-top: 4px;
  font-size: 13px;
  font-weight: 700;
  color: #ffffff;
  opacity: 0.95;
}
.deposit-alert-text .deposit-sub .tag {
  display: inline-block;
  background: #fff200;
  color: #c20000;
  padding: 2px 8px;
  border-radius: 6px;
  margin: 0 4px;
  font-weight: 800;
}

/* right: WhatsApp CTA button */
.deposit-wa-cta {
  flex: 0 0 auto;
  display: flex;
  flex-direction: column;
  gap: 8px;
  align-items: stretch;
  min-width: 240px;
}
.deposit-wa-btn {
  display: inline-flex !important;
  align-items: center;
  justify-content: center;
  gap: 10px;
  background: #fff;
  color: #0ca148 !important;
  font-weight: 900;
  font-size: 16px;
  padding: 14px 22px;
  border-radius: 14px;
  text-decoration: none !important;
  box-shadow:
    0 6px 18px rgba(0,0,0,0.22),
    0 0 0 3px rgba(255,255,255,0.35) inset;
  transition: transform .15s ease, box-shadow .15s ease;
  animation: deposit-cta-bounce 1.6s ease-in-out infinite;
  position: relative;
  overflow: hidden;
}
@keyframes deposit-cta-bounce {
  0%, 100% { transform: translateY(0) scale(1); }
  50%      { transform: translateY(-3px) scale(1.03); }
}
.deposit-wa-btn:hover {
  transform: translateY(-5px) scale(1.04) !important;
  box-shadow:
    0 12px 28px rgba(0,0,0,0.3),
    0 0 0 4px rgba(255,255,255,0.45) inset;
}
.deposit-wa-btn svg {
  width: 26px;
  height: 26px;
  flex: 0 0 auto;
}
.deposit-wa-btn .btn-text {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  line-height: 1.1;
}
.deposit-wa-btn .btn-main {
  font-size: 17px;
  font-weight: 900;
  color: #0ca148;
  text-transform: uppercase;
}
.deposit-wa-btn .btn-num {
  font-size: 12px;
  font-weight: 700;
  color: #555;
  margin-top: 2px;
}
.deposit-wa-hint {
  text-align: center;
  font-size: 12px;
  font-weight: 700;
  color: #fff6d5;
  letter-spacing: 0.3px;
}

/* responsive */
@media (max-width: 768px) {
  .deposit-alert { padding: 18px 16px; }
  .deposit-alert-inner { flex-direction: column; align-items: stretch; gap: 16px; }
  .deposit-alert-left { flex-direction: column; text-align: center; }
  .deposit-alert-text h2 { font-size: 22px; }
  .deposit-wa-cta { min-width: 100%; }
  .deposit-wa-btn { width: 100%; }
}

</style>
 
<!-- ================== DASHBOARD ================== -->
<div class="page-wrapper">
  <div class="page-content">
    <div class="row g-3 row-cols-1 row-cols-md-2 row-cols-lg-4">
<?php
if (checkAdmin($udata['type']) == true) {

    /* ================= API BALANCE (PARIPRINT.IN) ================= */

$buttonText = "Check Balance";

if (isset($_POST['check_balance'])) {
    global $pariprint_api_key;

    $apiBase = (function_exists('get_central_api_domain') ? rtrim(get_central_api_domain(), '/') : 'https://pariprint.in');
    $apiUrl = $apiBase . "/api-balance.php?" . http_build_query([
        'api_key' => $pariprint_api_key
    ]);

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'goodprintportal.in-balance-check'
    ]);

    $responseApi = curl_exec($ch);

    if (curl_errno($ch)) {
        $buttonText = "Net Error";
    } else {
        $response = json_decode($responseApi, true);

        if (isset($response['status']) && $response['status'] === "success" && isset($response['balance'])) {
            $balance = (float) $response['balance'];
            $buttonText = "₹ " . htmlspecialchars(number_format($balance, 2));
        } elseif (isset($response['message'])) {
            $msg = $response['message'];
            $buttonText = strlen($msg) < 25 ? htmlspecialchars($msg) : "Error";
        } else {
            $buttonText = "Error";
        }
    }

    curl_close($ch);
}

    /* ================= TODAY'S TOTAL SUCCESSFUL AMOUNT RECEIVED ================= */
    $todaySuccessTotal = 0;
    $todaySuccessCount = 0;
    $_tsRes = @mysqli_query($ahk_conn,
        "SELECT COALESCE(SUM(CAST(amount AS DECIMAL(12,2))),0) AS total_amt, COUNT(*) AS cnt
         FROM wallet
         WHERE status = 'success'
           AND DATE(COALESCE(txn_date, date)) = CURDATE()"
    );
    if ($_tsRes && ($_tsRow = mysqli_fetch_assoc($_tsRes))) {
        $todaySuccessTotal = (float)($_tsRow['total_amt'] ?? 0);
        $todaySuccessCount = (int)($_tsRow['cnt'] ?? 0);
    }
    @mysqli_free_result($_tsRes);

    /* ================= TOTAL USER WALLET BALANCE (EXCLUDING ADMIN) ================= */
    $totalUserWalletBalance = 0;
    $_twRes = @mysqli_query($ahk_conn,
        "SELECT COALESCE(SUM(CAST(balance AS DECIMAL(12,2))),0) AS total_bal
         FROM users
         WHERE LOWER(COALESCE(type,'user')) NOT IN ('admin','administrator','superadmin')"
    );
    if ($_twRes && ($_twRow = mysqli_fetch_assoc($_twRes))) {
        $totalUserWalletBalance = (float)($_twRow['total_bal'] ?? 0);
    }
    @mysqli_free_result($_twRes);
?>

    <!-- Total Users -->
    <div class="col-lg-3 col-md-6 d-flex">
      <div class="dashboard-card w-100">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <div class="small-title">Total Users</div>
            <div class="metric-value"><?php echo (int)($allusersforadmin ?? 0); ?></div>
          </div>
          <i class="bx bx-user card-icon"></i>
        </div>
      </div>
    </div>

    <!-- API Balance -->
    <div class="col-lg-3 col-md-6 d-flex">
      <div class="dashboard-card w-100">
        <form method="POST">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="small-title">API Balance</div>
            <i class="bx bx-wallet card-icon"></i>
          </div>
          <button type="submit" name="check_balance" class="btn btn-primary btn-sm w-100">
            <?php echo $buttonText; ?>
          </button>
        </form>
      </div>
    </div>

    <!-- Today's Total Successful Amount Received (CLICKABLE) -->
    <div class="col-lg-3 col-md-6 d-flex">
      <a href="daily_success_report.php" style="text-decoration:none;color:inherit;width:100%;display:flex;">
        <div class="dashboard-card w-100" style="cursor:pointer;border:2px solid #28a745;background:linear-gradient(135deg,#f0fff4 0%,#ffffff 100%);">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div>
              <div class="small-title" style="color:#155724;font-weight:700;">
                💰 Today's Total Successful Amount Received
              </div>
              <div class="metric-value" style="color:#155724;">
                ₹ <?php echo number_format($todaySuccessTotal, 2); ?>
              </div>
              <small class="text-muted">
                <?php echo $todaySuccessCount; ?> successful deposits · Click for date-wise report
              </small>
            </div>
            <i class="bx bx-rupee card-icon" style="color:#28a745;"></i>
          </div>
        </div>
      </a>
    </div>

    <!-- Total User Wallet Balance (EXCLUDING ADMIN) -->
    <div class="col-lg-3 col-md-6 d-flex">
      <div class="dashboard-card w-100" style="border:2px solid #ffc107;background:linear-gradient(135deg,#fffbeb 0%,#ffffff 100%);">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <div class="small-title" style="color:#856404;font-weight:700;">
              👥 Total User Balance
            </div>
            <div class="metric-value" style="color:#856404;">
              ₹ <?php echo number_format($totalUserWalletBalance, 2); ?>
            </div>
            <small class="text-muted">All users combined (Admin excluded)</small>
          </div>
          <i class="bx bx-wallet-alt card-icon" style="color:#ffc107;"></i>
        </div>
      </div>
    </div>

<?php } ?>
  </div>
</div>







<div class="container-fluid">

  <?php
  // ======================================================================
  // PUSH NOTICE BANNER — Customer Care se upar
  // (DB me column na ho to silently skip, no error)
  // ======================================================================
  $pnShowBanner = false;
  $pnText = '';
  $pnWalletShowBanner = false;
  $pnWalletText = '';
  $userHasWalletBalance = !empty($udata) && isset($udata['balance']) && (float)$udata['balance'] > 0;

  if (!empty($webdata) && is_array($webdata)) {
      // General notice (ALL USERS)
      $_pnEn = $webdata['push_notice_enabled'] ?? null;
      $_pnTx = $webdata['push_notice_text']    ?? '';
      if ($_pnEn !== null) {
          $pnShowBanner = ((int)$_pnEn === 1) && trim((string)$_pnTx) !== '';
          $pnText       = (string)$_pnTx;
      }
      // Wallet balance users notice (only if user has balance > 0)
      $_pnWEn = $webdata['push_notice_wallet_enabled'] ?? null;
      $_pnWTx = $webdata['push_notice_wallet_text']    ?? '';
      if ($_pnWEn !== null) {
          $pnWalletShowBanner = $userHasWalletBalance && ((int)$_pnWEn === 1) && trim((string)$_pnWTx) !== '';
          $pnWalletText       = (string)$_pnWTx;
      }
  }
  // Column-agnostic fallback (safety if DB migration pending)
  if ((!$pnShowBanner || !$pnWalletShowBanner) && !empty($GLOBALS['ahk_conn'])) {
      if (!$pnShowBanner) {
          $_chk = @mysqli_query($GLOBALS['ahk_conn'], "SHOW COLUMNS FROM settings LIKE 'push_notice_enabled'");
          if ($_chk && mysqli_num_rows($_chk) > 0) {
              $_r = @mysqli_query($GLOBALS['ahk_conn'], "SELECT push_notice_enabled, push_notice_text FROM settings WHERE id=1 LIMIT 1");
              if ($_r && ($_row = mysqli_fetch_assoc($_r))) {
                  if ((int)($_row['push_notice_enabled'] ?? 0) === 1 && trim((string)($_row['push_notice_text'] ?? '')) !== '') {
                      $pnShowBanner = true;
                      $pnText = (string)$_row['push_notice_text'];
                  }
              }
              @mysqli_free_result($_r);
          }
          @mysqli_free_result($_chk);
      }
      if (!$pnWalletShowBanner && $userHasWalletBalance) {
          $_chk2 = @mysqli_query($GLOBALS['ahk_conn'], "SHOW COLUMNS FROM settings LIKE 'push_notice_wallet_enabled'");
          if ($_chk2 && mysqli_num_rows($_chk2) > 0) {
              $_r2 = @mysqli_query($GLOBALS['ahk_conn'], "SELECT push_notice_wallet_enabled, push_notice_wallet_text FROM settings WHERE id=1 LIMIT 1");
              if ($_r2 && ($_row2 = mysqli_fetch_assoc($_r2))) {
                  if ((int)($_row2['push_notice_wallet_enabled'] ?? 0) === 1 && trim((string)($_row2['push_notice_wallet_text'] ?? '')) !== '') {
                      $pnWalletShowBanner = true;
                      $pnWalletText = (string)$_row2['push_notice_wallet_text'];
                  }
              }
              @mysqli_free_result($_r2);
          }
          @mysqli_free_result($_chk2);
      }
  }
  ?>
  <?php
  // ——— Determine if ANY notice style block is needed (avoid wasted CSS bytes) ———
  $pnAnyActive = $pnShowBanner || $pnWalletShowBanner;
  ?>
  <?php if ($pnAnyActive): ?>
  <style>
    /* ============================================================
       PREMIUM PUSH NOTICE STYLES — Shared
       ============================================================ */
    .gp-notice-glow {
      position: relative;
      isolation: isolate;
    }
    /* Sparkle dots overlay for premium feel */
    .gp-sparkles {
      position: absolute; inset: 0;
      pointer-events: none;
      overflow: hidden;
      z-index: 0;
    }
    .gp-sparkles span {
      position: absolute;
      width: 6px; height: 6px;
      background: radial-gradient(circle, #fff 0%, rgba(255,255,255,0) 70%);
      border-radius: 50%;
      opacity: .7;
      animation: gp-sparkle-drift 6s linear infinite;
    }
    .gp-sparkles span:nth-child(1){ top: 20%; left: 10%; animation-delay: 0s;   }
    .gp-sparkles span:nth-child(2){ top: 60%; left: 85%; animation-delay: 1.2s; }
    .gp-sparkles span:nth-child(3){ top: 35%; left: 55%; animation-delay: 2.4s; width: 4px; height: 4px; }
    .gp-sparkles span:nth-child(4){ top: 80%; left: 25%; animation-delay: 3.6s; width: 5px; height: 5px; }
    .gp-sparkles span:nth-child(5){ top: 15%; left: 72%; animation-delay: 4.8s; width: 3px; height: 3px; }
    @keyframes gp-sparkle-drift {
      0%   { transform: translateY(0)    scale(1);   opacity: .0; }
      15%  { opacity: .9; }
      50%  { transform: translateY(-18px) scale(1.2); opacity: .6; }
      85%  { opacity: .8; }
      100% { transform: translateY(-36px) scale(1);   opacity: 0; }
    }

    /* ============================================================
       NOTICE 1 — ALL USERS (ORANGE / WARNING)
       ============================================================ */
    .gp-push-notice {
      border-radius: 22px;
      background:
        radial-gradient(1200px 600px at 0% -10%, rgba(255,255,255,.55) 0%, rgba(255,255,255,0) 60%),
        linear-gradient(135deg, #fff7ed 0%, #ffedd5 40%, #fed7aa 100%);
      border: 2px solid rgba(249, 115, 22, 0.28);
      box-shadow:
        0 20px 45px -22px rgba(234, 88, 12, 0.55),
        0 8px 20px -8px  rgba(234, 88, 12, 0.25),
        0 2px 8px rgba(0,0,0,0.04),
        inset 0 1px 0 rgba(255,255,255,.9);
      padding: 22px 26px;
      position: relative;
      overflow: hidden;
      margin-bottom: 18px;
      display: flex;
      align-items: flex-start;
      gap: 20px;
    }
    /* Animated top gradient bar */
    .gp-push-notice::before {
      content: '';
      position: absolute; top: 0; left: 0; right: 0;
      height: 4px;
      background: linear-gradient(90deg, #f59e0b, #ef4444, #f97316, #ef4444, #f59e0b);
      background-size: 300% 100%;
      animation: gp-pn-bar 4s linear infinite;
      box-shadow: 0 1px 0 rgba(255,255,255,.6) inset;
    }
    @keyframes gp-pn-bar {
      0%   { background-position: 0% 0; }
      100% { background-position: 300% 0; }
    }
    /* Bottom subtle pattern */
    .gp-push-notice::after {
      content: '';
      position: absolute; bottom: 0; left: 0; right: 0; top: 60%;
      background-image:
        radial-gradient(rgba(234,88,12,.08) 1px, transparent 1px);
      background-size: 16px 16px;
      opacity: .9;
      z-index: 0;
      pointer-events: none;
    }
    .gp-pn-icon {
      width: 58px; height: 58px; flex-shrink: 0;
      border-radius: 18px;
      display: inline-flex;
      align-items: center; justify-content: center;
      background: linear-gradient(145deg, #fb923c, #ef4444 65%, #b91c1c);
      color: #fff;
      box-shadow:
        0 10px 22px rgba(239,68,68,.38),
        0 0 0 3px rgba(254,215,170,.7),
        inset 0 2px 0 rgba(255,255,255,.4),
        inset 0 -2px 0 rgba(0,0,0,.15);
      position: relative;
      z-index: 2;
    }
    .gp-pn-icon i {
      font-size: 24px;
      animation: gp-pn-shake 2.4s ease-in-out infinite;
      filter: drop-shadow(0 2px 2px rgba(0,0,0,.2));
    }
    @keyframes gp-pn-shake {
      0%,100% { transform: rotate(0); }
      15%     { transform: rotate(-14deg); }
      30%     { transform: rotate(12deg); }
      45%     { transform: rotate(-9deg); }
      60%     { transform: rotate(7deg); }
      75%     { transform: rotate(-4deg); }
    }
    .gp-pn-body { flex: 1 1 auto; min-width: 0; position: relative; z-index: 2; }
    .gp-pn-title {
      font-size: 17px;
      font-weight: 900;
      color: #7c2d12;
      letter-spacing: 0.03em;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
      text-transform: uppercase;
    }
    .gp-pn-title i {
      font-size: 17px;
      color: #ea580c;
    }
    .gp-pn-live {
      font-size: 10.5px;
      letter-spacing: 0.12em;
      background: linear-gradient(135deg, #ef4444, #b91c1c);
      color: #fff;
      padding: 4px 12px;
      border-radius: 999px;
      font-weight: 900;
      text-transform: uppercase;
      box-shadow:
        0 4px 10px rgba(239,68,68,.35),
        inset 0 1px 0 rgba(255,255,255,.25);
      position: relative;
    }
    .gp-pn-live::before {
      content: '';
      display: inline-block;
      width: 6px; height: 6px;
      margin-right: 6px;
      border-radius: 50%;
      background: #fff;
      vertical-align: middle;
      box-shadow: 0 0 0 0 rgba(255,255,255,.7);
      animation: gp-pn-pulse 1.6s ease-out infinite;
    }
    @keyframes gp-pn-pulse {
      0%   { box-shadow: 0 0 0 0 rgba(255,255,255,.65); }
      70%  { box-shadow: 0 0 0 10px rgba(255,255,255,0); }
      100% { box-shadow: 0 0 0 0 rgba(255,255,255,0); }
    }
    .gp-pn-text {
      font-size: 15px;
      color: #78350f;
      line-height: 1.72;
      white-space: pre-wrap;
      word-break: break-word;
      font-weight: 500;
      text-shadow: 0 1px 0 rgba(255,255,255,.6);
    }
    .gp-pn-close {
      border: none;
      background: rgba(124,45,18,0.08);
      color: #7c2d12;
      border-radius: 12px;
      width: 38px;
      height: 38px;
      font-size: 18px;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      transition: background .2s, color .2s, transform .2s, box-shadow .2s;
      position: relative;
      z-index: 3;
      backdrop-filter: blur(2px);
    }
    .gp-pn-close:hover {
      background: rgba(239,68,68,0.2);
      color: #b91c1c;
      transform: scale(1.08) rotate(90deg);
      box-shadow: 0 4px 12px rgba(239,68,68,.2);
    }
    @media (max-width: 540px) {
      .gp-push-notice { padding: 16px 18px; gap: 14px; border-radius: 18px; }
      .gp-pn-icon { width: 46px; height: 46px; border-radius: 14px; }
      .gp-pn-icon i { font-size: 19px; }
      .gp-pn-title { font-size: 14.5px; }
      .gp-pn-text  { font-size: 13.5px; line-height: 1.65; }
      .gp-pn-close { width: 34px; height: 34px; border-radius: 10px; }
    }

    /* ============================================================
       NOTICE 2 — WALLET BALANCE USERS (GREEN / SUCCESS)
       ============================================================ */
    .gp-wallet-notice {
      border-radius: 22px;
      background:
        radial-gradient(1200px 600px at 100% -10%, rgba(255,255,255,.55) 0%, rgba(255,255,255,0) 60%),
        linear-gradient(135deg, #f0fdf4 0%, #dcfce7 40%, #bbf7d0 100%);
      border: 2px solid rgba(16, 185, 129, 0.28);
      box-shadow:
        0 20px 45px -22px rgba(5, 150, 105, 0.55),
        0 8px 20px -8px  rgba(5, 150, 105, 0.25),
        0 2px 8px rgba(0,0,0,0.04),
        inset 0 1px 0 rgba(255,255,255,.9);
      padding: 22px 26px;
      position: relative;
      overflow: hidden;
      margin-bottom: 22px;
      display: flex;
      align-items: flex-start;
      gap: 20px;
      animation: gp-wn-attention 4.8s ease-in-out infinite;
      will-change: transform, box-shadow;
    }
    @keyframes gp-wn-attention {
      0%, 100% {
        transform: translateY(0);
        box-shadow:
          0 20px 45px -22px rgba(5, 150, 105, 0.55),
          0 8px 20px -8px rgba(5, 150, 105, 0.25),
          0 2px 8px rgba(0,0,0,0.04),
          inset 0 1px 0 rgba(255,255,255,.9);
      }
      50% {
        transform: translateY(-3px);
        box-shadow:
          0 26px 50px -22px rgba(5, 150, 105, 0.7),
          0 12px 24px -8px rgba(5, 150, 105, 0.34),
          0 2px 8px rgba(0,0,0,0.04),
          inset 0 1px 0 rgba(255,255,255,.9);
      }
    }
    /* Animated top gradient bar */
    .gp-wallet-notice::before {
      content: '';
      position: absolute; top: 0; left: 0; right: 0;
      height: 4px;
      background: linear-gradient(90deg, #10b981, #059669, #14b8a6, #059669, #10b981);
      background-size: 300% 100%;
      animation: gp-wn-bar 4s linear infinite, gp-wn-bar-glow 1.8s ease-in-out infinite;
      box-shadow: 0 1px 0 rgba(255,255,255,.6) inset;
    }
    @keyframes gp-wn-bar {
      0%   { background-position: 0% 0; }
      100% { background-position: 300% 0; }
    }
    @keyframes gp-wn-bar-glow {
      0%, 100% { opacity: .75; }
      50% { opacity: 1; }
    }
    /* Bottom dotted pattern */
    .gp-wallet-notice::after {
      content: '';
      position: absolute; bottom: 0; left: 0; right: 0; top: 60%;
      background-image:
        radial-gradient(rgba(5,150,105,.08) 1px, transparent 1px);
      background-size: 16px 16px;
      opacity: .9;
      z-index: 0;
      pointer-events: none;
    }
    .gp-wn-icon {
      width: 58px; height: 58px; flex-shrink: 0;
      border-radius: 18px;
      display: inline-flex;
      align-items: center; justify-content: center;
      background: linear-gradient(145deg, #34d399, #059669 60%, #047857);
      color: #fff;
      box-shadow:
        0 10px 22px rgba(5,150,105,.38),
        0 0 0 3px rgba(187,247,208,.7),
        inset 0 2px 0 rgba(255,255,255,.4),
        inset 0 -2px 0 rgba(0,0,0,.15);
      position: relative;
      z-index: 2;
    }
    .gp-wn-icon::before {
      content: '';
      position: absolute;
      inset: -8px;
      border: 2px solid rgba(16,185,129,.45);
      border-radius: 24px;
      animation: gp-wn-ring 2.4s ease-out infinite;
      pointer-events: none;
    }
    @keyframes gp-wn-ring {
      0% { opacity: .8; transform: scale(.9); }
      70%, 100% { opacity: 0; transform: scale(1.22); }
    }
    .gp-wn-icon::after {
      content: '!';
      position: absolute;
      font-size: 10px;
      font-weight: 900;
      right: -4px; bottom: -4px;
      background: #fff;
      color: #dc2626;
      width: 22px; height: 22px;
      border-radius: 50%;
      display: inline-flex;
      align-items: center; justify-content: center;
      box-shadow: 0 2px 6px rgba(5,150,105,.3);
      animation: gp-wn-coin-spin 4s ease-in-out infinite;
    }
    @keyframes gp-wn-coin-spin {
      0%,100% { transform: scale(1) rotate(0); }
      50%     { transform: scale(1.08) rotate(12deg); }
    }
    .gp-wn-icon i {
      font-size: 24px;
      animation: gp-wn-bounce 2.4s ease-in-out infinite;
      filter: drop-shadow(0 2px 2px rgba(0,0,0,.15));
    }
    .gp-wn-notice-symbol {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 30px;
      height: 30px;
      border: 3px solid #fff;
      border-radius: 50%;
      color: #fff;
      font-size: 23px;
      font-weight: 900;
      line-height: 1;
      animation: gp-wn-bounce 2.4s ease-in-out infinite;
      filter: drop-shadow(0 2px 2px rgba(0,0,0,.18));
    }
    @keyframes gp-wn-bounce {
      0%,100% { transform: translateY(0); }
      50%     { transform: translateY(-8px); }
    }
    .gp-wn-body { flex: 1 1 auto; min-width: 0; position: relative; z-index: 2; }
    .gp-wn-title {
      font-size: 17px;
      font-weight: 900;
      color: #064e3b;
      letter-spacing: 0.03em;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
      text-transform: uppercase;
      text-shadow: 0 1px 0 rgba(255,255,255,.8);
    }
    .gp-wn-title i {
      font-size: 17px;
      color: #059669;
    }
    .gp-wn-tag {
      font-size: 10.5px;
      letter-spacing: 0.12em;
      background: linear-gradient(135deg, #10b981, #047857);
      color: #fff;
      padding: 4px 12px;
      border-radius: 999px;
      font-weight: 900;
      text-transform: uppercase;
      box-shadow:
        0 4px 10px rgba(16,185,129,.35),
        inset 0 1px 0 rgba(255,255,255,.25);
      position: relative;
      animation: gp-wn-tag-pulse 2.2s ease-in-out infinite;
    }
    @keyframes gp-wn-tag-pulse {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.06); }
    }
    .gp-wn-tag::before {
      content: '💎';
      margin-right: 5px;
      font-size: 10px;
      vertical-align: middle;
      display: inline-block;
      animation: gp-wn-diamond 2.2s ease-in-out infinite;
    }
    @keyframes gp-wn-diamond {
      0%,100% { transform: scale(1); filter: brightness(1); }
      50%     { transform: scale(1.3); filter: brightness(1.4); }
    }
    .gp-wn-text {
      font-size: 18px;
      color: #dc2626;
      line-height: 1.65;
      white-space: pre-wrap;
      word-break: break-word;
      font-weight: 900;
      letter-spacing: 0.02em;
      text-shadow: 0 1px 0 rgba(255,255,255,.8);
      animation: gp-wn-text-pulse 1.8s ease-in-out infinite;
    }
    @keyframes gp-wn-text-pulse {
      0%, 100% {
        color: #dc2626;
        text-shadow: 0 1px 0 rgba(255,255,255,.8);
      }
      50% {
        color: #991b1b;
        text-shadow: 0 0 12px rgba(239,68,68,.45);
        transform: scale(1.015);
      }
    }
    .gp-wn-close {
      border: none;
      background: rgba(6,78,59,0.08);
      color: #064e3b;
      border-radius: 12px;
      width: 38px;
      height: 38px;
      font-size: 18px;
      cursor: pointer;
      display: inline-flex;
      align-items: center; justify-content: center;
      flex-shrink: 0;
      transition: background .2s, color .2s, transform .2s, box-shadow .2s;
      position: relative;
      z-index: 3;
      backdrop-filter: blur(2px);
    }
    .gp-wn-close:hover {
      background: rgba(5,150,105,0.2);
      color: #064e3b;
      transform: scale(1.08) rotate(90deg);
      box-shadow: 0 4px 12px rgba(5,150,105,.2);
    }
    @media (max-width: 540px) {
      .gp-wallet-notice { padding: 16px 18px; gap: 14px; border-radius: 18px; }
      .gp-wn-icon { width: 46px; height: 46px; border-radius: 14px; }
      .gp-wn-icon::after { width: 18px; height: 18px; font-size: 8px; right: -2px; bottom: -2px; }
      .gp-wn-icon i { font-size: 19px; }
      .gp-wn-title { font-size: 14.5px; }
      .gp-wn-text  { font-size: 15.5px; line-height: 1.6; }
      .gp-wn-close { width: 34px; height: 34px; border-radius: 10px; }
    }
    @media (prefers-reduced-motion: reduce) {
      .gp-wallet-notice,
      .gp-wallet-notice::before,
      .gp-wn-icon::before,
      .gp-wn-icon::after,
      .gp-wn-icon i,
      .gp-wn-notice-symbol,
      .gp-wn-tag,
      .gp-wn-tag::before,
      .gp-wn-text {
        animation: none;
      }
    }
  </style>
  <?php endif; /* /pnAnyActive styles */ ?>

  <?php if ($pnShowBanner): ?>
  <div class="gp-push-notice gp-notice-glow" role="alert" id="gpPushNotice">
    <div class="gp-sparkles"><span></span><span></span><span></span><span></span><span></span></div>
    <div class="gp-pn-icon">
      <i class="bi bi-megaphone-fill"></i>
    </div>
    <div class="gp-pn-body">
      <div class="gp-pn-title">
        <i class="bi bi-broadcast-pin" style="display:inline-flex;"></i>
        IMPORTANT NOTICE
        <span class="gp-pn-live">LIVE</span>
      </div>
      <div class="gp-pn-text"><?php echo nl2br(htmlspecialchars($pnText)); ?></div>
    </div>
    <button type="button" class="gp-pn-close"
            title="Dismiss (ek baar ke liye hide)"
            onclick="document.getElementById('gpPushNotice').style.display='none';">
      <i class="bi bi-x-lg" style="font-size:15px;"></i>
    </button>
  </div>
  <?php endif; ?>

  <?php if ($pnWalletShowBanner): ?>
  <div class="gp-wallet-notice gp-notice-glow" role="alert" id="gpWalletNotice">
    <div class="gp-sparkles"><span></span><span></span><span></span><span></span><span></span></div>
    <div class="gp-wn-icon">
      <span class="gp-wn-notice-symbol" aria-label="Important notice">!</span>
    </div>
    <div class="gp-wn-body">
      <div class="gp-wn-title">
        <i class="bi bi-credit-card-2-front" style="display:inline-flex;"></i>
        IMPORTANT NOTICE
        <span class="gp-wn-tag">FOR YOU</span>
      </div>
      <div class="gp-wn-text"><?php echo nl2br(htmlspecialchars($pnWalletText)); ?></div>
    </div>
    <button type="button" class="gp-wn-close"
            title="Dismiss (ek baar ke liye hide)"
            onclick="document.getElementById('gpWalletNotice').style.display='none';">
      <i class="bi bi-x-lg" style="font-size:15px;"></i>
    </button>
  </div>
  <?php endif; ?>

  <div class="contact-banner shadow-sm d-flex justify-content-between align-items-stretch flex-wrap" role="region" aria-label="Customer Care banner">

    <!-- Left Content -->
    <div class="banner-text" style="flex: 1 1 auto; min-width: 280px;">
      <div class="banner-sub">
        Hello <strong><?php echo htmlspecialchars($displayName ?? 'User'); ?></strong>,
        please use our services on <strong>Desktop / Laptop</strong> with
        <strong>Chrome</strong> or <strong>Mozilla</strong> —
        <strong><?php echo htmlspecialchars($webname ?? ($webdata['webname'] ?? 'Good Print Portal')); ?></strong>
      </div>

      <span class="note d-block mt-2">⚠️ Please don’t try double and resubmit call</span>

      <div class="wa-row mt-3" aria-hidden="false">
        <a
          class="wa-link wa-whatsapp d-flex align-items-center"
          href="https://wa.me/<?php echo ahkweb('phone');  ?>"
          target="_blank"
          rel="noopener noreferrer"
          aria-label="Chat on WhatsApp with <?php echo ahkweb('phone');  ?>"
          title="Chat on WhatsApp"
        >
          <img class="wa-icon" src="/assets/img/whatsapp_icon.png" alt="WhatsApp icon" style="width:18px;height:18px;flex-shrink:0;">
          <div>
            <div class="wa-number fw-bold text-white"><?php echo ahkweb('phone');  ?></div>
           <div class="wa-timing custom-color">
  🕒 Working hours only.
</div>


          </div>
        </a>
<a
  class="wa-link wa-telegram d-flex align-items-center"
  href="https://t.me/<?php echo ahkweb('payu_mkey'); ?>"
  target="_blank"
  rel="noopener noreferrer"
  aria-label="Chat on Telegram with @<?php echo ahkweb('payu_mkey'); ?>"
  title="Chat on Telegram"
>
  <i class="fab fa-telegram-plane" style="font-size: 17px; color: #fff;flex-shrink:0;"></i>
  <div>
    <div class="wa-number fw-bold text-white">
      @<?php echo ahkweb('payu_mkey'); ?>
    </div>
    <div class="wa-timing custom-color">
      🕒 Working hours.
    </div>
  </div>
</a>

          <a
          class="wa-link wa-group d-flex align-items-center"
          href="<?php echo ahkweb('salt');   ?>"
          target="_blank"
          rel="noopener noreferrer"
          aria-label="Chat on Telegram with Member Group"
          title="Member Group"
        >
         <i class="fas fa-users" style="font-size: 17px; color: #fff;flex-shrink:0;"></i>
          <div>
            <div class="wa-number fw-bold text-white">Member Group</div>
           <div class="wa-timing custom-color">
  🕒 Join Group
</div>


          </div>
        </a>
      </div>
    </div>

    <!-- Right Image + Title below it -->
    <div class="banner-right-col ms-auto d-flex flex-column align-items-center justify-content-center gap-1" style="flex: 0 0 105px; min-width: 90px;" aria-hidden="false">
      <div class="banner-image">
        <img src="/assets/img/care.jpg" alt="Customer care image" loading="lazy" class="rounded-circle banner-care-img">
      </div>
      <div class="banner-title-sm" style="
        font-weight: 900;
        font-size: 11.5px;
        color: #fff;
        padding: 3px 8px;
        border-radius: 999px;
        background: rgba(255,255,255,0.22);
        border: 1px solid rgba(255,255,255,0.3);
        backdrop-filter: blur(4px);
        text-align: center;
        white-space: nowrap;
        box-shadow: 0 3px 8px rgba(0,0,0,0.12);
        display: inline-flex;
        align-items: center;
        gap: 4px;
      ">
        <i class="bx bx-phone-call" style="font-size: 12px;"></i>
        Customer Care
      </div>
    </div>
  </div>

  <!-- ============ DEPOSIT NOT RECEIVED — WHATSAPP US (ONLY IF USER HAS PENDING) ============ -->
  <?php if ($_hasPendingDeposit) {
      // Build smarter WhatsApp prefill with user's own pending txn info
      $_waUserMsg = "Hello! Goodprint deposit abhi tak pending hai. Please check aur jaldi resolve karein.%0A%0A";
      $_waUserMsg .= "User Phone: " . rawurlencode($udata['phone'] ?? '') . "%0A";
      $_waUserMsg .= "Total Pending Amount: Rs " . number_format((float)$_pendingDepositTotal, 2) . "%0A";
      $_waUserMsg .= "Total Pending Requests: " . (int)$_pendingDepositCount . "%0A%0A";
      $_waUserMsg .= "Pending Transactions:%0A";
      foreach ($_pendingTxns as $_idx => $_t) {
          $_waUserMsg .= ($_idx + 1) . ". Order ID: " . rawurlencode($_t['txn_id'] ?? '')
                       . " | Amount: Rs " . rawurlencode($_t['amount'] ?? '')
                       . " | Mode: " . rawurlencode($_t['PAYMENTMODE'] ?? '');
          if (!empty($_t['BANKTXNID'])) $_waUserMsg .= " | UTR/Bank: " . rawurlencode($_t['BANKTXNID']);
          if (!empty($_t['date']))     $_waUserMsg .= " | Date: " . rawurlencode($_t['date']);
          $_waUserMsg .= "%0A";
      }
      $_depositWaLinkSmart = "https://wa.me/" . $_depositWa . "?text=" . $_waUserMsg;
  ?>
  <div class="deposit-alert-wrapper">
    <div class="deposit-alert" role="alert" aria-live="polite">
      <div class="deposit-alert-inner">

        <!-- LEFT: Icon + Text -->
        <div class="deposit-alert-left">
          <div class="deposit-alert-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 2.25a7.75 7.75 0 0 0-7.75 7.75v3.58c0 .62-.22 1.22-.62 1.7l-1.12 1.34A1.75 1.75 0 0 0 3.9 18.75h16.2a1.75 1.75 0 0 0 1.39-2.13l-1.12-1.34a2.75 2.75 0 0 1-.62-1.7v-3.58A7.75 7.75 0 0 0 12 2.25Z" fill="#ff1a1a" stroke="#ff1a1a" stroke-width="0.3"/>
              <path d="M10 19.25a2 2 0 0 0 4 0" stroke="#ff1a1a" stroke-width="1.6" stroke-linecap="round"/>
              <path d="M12 6.5v4.5" stroke="#fff200" stroke-width="2.2" stroke-linecap="round"/>
              <circle cx="12" cy="13.25" r="1.1" fill="#fff200"/>
            </svg>
          </div>
          <div class="deposit-alert-text">
            <h2>
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" style="vertical-align:-3px;margin-right:4px;">
                <path d="M12 2L14.4 8.6L21.5 9.2L16 14L17.5 21L12 17.8L6.5 21L8 14L2.5 9.2L9.6 8.6L12 2Z" fill="#fff200" stroke="#fff200" stroke-width="0.4"/>
              </svg>
              ⚠️ Your Deposit is Still Pending!
              <span class="blink">WhatsApp Us Now!</span>
            </h2>
            <p>
              <?php echo (int)$_pendingDepositCount; ?> pending deposit request
              <?php echo ((int)$_pendingDepositCount > 1) ? 's' : ''; ?>
              — total amount
              <strong style="color:#fff200;font-size:16px;">₹<?php echo number_format((float)$_pendingDepositTotal, 2); ?></strong>
              abhi tak wallet me credited nahi hua hai.
            </p>
            <div class="deposit-sub">
              <span class="tag">⏱ Instant Reply</span>
              WhatsApp par click karein —
              <strong>apne saare Order ID, Amount aur UTR</strong>
              hume already share kar diye jayenge
              <span class="tag">💯 100% Secure</span>
            </div>
          </div>
        </div>

        <!-- RIGHT: WhatsApp CTA Button -->
        <div class="deposit-wa-cta">
          <a
            class="deposit-wa-btn"
            href="<?php echo htmlspecialchars($_depositWaLinkSmart); ?>"
            target="_blank"
            rel="noopener noreferrer"
            aria-label="WhatsApp par deposit ke liye contact karein"
          >
            <svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <path d="M19.11 17.205c-.372 0-1.088 1.39-1.518 1.39a.63.63 0 0 1-.315-.1c-.802-.402-1.504-.817-2.163-1.447-.545-.516-1.146-1.29-1.46-1.963a.426.426 0 0 1-.073-.215c0-.33.99-.945.99-1.49 0-.143-.73-2.09-.832-2.335-.143-.372-.214-.487-.6-.487-.187 0-.36-.043-.53-.043-.302 0-.53.115-.746.315-.688.645-1.032 1.318-1.06 2.264v.114c-.015.99.472 1.977 1.017 2.78 1.23 1.82 2.506 3.41 4.554 4.34.616.287 2.035.888 2.722.888.817 0 2.15-.515 2.478-1.318.13-.33.244-.7.244-1.13 0-.458-.043-.744-.272-1.03-.229-.3-.73-.545-1.046-.66Z" fill="#0ca148"/>
              <path fill-rule="evenodd" clip-rule="evenodd" d="M22.573 9.498C20.66 7.586 18.085 6.517 15.38 6.517c-4.873 0-8.84 3.968-8.84 8.841 0 1.557.406 3.077 1.176 4.41L7 25l5.38-1.415a8.8 8.8 0 0 0 4.25 1.094h.004c4.873 0 8.84-3.968 8.84-8.84 0-2.364-.92-4.587-2.6-6.351Zm-7.194 13.156a7.294 7.294 0 0 1-3.715-1.017l-.267-.158-3.193.84.853-3.112-.174-.276a7.293 7.293 0 0 1-1.122-3.905c0-4.033 3.285-7.32 7.32-7.32 1.96 0 3.801.764 5.179 2.142a7.26 7.26 0 0 1 2.14 5.181c0 4.033-3.285 7.32-7.318 7.325Z" fill="#0ca148"/>
            </svg>
            <span class="btn-text">
              <span class="btn-main">WhatsApp Now</span>
              <span class="btn-num"><?php echo htmlspecialchars($_depositWaDisplay); ?></span>
            </span>
          </a>
          <div class="deposit-wa-hint">👇 Click karein — poora detail auto bheja jayega</div>
        </div>

      </div>
    </div>
  </div>
  <?php } // end: only for users with pending deposit ?>
</div>

<!-- ================== SERVICES ================== -->
<?php
$services = [
    // [
    //     "name" => "Add Balance Instant",
    //     "link" => "wallet",
    //     "image" => "../admin/uploads/Services/wallet.png",
    //     "price_key" => "",
    //     "badge" => "Instant"
    // ],
    // [
    //     "name" => "Mobile/DTH Recharge",
    //     "link" => "mobileRecharge",
    //     "image" => "../admin/uploads/Services/recharge2.png",
    //     "price_key" => "",
    //     "badge" => "Instant"
    // ],
     [
        "name" => "Vehicle Services",
        "link" => "vahicle_card_service",
        "image" => "../admin/uploads/Services/Yamaha-Bike-PNG.png",
        "price_key" => "",
        "badge" => ""
    ],
  
    [
        "name" => "Voter Services",
        "link" => "Voter_card_service",
        "image" => "../admin/uploads/Services/voter.png",
        "price_key" => "",
        "badge" => ""
    ],

    [
        "name" => "Mobile Number Details",
        "link" => "mobile_details_service",
        "image" => "../admin/uploads/service_icom/checkop-preview.png",
        "price_key" => "",
        "badge" => "3-in-1"
    ],

    [
        "name" => "Aadhaar Services",
        "link" => "Uid_Flx-Services",
        "image" => "../admin/uploads/Services/aadhar.png",
        "price_key" => "",
        "badge" => ""
    ],
    [
        "name" => "DBT Seeding Status",
        "link" => "dbt_seeding_status",
        "image" => "../admin/uploads/Services/npci.png",
        "price_key" => "",
        "badge" => "₹20"
    ],

     [
        "name" => "Pan Services",
        "link" => "pan_card_service",
        "image" => "../admin/uploads/Services/panservices.png",
        "price_key" => "",
        "badge" => ""
    ],
    
     [
        "name" => "Samagra Portal Services",
        "link" => "Samagra_card_service",
        "image" => "https://goodprintportal.in/smg.png",
        "price_key" => "",
        "badge" => ""
    ],
    
    
    [
        "name" => "TRICK Buy Services",
        "link" => "trick_buy_card",
        "image" => "https://goodprintportal.in/trick.png",
        "price_key" => "",
        "badge" => "New"
    ],
        [
        "name" => "Ration Services",
        "link" => "Ration_card_service",
        "image" => "../admin/uploads/Services/ration1.png",
        "price_key" => "",
        "badge" => ""
    ],
    [
        "name" => "Farmer Services",
        "link" => "FarmerStackService",
        "image" => "../admin/uploads/Services/farmerLogo.png",
        "price_key" => "",
        "badge" => ""
    ],
    
    //  [
    //     "name" => "JanAdhaar Services",
    //     "link" => "JanAdhaarServices",
    //     "image" => "../admin/uploads/Services/janaadhar.png",
    //     "price_key" => "",
    //     "badge" => ""
    // ],
    
     
    
     [
        "name" => "Passbook Services",
        "link" => "Passbook_card_service",
        "image" => "../admin/uploads/Services/1771198.png",
        "price_key" => "",
        "badge" => ""
    ],
    
     [
        "name" => "Other Services",
        "link" => "Other_card_service",
        "image" => "../admin/uploads/Services/dlpng.png",
        "price_key" => "",
        "badge" => ""
    ],
    
    [
          "name" => "Chassis To Rc Find",
        "link" => "chassis_to_rc",
        "image" => "../admin/uploads/service_icom/dlfind022.png",
        "price_key" => "",
        "badge" => ""
    ],
    [
        "name" => "LL Exam Request",
        "link" => "ll_request",
        "image" => "https://goodprintportal.in/ll.png",
        "price_key" => "",
        "badge" => "₹80"
    ],
    [
        "name" => "Instant DL PDF",
        "link" => "dl_pdf_card_service",
        "image" => "https://goodprintportal.in/dl.png",
        "price_key" => "",
        "badge" => ""
    ],
    [
        "name" => "Instant LL PDF",
        "link" => "instant_ll_pdf",
        "image" => "https://goodprintportal.in/llp.png",
        "price_key" => "",
        "badge" => ""
    ],
     [
        "name" => "Instant Voter Number Link ",
        "link" => "voter_m_link_w_otp",
        "image" => "https://goodprintportal.in/vl.png",
        "price_key" => "",
        "badge" => ""
    ],
     [
        "name" => "Tec Exam Pass ",
        "link" => "tec_exam_req",
        "image" => "https://goodprintportal.in/tec.png",
        "price_key" => "",
        "badge" => ""
    ],
     [
        "name" => "Vehical To Mobile Find ",
        "link" => "vehicle_to_mobile_find",
        "image" => "https://goodprintportal.in/vtm.png",
        "price_key" => "",
        "badge" => ""
    ],
     [
        "name" => "Adhar To Pan Find S1 ",
        "link" => "pan_find",
        "image" => "../admin/uploads/Services/panservices.png",
        "price_key" => "",
        "badge" => ""
    ],
    [
        "name" => "Adhar To Pan Find S2 ",
        "link" => "pan_find_server2",
        "image" => "../admin/uploads/Services/panservices.png",
        "price_key" => "",
        "badge" => ""
    ],
     [
        "name" => "Pan To Adhar Find ",
        "link" => "pan_find_server2",
        "image" => "../admin/uploads/service_icom/uidai.png",
        "price_key" => "",
        "badge" => ""
    ],
     [
        "name" => "Pan Details ",
        "link" => "pan_details_verify",
        "image" => "../admin/uploads/service_icom/panf.png",
        "price_key" => "",
        "badge" => ""
    ],
    // ---- 13 Pariprint products: one page admin/pp_service.php, price from `pricing` (includes/pp_extra_services.php) ----
    ["name" => "Udyam No to Udyam PDF", "link" => "pp_service.php?s=udyam-to-udyam-pdf", "image" => "../admin/uploads/service_icom/pp_udyam-pdf.svg", "price_key" => "pp_udyam_to_pdf_fee", "badge" => "New"],
    ["name" => "DL to Mobile Find", "link" => "pp_service.php?s=dl-to-mobile-find", "image" => "../admin/uploads/service_icom/pp_dl-mobile.svg", "price_key" => "pp_dl_to_mobile_fee", "badge" => "New"],
    ["name" => "PAN to Mask Aadhaar", "link" => "pp_service.php?s=pan-to-mask-aadhaar", "image" => "../admin/uploads/service_icom/pp_mask-aadhaar.svg", "price_key" => "pp_pan_to_mask_aadhaar_fee", "badge" => "New"],
    ["name" => "Vahan to Mobile", "link" => "pp_service.php?s=vahan-to-mobile", "image" => "../admin/uploads/service_icom/pp_vahan-mobile.svg", "price_key" => "pp_vahan_to_mobile_fee", "badge" => "New"],
    ["name" => "Voter Advance", "link" => "pp_service.php?s=voter-advance", "image" => "../admin/uploads/service_icom/pp_voter-advance.svg", "price_key" => "pp_voter_advance_fee", "badge" => "New"],
    ["name" => "ABHA Card Download", "link" => "pp_service.php?s=abha-card-download", "image" => "../admin/uploads/service_icom/pp_abha.svg", "price_key" => "pp_abha_card_fee", "badge" => "New"],
    ["name" => "LPG Details", "link" => "pp_service.php?s=lpg-details", "image" => "../admin/uploads/service_icom/pp_lpg.svg", "price_key" => "pp_lpg_details_fee", "badge" => "New"],
    ["name" => "Pincode Details", "link" => "pp_service.php?s=pincode-details", "image" => "../admin/uploads/service_icom/pp_pincode.svg", "price_key" => "pp_pincode_details_fee", "badge" => "New"],
    ["name" => "IFSC Details", "link" => "pp_service.php?s=ifsc-details", "image" => "../admin/uploads/service_icom/pp_ifsc.svg", "price_key" => "pp_ifsc_details_fee", "badge" => "New"],
    ["name" => "UAN Employment History", "link" => "pp_service.php?s=uan-employment-history", "image" => "../admin/uploads/service_icom/pp_uan.svg", "price_key" => "pp_uan_history_fee", "badge" => "New"],
    ["name" => "Ration to Aadhaar Number", "link" => "pp_service.php?s=ration-to-aadhaar", "image" => "../admin/uploads/service_icom/pp_ration-aadhaar.svg", "price_key" => "pp_ration_to_aadhaar_fee", "badge" => "New"],
    ["name" => "PAN to Insurance", "link" => "pp_service.php?s=pan-to-insurance", "image" => "../admin/uploads/service_icom/pp_insurance.svg", "price_key" => "pp_pan_to_insurance_fee", "badge" => "New"],
    ["name" => "TIN Details", "link" => "pp_service.php?s=tin-details", "image" => "../admin/uploads/service_icom/pp_tin.svg", "price_key" => "pp_tin_details_fee", "badge" => "New"],
    // [
    //     "name" => "FamilyID Services",
    //     "link" => "HrFaimlyCard",
    //     "image" => "../admin/uploads/Services/familyidLogo.png",
    //     "price_key" => "",
    //     "badge" => ""
    // ],
    
    //  [
    //     "name" => "Aadhaar Seeding",
    //     "link" => "npci_status_check",
    //     "image" => "../admin/uploads/Services/npci.png",
    //     "price_key" => "",
    //     "badge" => ""
    // ],

];

// Filter: Hide TRICK Buy Services card from non-admin users when service is disabled in settings
$_trickEnabled = isset($webdata['trick_service_enabled']) ? (int)$webdata['trick_service_enabled'] : 1;
$_trickIsAdmin = false;
if (!empty($udata)) {
    $_trickRole = strtolower(trim((string)($udata['role'] ?? $udata['type'] ?? '')));
    $_trickIsAdmin = ($_trickRole === 'admin' || $_trickRole === 'super admin' || $_trickRole === 'super_admin' || !empty($udata['is_admin']));
}
if ($_trickEnabled !== 1 && !$_trickIsAdmin) {
    $services = array_values(array_filter($services, function($svc) {
        return !isset($svc['link']) || strpos((string)$svc['link'], 'trick_buy') !== 0;
    }));
}
unset($_trickEnabled, $_trickIsAdmin, $_trickRole);

// Prices (pp_extra_setup adds missing pricing rows of the 13 Pariprint products; existing prices are kept)
require_once __DIR__ . '/../../includes/pp_extra_services.php';
pp_extra_setup($ahk_conn);
$prices = [];
$res = mysqli_query($ahk_conn, "SELECT service_name, price FROM pricing");
while ($row = mysqli_fetch_assoc($res)) {
    $prices[$row['service_name']] = $row['price'];
}
?>
<!-- Services (Full Width) -->
<div class="services-section">
  <div class="text-center mb-4">
    <h2>⚡ Explore 40+ Services</h2>
    <h3>✅ Reliable | 🔒 Secure | 🚀 Fast</h3>
  </div>

  <div class="row g-4 justify-content-center">
    <?php foreach ($services as $service): ?>
      <div class="col-custom-5 d-flex">
        <a href="<?php echo $service['link']; ?>" 
           <?php echo (strpos($service['link'], 'http') === 0) ? 'target="_blank"' : ''; ?> 
           class="text-decoration-none w-100">
          <div class="service-card w-100">

            <!-- Badge -->
            <?php if (!empty($service['badge'])): ?>
              <span class="badge-new"><?php echo $service['badge']; ?></span>
            <?php endif; ?>

            <!-- Price -->
            <?php if (!empty($prices[$service['price_key']])): ?>
              <span class="price-badge">
                ₹<?php echo htmlspecialchars($prices[$service['price_key']]); ?>
              </span>
            <?php endif; ?>

            <!-- Icon -->
            <div class="service-icon">
              <img src="<?php echo $service['image']; ?>" alt="icon">
            </div>

            <!-- Title -->
            <div class="service-title"><?php echo $service['name']; ?></div>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>
</div>


<?php include('../admin/footers.php'); ?>






	
<!-- Scripts -->
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/js/jquery.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/plugins/chartjs/chart.min.js"></script>
<script src="../template/ahkweb/assets/plugins/vectormap/jquery-jvectormap-2.0.2.min.js"></script>
<script src="../template/ahkweb/assets/plugins/vectormap/jquery-jvectormap-world-mill-en.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>
</body>
</html>
