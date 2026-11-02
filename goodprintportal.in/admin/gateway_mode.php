<?php
@ini_set('display_errors', 0);
@ini_set('display_startup_errors', 0);
error_reporting(0);
header('Content-Type: text/html; charset=UTF-8');

include_once(__DIR__.'/../includes/session.php');
include_once(__DIR__.'/../includes/config.php');

$_utype = isset($udata['type']) ? $udata['type'] : '';
if (function_exists('checkAdmin')) {
    if (!checkAdmin($_utype)) {
        echo '<script>window.location="index.php";</script>';
        die();
    }
}

function _gmEnsureSchema($conn) {
    static $done = false;
    if ($done) return;
    @mysqli_report(MYSQLI_REPORT_OFF);
    $cols = array();
    $r = @mysqli_query($conn, "SHOW COLUMNS FROM settings");
    if ($r) while ($row = @mysqli_fetch_assoc($r)) $cols[] = $row['Field'];
    if (!in_array('gateway_mode', $cols)) @mysqli_query($conn, "ALTER TABLE settings ADD COLUMN gateway_mode VARCHAR(20) NOT NULL DEFAULT 'gateway'");
    if (!in_array('manual_upi_id', $cols)) @mysqli_query($conn, "ALTER TABLE settings ADD COLUMN manual_upi_id VARCHAR(255) NULL DEFAULT NULL");
    if (!in_array('manual_qr_path', $cols)) @mysqli_query($conn, "ALTER TABLE settings ADD COLUMN manual_qr_path VARCHAR(500) NULL DEFAULT NULL");
    if (!in_array('manual_qr_updated_at', $cols)) @mysqli_query($conn, "ALTER TABLE settings ADD COLUMN manual_qr_updated_at DATETIME NULL DEFAULT NULL");
    if (!in_array('gateway_mode', $cols)) @mysqli_query($conn, "UPDATE settings SET gateway_mode='gateway' WHERE id=1 LIMIT 1");
    $done = true;
}
_gmEnsureSchema($ahk_conn);

$gwModes = array(
    'vapay'  => array('VAPay UPI Gateway', 'va.pdfgods.in — auto verified via webhook, wallet credited instantly.', 'fa-solid fa-bolt', 'text-success', '../vapay/wallet.php'),
    'akpdf'  => array('AKPDF AutoUPI', 'akpdf.site auto gateway.', 'fa-solid fa-globe', 'text-primary', '../akpdf/wallet.php'),
    'akqr'   => array('AKQR Gateway', 'AKQR auto gateway.', 'fa-solid fa-qrcode', 'text-info', '../akqr/wallet.php'),
    'drpay'  => array('DrPay Gateway', 'DrPay auto gateway.', 'fa-solid fa-credit-card', 'text-danger', '../drpay/wallet2.php'),
    'manual' => array('Manual QR / UPI', 'User pays to your UPI, submits UTR, admin approves.', 'fa-solid fa-hand-holding-dollar', 'text-warning', ''),
);

$actionMsg = '';
$actionType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_mode'])) {
        $mode_g = isset($_POST['gateway_mode']) ? strtolower(trim((string)$_POST['gateway_mode'])) : '';
        if (!isset($gwModes[$mode_g])) $mode_g = 'akpdf';
        if ($gwModes[$mode_g][4] !== '' && !file_exists(__DIR__ . '/' . $gwModes[$mode_g][4])) {
            $mode_g = '';
            $actionMsg = 'Gateway files not found on this server. Mode not changed.';
            $actionType = 'error';
        }
        $upd = ($mode_g !== '') ? @mysqli_prepare($ahk_conn, "UPDATE settings SET gateway_mode = ? WHERE id=1 LIMIT 1") : false;
        if ($upd) {
            @mysqli_stmt_bind_param($upd, 's', $mode_g);
            if (@mysqli_stmt_execute($upd)) {
                $actionMsg = 'Gateway Mode updated successfully!';
            } else {
                $actionMsg = 'Mode update failed.';
                $actionType = 'error';
            }
            @mysqli_stmt_close($upd);
        }
    }

    if (isset($_POST['save_upi'])) {
        $upi = isset($_POST['manual_upi_id']) ? trim($_POST['manual_upi_id']) : '';
        $upiSafe = getSafe($upi);
        $upd = @mysqli_prepare($ahk_conn, "UPDATE settings SET manual_upi_id = ? WHERE id=1 LIMIT 1");
        if ($upd) {
            @mysqli_stmt_bind_param($upd, 's', $upiSafe);
            if (@mysqli_stmt_execute($upd)) {
                $actionMsg = 'UPI ID updated successfully!';
            } else {
                $actionMsg = 'UPI update failed.';
                $actionType = 'error';
            }
            @mysqli_stmt_close($upd);
        }
    }

    if (isset($_FILES['qr_image']) && is_array($_FILES['qr_image']) && isset($_FILES['qr_image']['error']) && $_FILES['qr_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['qr_image'];
        $allowed = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
        $ftype = isset($file['type']) ? $file['type'] : '';
        if (!in_array($ftype, $allowed)) {
            $actionMsg = 'Only JPG, PNG, GIF, WEBP images allowed!';
            $actionType = 'error';
        } else {
            $uploadDir = __DIR__ . '/uploads/manual_qr/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
                @file_put_contents($uploadDir . 'index.php', "<?php\nheader('HTTP/1.1 403 Forbidden');\nexit;\n");
            }
            $ext = '';
            if (!empty($file['name'])) $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!$ext) {
                $mimeMap = array('image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp');
                $ext = isset($mimeMap[$ftype]) ? $mimeMap[$ftype] : 'png';
            }
            $fname = 'manual_qr_' . time() . '.' . $ext;
            $fpath = $uploadDir . $fname;
            $tmp_name = isset($file['tmp_name']) ? $file['tmp_name'] : '';
            if ($tmp_name && @move_uploaded_file($tmp_name, $fpath)) {
                $relPath = 'uploads/manual_qr/' . $fname;
                $upd = @mysqli_prepare($ahk_conn, "UPDATE settings SET manual_qr_path = ?, manual_qr_updated_at = NOW() WHERE id=1 LIMIT 1");
                if ($upd) {
                    @mysqli_stmt_bind_param($upd, 's', $relPath);
                    if (@mysqli_stmt_execute($upd)) {
                        $actionMsg = 'QR Code uploaded successfully!';
                    } else {
                        $actionMsg = 'QR DB save failed.';
                        $actionType = 'error';
                    }
                    @mysqli_stmt_close($upd);
                }
            } else {
                $actionMsg = 'Failed to save uploaded file. Check permissions.';
                $actionType = 'error';
            }
        }
    }
}

$gwSet = array('gateway_mode' => 'gateway', 'manual_upi_id' => '', 'manual_qr_path' => '', 'manual_qr_updated_at' => '');
$_rr = @mysqli_query($ahk_conn, "SELECT gateway_mode, manual_upi_id, manual_qr_path, manual_qr_updated_at FROM settings WHERE id=1 LIMIT 1");
if ($_rr && ($_rw = @mysqli_fetch_assoc($_rr))) {
    foreach ($gwSet as $k => $v) if (isset($_rw[$k])) $gwSet[$k] = $_rw[$k];
}

$curMode = strtolower(trim((string)$gwSet['gateway_mode']));
if ($curMode === 'gateway' || !isset($gwModes[$curMode])) $curMode = 'akpdf'; // old value 'gateway' = AKPDF
$curUpi = $gwSet['manual_upi_id'];
$curQr = $gwSet['manual_qr_path'];

include(__DIR__.'/../template/ahkweb/header.php');
if ($actionMsg !== '') showAlert('', $actionMsg, $actionType);
?>
<style>
.mode-card { border-radius:16px; transition:all .3s; border:3px solid transparent; cursor:pointer; }
.mode-card.active { border-color:#4e54c8; box-shadow:0 8px 24px rgba(78,84,200,0.25); background:linear-gradient(135deg,#eef2ff 0%,#e0e7ff 100%); }
.mode-card:hover { transform:translateY(-3px); box-shadow:0 6px 18px rgba(0,0,0,0.08); }
.mode-icon { font-size:48px; margin-bottom:10px; }
.badge-mode-gateway { background:linear-gradient(135deg,#0ea5e9,#2563eb); color:#fff; padding:6px 14px; border-radius:20px; font-weight:700; }
.badge-mode-manual { background:linear-gradient(135deg,#f59e0b,#d97706); color:#fff; padding:6px 14px; border-radius:20px; font-weight:700; }
.qr-preview { max-width:220px; border:2px dashed #cbd5e1; border-radius:12px; padding:10px; background:#fff; }
.upi-box { background:linear-gradient(135deg,#f8fafc,#e2e8f0); border-radius:12px; padding:14px 18px; font-family:'Courier New',monospace; font-size:16px; font-weight:700; color:#1e293b; border:1px solid #cbd5e1; }
</style>

<div class="page-wrapper">
    <div class="page-content">
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3">Admin Control</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="index.php"><i class="bx bx-home-alt"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Gateway Mode Settings</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="row">
            <div class="col-12 col-xl-8">
                <div class="card radius-10">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
                            <div>
                                <h5 class="mb-0"><i class="fa-solid fa-toggle-on text-warning me-2"></i>Payment Gateway Mode</h5>
                                <p class="text-muted mb-0 small">Choose which payment gateway users see on the Add Money page.</p>
                            </div>
                            <div>
                                <span class="<?= ($curMode === 'manual') ? 'badge-mode-manual' : 'badge-mode-gateway' ?>"><i class="<?= $gwModes[$curMode][2] ?> me-1"></i><?= htmlspecialchars($gwModes[$curMode][0]) ?> Active</span>
                            </div>
                        </div>

                        <form method="POST">
                            <div class="row g-3 mb-4">
                                <?php foreach ($gwModes as $mKey => $m) {
                                    $mFileOk = ($m[4] === '') || file_exists(__DIR__ . '/' . $m[4]); ?>
                                <div class="col-md-6 col-xxl-4">
                                    <label class="mode-card card h-100 <?= ($curMode === $mKey) ? 'active' : '' ?>" style="padding:20px;display:block;<?= $mFileOk ? '' : 'opacity:.5;' ?>">
                                        <input type="radio" name="gateway_mode" value="<?= $mKey ?>" class="d-none" <?= ($curMode === $mKey) ? 'checked' : '' ?> <?= $mFileOk ? '' : 'disabled' ?>>
                                        <div class="text-center">
                                            <div class="mode-icon <?= $m[3] ?>"><i class="<?= $m[2] ?>"></i></div>
                                            <h6 class="fw-bold mb-1"><?= htmlspecialchars($m[0]) ?></h6>
                                            <p class="text-muted small mb-0"><?= htmlspecialchars($m[1]) ?></p>
                                            <div class="mt-3">
                                                <?php if (!$mFileOk) { ?><span class="badge bg-secondary">Files missing</span>
                                                <?php } elseif ($mKey === 'manual') { ?><span class="badge bg-warning text-dark">Admin Approval</span>
                                                <?php } else { ?><span class="badge bg-primary">Auto Verify</span><?php } ?>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                                <?php } ?>
                            </div>

                            <button type="submit" name="save_mode" class="btn btn-primary px-4 fw-bold">
                                <i class="fa-solid fa-save me-2"></i>Save Mode
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card radius-10 mt-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <h6 class="mb-0 fw-bold"><i class="fa-solid fa-credit-card text-success me-2"></i>Manual Payment Settings</h6>
                        </div>
                        <p class="text-muted small mb-4">These settings are used when <b>Manual Mode</b> is active. Configure your UPI ID and upload QR code image.</p>

                        <form method="POST">
                            <div class="mb-4">
                                <label class="form-label fw-bold">UPI ID <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-id-card"></i></span>
                                    <input type="text" name="manual_upi_id" class="form-control form-control-lg"
                                           placeholder="yourbusiness@upi  (example: anuprint@paytm)"
                                           value="<?= htmlspecialchars($curUpi) ?>" required>
                                </div>
                                <div class="form-text">Enter your business UPI ID where users will send money.</div>
                            </div>

                            <button type="submit" name="save_upi" class="btn btn-success px-4 fw-bold mb-5">
                                <i class="fa-solid fa-check me-2"></i>Update UPI ID
                            </button>
                        </form>

                        <hr>

                        <form method="POST" enctype="multipart/form-data">
                            <div class="row g-4 align-items-start">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Upload QR Code Image</label>
                                    <input type="file" name="qr_image" class="form-control" accept="image/*">
                                    <div class="form-text mt-2">Upload your UPI QR code (JPG, PNG, GIF, WEBP). Max size as per server.</div>
                                    <button type="submit" class="btn btn-info text-white px-4 fw-bold mt-3">
                                        <i class="fa-solid fa-cloud-arrow-up me-2"></i>Upload QR
                                    </button>
                                </div>
                                <div class="col-md-6 text-center">
                                    <label class="form-label fw-bold d-block">Current QR Preview</label>
                                    <?php if (!empty($curQr) && file_exists(__DIR__ . '/' . $curQr)) { ?>
                                        <img src="<?= htmlspecialchars($curQr) ?>" alt="Manual QR" class="qr-preview">
                                        <?php if (!empty($gwSet['manual_qr_updated_at'])) { ?>
                                            <div class="small text-muted mt-2">Updated: <?= date('d M Y, h:i A', strtotime($gwSet['manual_qr_updated_at'])) ?></div>
                                        <?php } ?>
                                    <?php } else { ?>
                                        <div class="qr-preview d-flex align-items-center justify-content-center" style="height:220px;color:#94a3b8;">
                                            <div>
                                                <i class="fa-solid fa-image" style="font-size:60px;"></i>
                                                <div class="mt-2 small">No QR uploaded yet</div>
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </form>

                        <?php if (!empty($curUpi)) { ?>
                            <hr class="mt-5">
                            <div class="bg-light p-4 rounded-12">
                                <label class="fw-bold d-block mb-2"><i class="fa-solid fa-eye text-info me-2"></i>Live Preview (what users see)</label>
                                <div class="upi-box mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <span><i class="fa-solid fa-id-card text-primary me-2"></i><?= htmlspecialchars($curUpi) ?></span>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($curUpi) ?>');this.innerHTML='<i class=\'fa-solid fa-check\'></i> Copied';setTimeout(()=>this.innerHTML='<i class=\'fa-solid fa-copy\'></i> Copy',1500);"><i class="fa-solid fa-copy"></i> Copy</button>
                                </div>
                                <div class="text-muted small">When Manual Mode is active, users see this UPI + your QR on payment page. They pay and submit UTR no. for admin approval.</div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="card radius-10 bg-gradient-primary text-white">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="fa-solid fa-circle-info me-2"></i>Quick Guide</h6>
                        <ol class="mb-0 small" style="padding-left:18px;line-height:1.9;">
                            <li>Select <b>VAPay / AKPDF / AKQR / DrPay</b> for instant, automated payments.</li>
                            <li>Select <b>Manual Mode</b> to receive UPI payments directly.</li>
                            <li>In Manual mode: set your UPI ID & upload QR.</li>
                            <li>Users submit UTR after payment → visible in <a href="qrpay_pending_v2.php?tab=pending" class="text-white text-decoration-underline fw-bold">Pending Requests</a>.</li>
                            <li>Admin Approve → wallet auto credited with audit log.</li>
                            <li>Admin Reject → marked rejected, no wallet credit.</li>
                        </ol>
                    </div>
                </div>

                <div class="card radius-10 mt-4">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="fa-solid fa-bolt text-warning me-2"></i>Current Status Summary</h6>
                        <div class="d-flex justify-content-between mb-2 small">
                            <span class="text-muted">Mode:</span>
                            <span class="fw-bold"><?= htmlspecialchars($gwModes[$curMode][0]) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2 small">
                            <span class="text-muted">UPI Configured:</span>
                            <span class="fw-bold"><?= (!empty($curUpi))?'<span class="text-success">YES</span>':'<span class="text-danger">NO</span>' ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2 small">
                            <span class="text-muted">QR Uploaded:</span>
                            <span class="fw-bold"><?= (!empty($curQr) && file_exists(__DIR__.'/'.$curQr))?'<span class="text-success">YES</span>':'<span class="text-danger">NO</span>' ?></span>
                        </div>
                        <hr>
                        <a href="qrpay_pending_v2.php?tab=pending" class="btn btn-outline-warning w-100 fw-bold mb-2">
                            <i class="fa-solid fa-clock-rotate-left me-2"></i>View Pending Approvals
                        </a>
                        <a href="qrpay_pending_v2.php?tab=all" class="btn btn-outline-info w-100 fw-bold">
                            <i class="fa-solid fa-table-list me-2"></i>All Payment Records
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.mode-card input[type=radio]').forEach(r => {
    r.addEventListener('change', () => {
        document.querySelectorAll('.mode-card').forEach(c => c.classList.remove('active'));
        if (r.checked) r.closest('.mode-card').classList.add('active');
    });
});
</script>

<?php include(__DIR__.'/../template/ahkweb/footer.php'); ?>
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/js/jquery.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>
</body>
</html>
