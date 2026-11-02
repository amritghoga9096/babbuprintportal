<?php
@ob_start();
include('../includes/session.php');
include('../includes/config.php');

if (checkAdmin($udata['type']) == false) {
    ?><script>window.location='index.php';</script><?php die();
}

function _rsEnsureSchema($conn) {
    static $done = null; if ($done === true) return;
    $prevReport = function_exists('mysqli_report') ? @mysqli_report(MYSQLI_REPORT_OFF) : null;
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS settings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        webname VARCHAR(255) DEFAULT NULL,
        ref_bal VARCHAR(20) DEFAULT '10'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $cols = [];
    $r = @mysqli_query($conn, "SHOW COLUMNS FROM settings");
    if ($r) while ($row = mysqli_fetch_assoc($r)) $cols[] = $row['Field'];
    if (!in_array('ref_bal', $cols, true)) {
        $after = in_array('push_notice_wallet_text', $cols, true) ? 'AFTER push_notice_wallet_text' : '';
        @mysqli_query($conn, "ALTER TABLE settings ADD COLUMN ref_bal VARCHAR(20) DEFAULT '10' {$after}");
    }
    if (!in_array('ref_enabled', $cols, true)) {
        $afterCol = in_array('ref_bal', $cols, true) ? 'ref_bal' : (in_array('push_notice_wallet_text', $cols, true) ? 'push_notice_wallet_text' : '');
        $after = $afterCol !== '' ? "AFTER {$afterCol}" : '';
        @mysqli_query($conn, "ALTER TABLE settings ADD COLUMN ref_enabled TINYINT(1) NOT NULL DEFAULT 1 {$after}");
    }
    if ($prevReport !== null) @mysqli_report($prevReport);
    $chk = @mysqli_query($conn, "SELECT id FROM settings ORDER BY id ASC LIMIT 1");
    if (!$chk || mysqli_num_rows($chk) === 0) {
        @mysqli_query($conn, "INSERT INTO settings (id,webname,ref_bal,ref_enabled) VALUES (1,'GoodPrint','10',1)");
    }
    $done = true;
}
_rsEnsureSchema($ahk_conn);

$saved = isset($_GET['saved']) && $_GET['saved'] === '1';
$savedType = isset($_GET['t']) ? trim((string)$_GET['t']) : 'bonus';
$saveError = '';

if (isset($_POST['save_referral_bonus'])) {
    $bonusVal = trim((string)($_POST['ref_bal'] ?? '10'));
    if ($bonusVal === '' || !is_numeric($bonusVal)) $bonusVal = '10';
    if ((float)$bonusVal < 0) $bonusVal = '0';
    $bonusVal = number_format((float)$bonusVal, 2, '.', '');

    $prevReport = function_exists('mysqli_report') ? @mysqli_report(MYSQLI_REPORT_OFF) : null;
    $upd = @mysqli_prepare($ahk_conn, "UPDATE settings SET ref_bal = ? WHERE id=1 LIMIT 1");
    if ($upd) {
        @mysqli_stmt_bind_param($upd, 's', $bonusVal);
        @mysqli_stmt_execute($upd);
        @mysqli_stmt_close($upd);
    }
    if ($prevReport !== null) @mysqli_report($prevReport);

    $GLOBALS['webdata'] = null;
    ?><script>window.location='referral_settings.php?saved=1&t=bonus';</script><?php die();
}

if (isset($_POST['save_referral_toggle'])) {
    $enableVal = isset($_POST['ref_enabled']) && in_array(trim((string)$_POST['ref_enabled']), ['1','on','true','yes'], true) ? 1 : 0;
    $prevReport = function_exists('mysqli_report') ? @mysqli_report(MYSQLI_REPORT_OFF) : null;
    $upd = @mysqli_prepare($ahk_conn, "UPDATE settings SET ref_enabled = ? WHERE id=1 LIMIT 1");
    if ($upd) {
        @mysqli_stmt_bind_param($upd, 'i', $enableVal);
        @mysqli_stmt_execute($upd);
        @mysqli_stmt_close($upd);
    }
    if ($prevReport !== null) @mysqli_report($prevReport);
    $GLOBALS['webdata'] = null;
    ?><script>window.location='referral_settings.php?saved=1&t=toggle';</script><?php die();
}

$currentRefBal = '10.00';
$refEnabled = 1;
$ws = @mysqli_query($ahk_conn, "SELECT ref_bal, ref_enabled FROM settings WHERE id=1 LIMIT 1");
if ($ws && ($wr = mysqli_fetch_assoc($ws))) {
    if (is_numeric($wr['ref_bal'] ?? '')) $currentRefBal = number_format((float)$wr['ref_bal'], 2, '.', '');
    $refEnabled = (int)($wr['ref_enabled'] ?? 1);
} elseif (is_array($webdata ?? null)) {
    if (isset($webdata['ref_bal']) && is_numeric($webdata['ref_bal'])) $currentRefBal = number_format((float)$webdata['ref_bal'], 2, '.', '');
    $refEnabled = isset($webdata['ref_enabled']) ? (int)$webdata['ref_enabled'] : 1;
}

$totalReferralsGiven = 0;
$totalReferralAmount = 0;
$tr = @mysqli_query($ahk_conn, "SELECT COUNT(*) AS cnt, COALESCE(SUM(CAST(referral_balance AS DECIMAL(15,2))),0) AS amt FROM referal WHERE status='1'");
if ($tr && ($trr = mysqli_fetch_assoc($tr))) {
    $totalReferralsGiven = (int)($trr['cnt'] ?? 0);
    $totalReferralAmount = (float)($trr['amt'] ?? 0);
}

$pageTitle = 'Referral Bonus Settings | GoodPrint Admin';
include('../template/ahkweb/header.php');
?>

<style>
    .rs-gradient-card {
        background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%) !important;
        color: #fff !important;
        border: 0 !important;
    }
    .rs-gradient-card .card-title,
    .rs-gradient-card .display-5,
    .rs-gradient-card .lead { color: #fff !important; }
    .rs-toggle-card-on {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
        color: #fff !important;
        border: 0 !important;
    }
    .rs-toggle-card-off {
        background: linear-gradient(135deg, #64748b 0%, #475569 100%) !important;
        color: #fff !important;
        border: 0 !important;
    }
    .rs-toggle-card-on .card-title,
    .rs-toggle-card-on .display-4,
    .rs-toggle-card-off .card-title,
    .rs-toggle-card-off .display-4 { color: #fff !important; }
    .rs-switch-wrap { display: inline-flex; align-items: center; gap: 16px; }
    .rs-switch { position: relative; display: inline-block; width: 68px; height: 36px; }
    .rs-switch input { opacity: 0; width: 0; height: 0; }
    .rs-slider { position: absolute; cursor: pointer; inset: 0; background-color: #94a3b8; transition: .3s; border-radius: 999px; }
    .rs-slider:before { position: absolute; content: ""; height: 28px; width: 28px; left: 4px; bottom: 4px; background-color: #fff; transition: .3s; border-radius: 50%; box-shadow: 0 2px 6px rgba(0,0,0,0.25); }
    .rs-switch input:checked + .rs-slider { background: linear-gradient(135deg, #10b981, #059669); }
    .rs-switch input:checked + .rs-slider:before { transform: translateX(32px); }
    .rs-switch-ico { font-size: 22px; line-height: 1; }
    @media (max-width: 768px) {
        .rs-info-big { font-size: 13px !important; }
        .rs-switch { width: 58px; height: 32px; }
        .rs-slider:before { height: 24px; width: 24px; }
        .rs-switch input:checked + .rs-slider:before { transform: translateX(26px); }
    }
</style>

<div class="page-wrapper">
    <div class="page-content">

        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3">
                <i class="fa-solid fa-gift me-1" style="color:#a855f7;"></i>
                Admin Control
            </div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="index.php"><i class="bx bx-home-alt"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Referral Bonus Settings</li>
                    </ol>
                </nav>
            </div>
            <div class="ms-auto">
                <a href="referral.php" class="btn btn-sm btn-outline-primary">
                    <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>
                    User Referral Page Khulein
                </a>
                <a href="settings.php" class="btn btn-sm btn-outline-secondary ms-1">
                    <i class="fa-solid fa-sliders me-1"></i>
                    All Settings
                </a>
            </div>
        </div>

        <?php if ($saved): ?>
        <div class="alert alert-success border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-circle-check fa-lg text-success"></i>
                <div>
                    <?php if ($savedType === 'toggle'): ?>
                        <b>Refer & Earn System status update ho gaya!</b> Ab <?php echo $refEnabled === 1 ? 'ON hai — user ko sidebar/header me menu dikhega + bonus milega.' : 'OFF hai — user ko side menu + header icon + referral page access sab disabled.'; ?>
                    <?php else: ?>
                        <b>Referral Bonus Amount save ho gaya!</b> Ab naye referrals pe itna hi bonus credit hoga.
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($saveError !== ''): ?>
        <div class="alert alert-danger border-0 shadow-sm mb-4">
            <b>Save nahi hua:</b> <?= htmlspecialchars($saveError) ?>
        </div>
        <?php endif; ?>

        <!-- 4 Stats Cards (NEW: include Enable/Disable status big card) -->
        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 mb-4">
            <div class="col mb-3 mb-xl-0">
                <div class="card radius-10 <?= $refEnabled === 1 ? 'rs-toggle-card-on' : 'rs-toggle-card-off'; ?>">
                    <div class="card-body">
                        <p class="card-title mb-1 opacity-90" style="font-size: 13px;">System Status</p>
                        <div class="d-flex align-items-center justify-content-between gap-3">
                            <div class="display-4 fw-bold mb-0" style="font-size: 28px !important;">
                                <?= $refEnabled === 1 ? 'ON' : 'OFF' ?>
                            </div>
                            <div class="rs-switch-ico">
                                <?= $refEnabled === 1 ? '<i class="fa-solid fa-circle-check"></i>' : '<i class="fa-solid fa-circle-stop"></i>'; ?>
                            </div>
                        </div>
                        <p class="lead mt-1 mb-0 opacity-90" style="font-size: 12px;">
                            <?= $refEnabled === 1 ? 'Referral Menu + Bonus — Sab Active' : 'User ko menu/icon dikhega hi nahi'; ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col mb-3 mb-xl-0">
                <div class="card radius-10 rs-gradient-card">
                    <div class="card-body">
                        <p class="card-title mb-1 opacity-90" style="font-size: 13px;">Current Referral Bonus</p>
                        <div class="display-5 fw-bold mb-0" style="font-size: 30px !important;">
                            ₹<?= htmlspecialchars($currentRefBal) ?>
                        </div>
                        <p class="lead mt-1 mb-0 opacity-85" style="font-size: 12px;">per successful referral</p>
                    </div>
                </div>
            </div>
            <div class="col mb-3 mb-xl-0">
                <div class="card radius-10" style="background: linear-gradient(135deg, #ec4899 0%, #f43f5e 100%); color:#fff; border:0;">
                    <div class="card-body">
                        <p class="card-title mb-1 opacity-90" style="font-size: 13px;">Total Payout</p>
                        <div class="display-5 fw-bold mb-0" style="font-size: 30px !important;">
                            ₹<?= number_format($totalReferralAmount, 2) ?>
                        </div>
                        <p class="lead mt-1 mb-0 opacity-85" style="font-size: 12px;">lifetime bonus paid</p>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card radius-10" style="background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%); color:#fff; border:0;">
                    <div class="card-body">
                        <p class="card-title mb-1 opacity-90" style="font-size: 13px;">Successful Referrals</p>
                        <div class="display-5 fw-bold mb-0" style="font-size: 30px !important;">
                            <?= number_format($totalReferralsGiven) ?>
                        </div>
                        <p class="lead mt-1 mb-0 opacity-85" style="font-size: 12px;">users activated bonus</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- TOGGLE SYSTEM CARD (NEW) -->
        <div class="row mb-4">
            <div class="col-lg-12">
                <div class="card border-0 shadow-sm radius-10">
                    <div class="card-header bg-white border-bottom d-flex align-items-center gap-2">
                        <div style="width: 36px; height: 36px; border-radius: 8px; background: linear-gradient(135deg, <?= $refEnabled === 1 ? '#10b981, #059669' : '#64748b, #475569'; ?>); display: inline-flex; align-items: center; justify-content: center;">
                            <?= $refEnabled === 1
                                ? '<i class="fa-solid fa-power-off text-white"></i>'
                                : '<i class="fa-solid fa-toggle-off text-white"></i>'; ?>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0 fw-bold">Refer & Earn System — ON / OFF</h6>
                            <p class="mb-0 small text-muted">Agar OFF kar do — User ko Sidebar Menu + Header Gift Icon + Referral Page Access + Bonus Credit sab BAND ho jayega.</p>
                        </div>
                        <div class="ms-auto">
                            <form method="POST" action="referral_settings.php" id="rsToggleForm" class="m-0 p-0">
                                <div class="rs-switch-wrap">
                                    <span class="small fw-semibold <?= $refEnabled === 1 ? 'text-muted' : 'text-danger'; ?>" style="font-size: 13px;">OFF</span>
                                    <label class="rs-switch" title="System ON / OFF">
                                        <input
                                            type="checkbox"
                                            name="ref_enabled"
                                            id="rsToggleSwitch"
                                            value="1"
                                            <?= $refEnabled === 1 ? 'checked' : ''; ?>>
                                        <span class="rs-slider"></span>
                                    </label>
                                    <span class="small fw-semibold <?= $refEnabled === 1 ? 'text-success' : 'text-muted'; ?>" style="font-size: 13px;">ON</span>
                                </div>
                                <button type="submit" name="save_referral_toggle" class="btn btn-sm btn-outline-dark ms-3 px-4" style="border-radius:8px;">
                                    <i class="fa-solid fa-floppy-disk me-1"></i>
                                    Save Status
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="alert <?= $refEnabled === 1 ? 'alert-success' : 'alert-warning'; ?> border-0 mb-0" role="alert">
                            <div class="d-flex flex-wrap gap-3 align-items-start">
                                <i class="fa-solid <?= $refEnabled === 1 ? 'fa-circle-info text-success' : 'fa-triangle-exclamation text-warning'; ?> fa-lg mt-1"></i>
                                <div>
                                    <?php if ($refEnabled === 1): ?>
                                        <b class="text-success">✅ Refer & Earn System is ACTIVE (ON)</b>
                                        <ul class="mb-0 mt-2 ps-3" style="line-height: 1.9; font-size: 13px;">
                                            <li>User ke Sidebar mein "Refer & Earn" menu <b>dikhega</b>.</li>
                                            <li>Header topbar mein 🎁 Gift Icon <b>dikhega</b> (laptop/desktop screens pe).</li>
                                            <li>User <code>referral.php</code> page access kar sakte hain, apna refer link share kar sakte hain.</li>
                                            <li>Koi first deposit/reg fee payment karne par <b>referral bonus auto credit hoga</b>.</li>
                                        </ul>
                                    <?php else: ?>
                                        <b class="text-warning">⛔ Refer & Earn System is DISABLED (OFF)</b>
                                        <ul class="mb-0 mt-2 ps-3" style="line-height: 1.9; font-size: 13px;">
                                            <li>User ke Sidebar se "Refer & Earn" menu <b>hata diya jayega</b> (dikhna hi band).</li>
                                            <li>Header topbar ka 🎁 Gift Icon <b>gayab ho jayega</b> sab screens pe.</li>
                                            <li>Agar user direct URL <code>referral.php</code> open kare — <b>dashboard pe redirect</b> ho jayega with message.</li>
                                            <li>Koi bhi payment/deposit par referral bonus <b>NAHI milega</b> (helper bhi OFF).</li>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm radius-10">
                    <div class="card-header bg-white border-bottom d-flex align-items-center gap-2">
                        <div style="width: 36px; height: 36px; border-radius: 8px; background: linear-gradient(135deg, #c4b5fd, #a78bfa); display: inline-flex; align-items: center; justify-content: center;">
                            <i class="fa-solid fa-gift text-white"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold">Referral Bonus Amount Set Karein</h6>
                            <p class="mb-0 small text-muted">Admin ye amount set kar sakta hai — jitna set karoge, utna har successful referral pe bonus milega.</p>
                        </div>
                    </div>
                    <div class="card-body p-4 p-md-5">
                        <form method="POST" action="referral_settings.php">
                            <div class="mb-4">
                                <label class="form-label fw-semibold mb-2">
                                    <i class="fa-solid fa-indian-rupee-sign me-1 text-purple"></i>
                                    Referral Bonus Per Successful Referral (₹)
                                </label>
                                <div class="input-group input-group-lg" style="max-width: 380px;">
                                    <span class="input-group-text bg-white" style="border-right: 0;">
                                        <i class="fa-solid fa-gift" style="color:#8b5cf6;"></i>
                                    </span>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        name="ref_bal"
                                        class="form-control form-control-lg fw-bold"
                                        value="<?= htmlspecialchars($currentRefBal) ?>"
                                        placeholder="Example: 10"
                                        style="border-left: 0; font-size: 18px;">
                                    <span class="input-group-text bg-white text-muted">Rupees</span>
                                </div>
                                <div class="form-text mt-2 rs-info-big">
                                    <ul class="mb-0 mt-2 ps-3" style="font-size: 13px; line-height: 1.8;">
                                        <li>
                                            Jab koi <b>naya user</b> kisi purane user ke refer link se register kare, aur usne
                                            <b>first time wallet Deposit / Registration Fee Payment</b> kar diya — to
                                            <b>refer karne wale user ke wallet</b> mein itna amount <b>auto credit</b> ho jayega.
                                        </li>
                                        <li>
                                            Bonus <b>sirf EK HI BAAR</b> har naye user ke liye milega (one-time guard).
                                        </li>
                                        <li>
                                            Agar ye value <b>0</b> rakho — to referral bonus system poori tarah <b>OFF</b> ho jayega.
                                        </li>
                                        <li>
                                            Sirf register karne se bonus <b>nahi</b> milega — Deposit mandatory hai.
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <div class="d-flex gap-2 pt-3 border-top">
                                <button type="submit" name="save_referral_bonus"
                                        class="btn btn-lg px-5 fw-bold"
                                        style="background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%); border:0; color:#fff; box-shadow: 0 4px 12px rgba(139,92,246,0.35);">
                                    <i class="fa-solid fa-floppy-disk me-2"></i>
                                    Save Bonus Amount
                                </button>
                                <a href="index.php" class="btn btn-lg btn-outline-secondary px-4">
                                    Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm radius-10 mb-4" style="background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%); border-left: 4px solid #8b5cf6 !important;">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-3 text-dark">
                            <i class="fa-solid fa-lightbulb me-2" style="color:#a855f7;"></i>
                            System Kaise Kaam Karta Hai
                        </h6>
                        <div class="rs-info-big">
                            <div class="d-flex gap-2 mb-3">
                                <div class="fw-bold text-white rounded-circle d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width:26px; height:26px; font-size:12px; background:#8b5cf6;">1</div>
                                <div>
                                    <b>User A apna Refer Link share karta hai</b><br>
                                    <span class="text-muted small">Link mein User A ka phone base64 encoded hota hai.</span>
                                </div>
                            </div>
                            <div class="d-flex gap-2 mb-3">
                                <div class="fw-bold text-white rounded-circle d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width:26px; height:26px; font-size:12px; background:#8b5cf6;">2</div>
                                <div>
                                    <b>User B us link se Register karta hai</b><br>
                                    <span class="text-muted small">User B ke record mein <code>referred_by = User A</code> save ho jata hai.</span>
                                </div>
                            </div>
                            <div class="d-flex gap-2 mb-3">
                                <div class="fw-bold text-white rounded-circle d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width:26px; height:26px; font-size:12px; background:#8b5cf6;">3</div>
                                <div>
                                    <b>User B first time Deposit karta hai</b><br>
                                    <span class="text-muted small">Wallet add money / Registration fee / Activation fee — koi bhi successful first payment.</span>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <div class="fw-bold text-white rounded-circle d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width:26px; height:26px; font-size:12px; background:#10b981;">✓</div>
                                <div>
                                    <b class="text-success">User A ke wallet mein ₹<?= htmlspecialchars($currentRefBal) ?> Auto Credit!</b><br>
                                    <span class="text-muted small">Sirf 1 baar — phir User B ke future deposits pe koi bonus nahi.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm radius-10">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-3 text-dark">
                            <i class="fa-solid fa-shield-halved me-2 text-success"></i>
                            Anti-Abuse Guards (Already Laga Hua)
                        </h6>
                        <ul class="mb-0 ps-3" style="font-size: 13px; line-height: 1.8;">
                            <li>Har phone number pe sirf <b>1 hi</b> referral bonus entry ban sakti hai — double bonus ka koi chance nahi.</li>
                            <li>Helper function idempotent hai — kitni baar bhi payment callback aaye, <b>sirf 1 hi baar</b> bonus milega.</li>
                            <li>Missing tables/columns — <b>auto create</b> ho jayenge (setup mein dikkat nahi).</li>
                            <li>Khud ko refer (self-refer) — <b>blocked</b> (referred_by !== phone check).</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include('../template/ahkweb/footer.php'); ?>
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>
</body>
</html>
