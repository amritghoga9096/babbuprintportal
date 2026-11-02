<?php
// One page for the 13 Pariprint products: pp_service.php?s=<slug> (see includes/pp_extra_services.php)
include('../includes/session.php');
include('../includes/config.php');
require_once __DIR__ . '/../includes/pp_extra_services.php';
date_default_timezone_set('Asia/Kolkata');

$ppAll = pp_extra_services();
$ppSlug = (string) ($_GET['s'] ?? '');
if (!isset($ppAll[$ppSlug])) {
    header('Location: index.php');
    exit;
}
if (empty($udata['phone'])) {
    header('Location: ../login.php');
    exit;
}
$ppSvc = $ppAll[$ppSlug];
pp_extra_setup($ahk_conn);
$ppFee = pp_extra_fee($ahk_conn, $ppSvc);
$ppPhone = (string) $udata['phone'];
if (empty($_SESSION['pp_csrf'])) $_SESSION['pp_csrf'] = bin2hex(random_bytes(16));
$ppSelf = 'pp_service.php?s=' . rawurlencode($ppSlug);
$ppValues = [];
$ppError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pp_submit'])) {
    foreach ($ppSvc['inputs'] as $name => $in) $ppValues[$name] = (string) ($_POST[$name] ?? '');
    $clean = [];
    if (!hash_equals((string) $_SESSION['pp_csrf'], (string) ($_POST['pp_csrf'] ?? ''))) {
        $ppError = 'Session expire ho gaya. Page refresh karke dobara try karein.';
    } else {
        foreach ($ppSvc['inputs'] as $name => $in) {
            list($val, $err) = pp_extra_clean($in['type'], $ppValues[$name]);
            if ($err !== null) { $ppError = $err; break; }
            $clean[$name] = $val;
        }
    }
    if ($ppError === '' && $ppFee <= 0) $ppError = 'Is service ka price set nahi hai. Admin se contact karein.';

    if ($ppError === '') {
        // 1) debit first (atomic, never below zero) — a double click cannot run two hits on one balance
        $stmt = mysqli_prepare($ahk_conn, 'UPDATE users SET balance = ROUND(CAST(balance AS DECIMAL(12,2)) - ?, 2) WHERE phone = ? AND CAST(balance AS DECIMAL(12,2)) >= ?');
        mysqli_stmt_bind_param($stmt, 'dsd', $ppFee, $ppPhone, $ppFee);
        mysqli_stmt_execute($stmt);
        $debited = mysqli_stmt_affected_rows($stmt) === 1;
        mysqli_stmt_close($stmt);

        if (!$debited) {
            $ppError = 'Wallet balance kam hai. Is service ka charge Rs. ' . number_format($ppFee, 2) . ' hai. Pehle wallet recharge karein.';
        } else {
            @set_time_limit(180);
            ignore_user_abort(true);
            $r = pp_extra_call($ppSlug, $clean, (string) $pariprint_api_key);
            $summary = substr(implode(' / ', $clean), 0, 250);
            $appNo = 'PPX' . date('ymdHis') . random_int(100, 999);

            if (!$r['ok']) {
                // 2a) failed -> money back
                $stmt = mysqli_prepare($ahk_conn, 'UPDATE users SET balance = ROUND(CAST(balance AS DECIMAL(12,2)) + ?, 2) WHERE phone = ?');
                mysqli_stmt_bind_param($stmt, 'ds', $ppFee, $ppPhone);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $status = 'failed';
                $fee = 0.0;
                $json = null;
                $ppError = $r['message'] . ' (Amount wallet me wapas aa gaya.)';
            } else {
                // 2b) success -> wallet history entry
                $status = 'success';
                $fee = $ppFee;
                $json = json_encode($r['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $bres = mysqli_prepare($ahk_conn, 'SELECT balance FROM users WHERE phone = ? LIMIT 1');
                mysqli_stmt_bind_param($bres, 's', $ppPhone);
                mysqli_stmt_execute($bres);
                $brow = db_stmt_fetch_assoc($bres);
                mysqli_stmt_close($bres);
                $newBal = (string) ($brow['balance'] ?? '0');
                $purpose = $ppSvc['name'] . ' - ' . $summary;
                $feeStr = (string) $ppFee;
                $stmt = mysqli_prepare($ahk_conn, "INSERT INTO wallethistory (userid, amount, balance, purpose, status, type) VALUES (?, ?, ?, ?, '1', 'Debit')");
                mysqli_stmt_bind_param($stmt, 'ssss', $ppPhone, $feeStr, $newBal, $purpose);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
            $msg = substr($r['message'], 0, 500);
            $name = $ppSvc['name'];
            $stmt = mysqli_prepare($ahk_conn, 'INSERT INTO pp_extra_requests (application_no, username, slug, service_name, input_summary, fee, status, message, result_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'sssssdsss', $appNo, $ppPhone, $ppSlug, $name, $summary, $fee, $status, $msg, $json);
            mysqli_stmt_execute($stmt);
            $newId = (int) mysqli_insert_id($ahk_conn);
            mysqli_stmt_close($stmt);

            if ($status === 'success') {
                $_SESSION['pp_flash'] = 'Success! Rs. ' . number_format($ppFee, 2) . ' wallet se kat gaya.';
                header('Location: ' . $ppSelf . '&r=' . $newId);
                exit;
            }
        }
    }
}

// result to show: ?r=<id> (own request only)
$ppShow = null;
if (!empty($_GET['r'])) {
    $ppShow = pp_extra_get_request($ahk_conn, (int) $_GET['r'], $ppPhone, $ppSlug);
}
$ppFlash = (string) ($_SESSION['pp_flash'] ?? '');
unset($_SESSION['pp_flash']);

$stmt = mysqli_prepare($ahk_conn, 'SELECT id, application_no, input_summary, fee, status, message, created_at, (result_json IS NOT NULL) AS has_result FROM pp_extra_requests WHERE username = ? AND slug = ? ORDER BY id DESC LIMIT 20');
mysqli_stmt_bind_param($stmt, 'ss', $ppPhone, $ppSlug);
mysqli_stmt_execute($stmt);
$ppHistory = db_stmt_fetch_all_assoc($stmt);
mysqli_stmt_close($stmt);

$balance = (float) ($udata['balance'] ?? 0);
$bq = mysqli_prepare($ahk_conn, 'SELECT balance FROM users WHERE phone = ? LIMIT 1');
mysqli_stmt_bind_param($bq, 's', $ppPhone);
mysqli_stmt_execute($bq);
$brow = db_stmt_fetch_assoc($bq);
mysqli_stmt_close($bq);
if ($brow) $balance = (float) $brow['balance'];

include('../template/ahkweb/header.php');
$h = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
?>
<style>
    body { background: linear-gradient(135deg, #f5f7fc 0%, #eef2f9 100%); }
    .pp-card { background: #fff; border: none; border-radius: 1.5rem; box-shadow: 0 20px 40px -14px rgba(0,0,0,.18); }
    .pp-fee { background: linear-gradient(135deg, #ff9a3c, #ff6a00); color: #fff; font-weight: 700; border-radius: 60px; padding: .45rem 1.6rem; font-size: 1.2rem; display: inline-block; }
    .pp-input { border-radius: 60px; padding: .8rem 1.3rem; border: 2px solid #e2e8f0; }
    .pp-input:focus { border-color: #4361ee; box-shadow: 0 0 0 4px rgba(67,97,238,.15); outline: none; }
    .pp-btn { background: linear-gradient(90deg, #4361ee, #3a56d4); border: none; padding: .85rem; font-weight: 700; border-radius: 60px; color: #fff; }
    .pp-btn:hover { color: #fff; box-shadow: 0 10px 25px rgba(67,97,238,.4); }
    .pp-bal { border-radius: 1rem; border-left: 5px solid #10b981; background: #fff; }
    .pp-icon { width: 84px; height: 84px; object-fit: contain; }
    .pp-card table th, .pp-card table td { font-size: 14px; vertical-align: middle; }
</style>
<div class="page-wrapper">
    <div class="page-content">
        <div class="container-fluid py-4">
            <div class="row justify-content-center">
                <div class="col-lg-11 col-12 d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
                    <div class="d-flex align-items-center gap-3">
                        <img class="pp-icon" src="uploads/service_icom/<?php echo $h($ppSvc['img']); ?>" alt="">
                        <div>
                            <h3 class="fw-bold mb-0"><?php echo $h($ppSvc['name']); ?></h3>
                            <p class="text-muted mt-1 mb-0"><?php echo $h($ppSvc['desc']); ?></p>
                        </div>
                    </div>
                    <a href="index.php" class="btn btn-outline-secondary rounded-pill px-3"><i class="bi bi-arrow-left"></i> Back</a>
                </div>

                <div class="col-lg-4 col-md-5 col-12 mb-4">
                    <div class="card pp-card">
                        <div class="card-body p-4">
                            <div class="text-center mb-3"><span class="pp-fee"><i class="bi bi-tag-fill"></i> Rs. <?php echo number_format($ppFee, 2); ?> / Request</span></div>
                            <div class="pp-bal p-3 mb-4 d-flex justify-content-between align-items-center shadow-sm">
                                <span class="fw-semibold"><i class="bi bi-wallet2 text-success"></i> Balance</span>
                                <span class="fs-5 fw-bold">Rs. <?php echo number_format($balance, 2); ?></span>
                                <a href="wallet.php" class="btn btn-sm btn-outline-secondary rounded-pill">Recharge</a>
                            </div>
                            <form method="POST" action="<?php echo $h($ppSelf); ?>" autocomplete="off" onsubmit="var b=this.querySelector('button[type=submit]');b.disabled=true;b.innerHTML='Please wait...';">
                                <input type="hidden" name="pp_csrf" value="<?php echo $h($_SESSION['pp_csrf']); ?>">
                                <input type="hidden" name="pp_submit" value="1">
                                <?php foreach ($ppSvc['inputs'] as $name => $in): ?>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold mb-1"><?php echo $h($in['label']); ?> <span class="text-danger">*</span></label>
                                        <input type="text" name="<?php echo $h($name); ?>" class="form-control pp-input" placeholder="EX: <?php echo $h($in['ph']); ?>" maxlength="150" required value="<?php echo $h($ppValues[$name] ?? ''); ?>"<?php echo in_array($in['type'], ['udyam', 'dl', 'pan', 'vrn', 'epic', 'ifsc', 'company'], true) ? ' style="text-transform:uppercase"' : ''; ?>>
                                    </div>
                                <?php endforeach; ?>
                                <button type="submit" class="btn pp-btn w-100" <?php echo $balance < $ppFee ? 'disabled' : ''; ?>>
                                    <i class="bi bi-search me-1"></i> <?php echo $balance >= $ppFee ? 'Submit & Pay Rs. ' . number_format($ppFee, 2) : 'Insufficient Balance'; ?>
                                </button>
                                <div class="small text-muted text-center mt-2">Record nahi mila to amount turant wallet me wapas.</div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7 col-md-7 col-12 mb-4">
                    <div class="card pp-card h-100">
                        <div class="card-body p-4">
                            <h4 class="fw-bold mb-3">Result</h4>
                            <?php if ($ppError !== ''): ?>
                                <div class="alert alert-danger"><?php echo $h($ppError); ?></div>
                            <?php endif; ?>
                            <?php if ($ppShow): $data = json_decode((string) $ppShow['result_json'], true); $data = is_array($data) ? $data : []; ?>
                                <?php if ($ppFlash !== ''): ?><div class="alert alert-success"><?php echo $h($ppFlash); ?></div><?php endif; ?>
                                <div class="mb-2 text-muted small">#<?php echo $h($ppShow['application_no']); ?> · <?php echo $h($ppShow['input_summary']); ?> · <?php echo $h($ppShow['created_at']); ?></div>
                                <?php $file = pp_extra_file($data); if ($file): ?>
                                    <?php if ($file['ext'] !== 'pdf'): ?>
                                        <div class="mb-2"><img src="pp_service_file.php?id=<?php echo (int) $ppShow['id']; ?>&inline=1" alt="Card" style="max-width:360px;width:100%;border-radius:10px;border:1px solid #e2e8f0"></div>
                                    <?php endif; ?>
                                    <a class="btn btn-success rounded-pill px-4 mb-3" href="pp_service_file.php?id=<?php echo (int) $ppShow['id']; ?>"><i class="bi bi-download"></i> Download <?php echo $file['ext'] === 'pdf' ? 'PDF' : 'Card'; ?></a>
                                <?php endif; ?>
                                <?php echo pp_extra_render($data); ?>
                            <?php elseif ($ppError === ''): ?>
                                <div class="text-center py-5">
                                    <img class="pp-icon mb-3" src="uploads/service_icom/<?php echo $h($ppSvc['img']); ?>" alt="">
                                    <h5 class="text-muted">Details bhar kar Submit karein</h5>
                                    <p class="text-muted mb-0">Result yahan full detail me dikhega. History me se kabhi bhi dobara dekh sakte hain.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-11 col-12 mb-4">
                    <div class="card pp-card">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-3">My Requests</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped align-middle mb-0">
                                    <thead><tr><th>#</th><th>Input</th><th>Charge</th><th>Status</th><th>Date</th><th>Result</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($ppHistory as $row): ?>
                                        <tr>
                                            <td><?php echo $h($row['application_no']); ?></td>
                                            <td class="text-break"><?php echo $h($row['input_summary']); ?></td>
                                            <td>Rs. <?php echo number_format((float) $row['fee'], 2); ?></td>
                                            <td><span class="badge bg-<?php echo $row['status'] === 'success' ? 'success' : 'danger'; ?>"><?php echo $h(strtoupper($row['status'])); ?></span><?php if ($row['status'] !== 'success'): ?><div class="small text-muted"><?php echo $h($row['message']); ?></div><?php endif; ?></td>
                                            <td class="text-nowrap"><?php echo $h($row['created_at']); ?></td>
                                            <td><?php if ($row['status'] === 'success' && $row['has_result']): ?><a class="btn btn-sm btn-primary rounded-pill" href="<?php echo $h($ppSelf . '&r=' . (int) $row['id']); ?>">View</a><?php else: ?>-<?php endif; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (!$ppHistory): ?><tr><td colspan="6" class="text-center text-muted">Abhi tak koi request nahi.</td></tr><?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>

<?php include('../template/ahkweb/footer.php'); ?>
</body>
</html>
