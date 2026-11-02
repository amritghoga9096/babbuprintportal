<?php
include('../includes/session.php');
include('../includes/config.php');
if (!checkAdmin($udata['type'])) {
    echo '<script>window.location="index.php";</script>';
    die();
}

// ===== SELF-HEAL: Ensure wallet-related tables + columns exist =====
function _v2EnsureWalletSchema_qr($conn) {
    if (!$conn) return;
    static $done = null;
    if ($done === true) return;
    @mysqli_report(MYSQLI_REPORT_OFF);
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `wallet` (
      `id` INT NOT NULL AUTO_INCREMENT, `phone` VARCHAR(255) NULL, `amount` VARCHAR(255) NULL,
      `txn_id` VARCHAR(255) NULL, `date` VARCHAR(255) NULL, `txn_date` DATETIME NULL DEFAULT CURRENT_TIMESTAMP(),
      `email` VARCHAR(255) NULL, `PAYMENTMODE` VARCHAR(255) NULL, `RESPMSG` VARCHAR(255) NULL,
      `BANKTXNID` VARCHAR(255) NULL, `status` ENUM('pending','success','rejected') NOT NULL DEFAULT 'pending',
      PRIMARY KEY (`id`), KEY `idx_wallet_txn` (`txn_id`), KEY `idx_wallet_phone` (`phone`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $cols = []; $r = @mysqli_query($conn, "SHOW COLUMNS FROM wallet");
    if ($r) while ($row = mysqli_fetch_assoc($r)) $cols[] = $row['Field'];
    $add = function($n, $d) use ($conn, $cols) { if (!in_array($n, $cols, true)) @mysqli_query($conn, "ALTER TABLE wallet ADD COLUMN {$n} {$d}"); };
    $add('txn_date', "DATETIME NULL DEFAULT CURRENT_TIMESTAMP()"); $add('PAYMENTMODE', "VARCHAR(255) NULL");
    $add('BANKTXNID', "VARCHAR(255) NULL"); $add('RESPMSG', "VARCHAR(255) NULL"); $add('email', "VARCHAR(255) NULL");
    $add('status', "ENUM('pending','success','rejected') NOT NULL DEFAULT 'pending'");
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `wallet_transactions` (
      `id` INT NOT NULL AUTO_INCREMENT, `order_id` VARCHAR(64) NOT NULL, `session_id` VARCHAR(128) NULL,
      `phone` VARCHAR(255) NULL, `email` VARCHAR(255) NULL, `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      `txn_status` VARCHAR(32) NOT NULL DEFAULT 'PENDING', `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `wallethistory` (
      `id` INT NOT NULL AUTO_INCREMENT, `userid` VARCHAR(255) NULL, `amount` VARCHAR(255) NULL,
      `balance` VARCHAR(255) NULL, `purpose` VARCHAR(255) NULL, `status` VARCHAR(255) NULL,
      `type` VARCHAR(255) NULL, `date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(), PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `transactions` (
      `id` INT NOT NULL AUTO_INCREMENT, `uid` INT NULL, `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      `description` TEXT NULL, `txn_ref` VARCHAR(128) NULL, `date` DATETIME NULL,
      `status` VARCHAR(32) NULL DEFAULT 'success', PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $done = true;
}
_v2EnsureWalletSchema_qr($ahk_conn);

$actionMsg = '';
$actionType = '';

function _qrV2Alert($msg, $type = 'success') {
    global $actionMsg, $actionType;
    $actionMsg = $msg;
    $actionType = $type;
}

// Approve QR payment (any status -> mark success + credit wallet)
if (isset($_GET['apid']) && isset($_GET['approve']) && $_GET['approve'] == 1) {
    try {
        $txnId = base64_decode(getSafe($_GET['apid']));
        $sel = ahkQuery("SELECT w.*, u.id as uid, u.balance as u_bal FROM wallet w LEFT JOIN users u ON w.phone = u.phone WHERE w.txn_id='$txnId' LIMIT 1");
        $row = $sel ? mysqli_fetch_assoc($sel) : null;
        if (!$row) { _qrV2Alert('TXN record not found', 'error'); }
        else {
            $amt = (float)$row['amount'];
            $uid = (int)$row['uid'];
            $phone = $row['phone'];
            if (function_exists('mysqli_begin_transaction')) mysqli_begin_transaction($ahk_conn);
            else ahkQuery("START TRANSACTION");
            $ok = true;
            $ok = $ok && ahkQuery("UPDATE wallet SET status='success' WHERE txn_id='$txnId' LIMIT 1");
            if ($uid > 0) {
                $ok = $ok && ahkQuery("UPDATE users SET balance = balance + $amt WHERE id=$uid LIMIT 1");
                $ref = 'QRAPV-' . strtoupper(substr(md5($txnId . microtime(true)), 0, 10));
                $desc = 'Gateway Payment Approved by Admin: TXN ' . $txnId . ' / UTR: ' . ($row['BANKTXNID'] ?? 'NA') . ' / Mode: ' . ($row['PAYMENTMODE'] ?? '-') . ' | User: ' . $phone . ' | Rs.' . number_format($amt, 2);
                $logged = false;
                $stA = @mysqli_prepare($ahk_conn, "INSERT INTO transactions (uid, amount, description, txn_ref, date, status) VALUES (?, ?, ?, ?, NOW(), 'success')");
                if ($stA) { @mysqli_stmt_bind_param($stA, 'idss', $uid, $amt, $desc, $ref); if (@mysqli_stmt_execute($stA)) $logged = true; @mysqli_stmt_close($stA); }
                if (!$logged) {
                    $stB = @mysqli_prepare($ahk_conn, "INSERT INTO wallet_transactions (order_id, session_id, phone, email, amount, txn_status, created_at) VALUES (?, ?, ?, ?, ?, 'CREDITED', NOW())");
                    if ($stB) {
                        $sess = session_id() ?: ('adm_' . $ref);
                        $em = $row['email'] ?? '';
                        @mysqli_stmt_bind_param($stB, 'ssssd', $ref, $sess, $phone, $em, $amt);
                        if (@mysqli_stmt_execute($stB)) $logged = true;
                        @mysqli_stmt_close($stB);
                    }
                }
            }
            if ($ok) {
                if ($phone !== '' && function_exists('gpGiveReferralBonusIfEligible')) {
                    gpGiveReferralBonusIfEligible($phone, $ahk_conn);
                }
                if (function_exists('mysqli_commit')) @mysqli_commit($ahk_conn); else ahkQuery("COMMIT");
                _qrV2Alert('Payment Approved! Rs.' . number_format($amt,2) . ' credited to user wallet (' . htmlspecialchars($phone) . ')');
            } else {
                if (function_exists('mysqli_rollback')) @mysqli_rollback($ahk_conn); else ahkQuery("ROLLBACK");
                _qrV2Alert('Approve Failed: ' . mysqli_error($ahk_conn), 'error');
            }
        }
    } catch (Throwable $e) {
        _qrV2Alert('Server Error: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')', 'error');
    }
}

// Reject QR payment
if (isset($_GET['rejid']) && isset($_GET['reject']) && $_GET['reject'] == 1) {
    try {
        $txnId = base64_decode(getSafe($_GET['rejid']));
        $ok = ahkQuery("UPDATE wallet SET status='rejected' WHERE txn_id='$txnId' LIMIT 1");
        if ($ok) _qrV2Alert('Payment Rejected successfully');
        else _qrV2Alert('Reject Failed: ' . mysqli_error($ahk_conn), 'error');
    } catch (Throwable $e) {
        _qrV2Alert('Server Error: ' . $e->getMessage(), 'error');
    }
}

// TAB filter: ?tab=pending (default) OR ?tab=all
$tab = getSafe($_GET['tab'] ?? 'pending');
if (!in_array($tab, ['pending','all','rejected','success'])) $tab = 'pending';

include('../template/ahkweb/header.php');
if ($actionMsg) showAlert($actionMsg, '', $actionType);
?>
<style>
.badge-pending { background:#fff3cd; color:#856404; padding:4px 10px; border-radius:10px; font-weight:700; }
.badge-success { background:#d4edda; color:#155724; padding:4px 10px; border-radius:10px; font-weight:700; }
.badge-rejected { background:#f8d7da; color:#721c24; padding:4px 10px; border-radius:10px; font-weight:700; }
.act-wrap { display:flex; gap:12px; align-items:center; justify-content:center; flex-wrap:wrap; }
.act-wrap a { font-size:24px; padding:4px 10px; border-radius:8px; text-decoration:none; }
.col-amt { font-weight:800; color:#1565c0; }
.nav-tabs .nav-link { border-radius:8px 8px 0 0; }
.nav-tabs .nav-link.active { background:#0d6efd; color:#fff; border-color:#0d6efd; }
</style>
<div class="page-wrapper">
    <div class="page-content">
        <div class="card radius-10">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between flex-wrap mb-3">
                    <div>
                        <h5 class="mb-0">💸 Gateway / QR Payment Requests (Admin v2)</h5>
                        <p class="text-muted mb-0">Approve → auto credits ₹ to user wallet + audit log. Reject → mark rejected. View ALL payment records (even SUCCESS) for audit.</p>
                    </div>
                </div>
                <ul class="nav nav-tabs mb-3" role="tablist">
                    <?php $tabs = ['pending'=>'🟡 Pending Gateway Deposits','all'=>'📋 ALL Records','success'=>'✅ Success','rejected'=>'❌ Rejected'];
                    foreach ($tabs as $tk=>$tl) { $active = ($tab===$tk) ? 'active' : ''; ?>
                      <li class="nav-item"><a class="nav-link <?=$active?>" href="?tab=<?=urlencode($tk)?>"><?=$tl?></a></li>
                    <?php } ?>
                </ul>
                <hr>
                <div class="table-responsive">
                    <table id="example2" class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center">SL</th>
                                <th class="text-center">User Name</th>
                                <th class="text-center">Phone / Email</th>
                                <th class="text-center">Mode</th>
                                <th class="text-center">Order / TXN ID</th>
                                <th class="text-center">UTR / Bank TXN</th>
                                <th class="text-center">Amount</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Date</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        // WHERE: Show ALL wallet records that are NOT signup registration (REGFEE / REGQR). Include even NULL PAYMENTMODE.
                        $where = "(w.PAYMENTMODE IS NULL OR w.PAYMENTMODE NOT IN ('REGFEE','REGQR'))";
                        if ($tab === 'pending') $where .= " AND w.status = 'pending'";
                        elseif ($tab === 'success') $where .= " AND w.status = 'success'";
                        elseif ($tab === 'rejected') $where .= " AND w.status = 'rejected'";
                        $sql = "SELECT w.id, w.txn_id, w.phone, w.email, w.amount, w.BANKTXNID, w.status, w.PAYMENTMODE, w.txn_date, w.date,
                                       u.name as uname, u.id as uid
                                FROM wallet w
                                LEFT JOIN users u ON w.phone = u.phone
                                WHERE $where
                                ORDER BY w.id DESC
                                LIMIT 500";
                        $res = ahkQuery($sql);
                        $foundRows = $res ? mysqli_num_rows($res) : 0;
                        if ($foundRows > 0) {
                            $x = 0;
                            while ($d = mysqli_fetch_assoc($res)) { $x++;
                                $stBadge = '';
                                if ($d['status'] === 'pending') $stBadge = '<span class="badge-pending">PENDING</span>';
                                elseif ($d['status'] === 'success') $stBadge = '<span class="badge-success">SUCCESS</span>';
                                elseif ($d['status'] === 'rejected') $stBadge = '<span class="badge-rejected">REJECTED</span>';
                                else $stBadge = '<span class="badge bg-light text-dark">'.htmlspecialchars(strtoupper($d['status'])).'</span>';
                                $_rawDt = $d['txn_date'] ?: ($d['date'] ?: '');
                                if ($_rawDt && $_ts = strtotime($_rawDt)) {
                                    $dt = date('d-m-Y - h:i:s A', $_ts);
                                } else {
                                    $dt = $_rawDt ?: '-';
                                }
                        ?>
                            <tr>
                                <td class="text-center"><?= $x ?></td>
                                <td class="text-center"><?= htmlspecialchars($d['uname'] ?? ($d['name'] ?? '-')) ?></td>
                                <td class="text-center">
                                    <strong>📞 <?= htmlspecialchars($d['phone']) ?></strong><br>
                                    📧 <?= htmlspecialchars($d['email']) ?>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($d['PAYMENTMODE'])) { ?>
                                        <span class="badge bg-info"><?= htmlspecialchars(strtoupper($d['PAYMENTMODE'])) ?></span>
                                    <?php } else { ?>
                                        <span class="badge bg-secondary">UPI/QR/Gateway</span>
                                    <?php } ?>
                                </td>
                                <td class="text-center"><strong><?= htmlspecialchars($d['txn_id']) ?></strong></td>
                                <td class="text-center">
                                    <?php
                                    $_utr = (string)($d['BANKTXNID'] ?? '');
                                    $_isManual = (!empty($d['PAYMENTMODE']) && strcasecmp($d['PAYMENTMODE'], 'ManualUPI') === 0);
                                    if ($_utr !== '') {
                                        if ($_isManual) {
                                            $_respMsgTxt = '';
                                            if (!empty($d['RESPMSG'])) {
                                                $_rp = (string)$d['RESPMSG'];
                                                if (stripos($_rp, 'UTR:') !== false) {
                                                    $_afterUtr = substr($_rp, stripos($_rp, 'UTR:') + 4);
                                                    $_afterUtr = trim(explode('|', $_afterUtr)[0] ?? '');
                                                    if ($_afterUtr && $_afterUtr !== $_utr) $_respMsgTxt = $_afterUtr;
                                                }
                                            }
                                            echo '<div>';
                                            echo '<span class="badge" style="background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;font-weight:700;font-size:12px;padding:5px 11px;border-radius:8px;letter-spacing:.3px;">';
                                            echo '<i class="fa-regular fa-id-card me-1"></i> MANUAL UTR';
                                            echo '</span>';
                                            echo '<div style="margin-top:7px;">';
                                            echo '<span style="display:inline-flex;align-items:center;gap:6px;background:#fff7ed;color:#9a3412;border:1px solid #fdba74;padding:4px 10px;border-radius:8px;font-weight:700;font-size:13px;">';
                                            echo htmlspecialchars($_utr);
                                            echo '<a href="javascript:void(0)" title="Copy UTR" onclick="navigator.clipboard.writeText(\''.htmlspecialchars($_utr).'\');var x=this.innerHTML;this.innerHTML=\'<i class=\\\'fa-solid fa-check\\\'></i>\';setTimeout(function(){var p=document.createElement(\'span\');p.innerHTML=x;},1000);" style="color:#9a3412;text-decoration:none;margin-left:3px;cursor:pointer;"><i class="fa-regular fa-copy"></i></a>';
                                            echo '</span>';
                                            if ($_respMsgTxt) {
                                                echo '<div style="font-size:10.5px;color:#b45309;font-weight:600;margin-top:5px;"><i class="fa-solid fa-circle-info me-1"></i>'.htmlspecialchars($_respMsgTxt).'</div>';
                                            }
                                            echo '</div></div>';
                                        } else {
                                            echo '<span class="badge bg-success">'.htmlspecialchars($_utr).'</span>';
                                        }
                                    } else {
                                        if ($_isManual) {
                                            echo '<span class="text-danger" style="font-weight:700;"><i class="fa-solid fa-triangle-exclamation me-1"></i>UTR Not Submitted</span>';
                                            echo '<div style="font-size:10.5px;color:#92400e;margin-top:4px;">User abhi UTR submit nahi kiya</div>';
                                        } else {
                                            echo '<span class="text-danger">TXN not provided</span>';
                                        }
                                    }
                                    ?>
                                </td>
                                <td class="text-center col-amt">Rs.<?= number_format((float)$d['amount'], 2) ?></td>
                                <td class="text-center"><?= $stBadge ?></td>
                                <td class="text-center small text-muted"><?= htmlspecialchars($dt) ?></td>
                                <td class="text-center">
                                    <div class="act-wrap">
                                        <?php if ($d['status'] !== 'success') { ?>
                                            <a class="bg-light-success text-success" title="Approve & Credit wallet" href="?tab=<?=urlencode($tab)?>&apid=<?= base64_encode($d['txn_id']) ?>&approve=1" onclick="return confirm('Approve payment Rs.<?= number_format((float)$d['amount'],2) ?> for user <?= htmlspecialchars($d['phone']) ?>? Wallet will be credited INSTANTLY.');">✅</a>
                                        <?php } ?>
                                        <?php if ($d['status'] !== 'rejected') { ?>
                                            <a class="bg-light-danger text-danger" title="Reject" href="?tab=<?=urlencode($tab)?>&rejid=<?= base64_encode($d['txn_id']) ?>&reject=1" onclick="return confirm('Reject TXN <?= htmlspecialchars($d['txn_id']) ?>? Payment will be marked rejected.');">❌</a>
                                        <?php } ?>
                                    </div>
                                </td>
                            </tr>
                        <?php
                            }
                        } else {
                            echo '<tr><td colspan="10" class="text-center text-muted py-5"><h5>🗂️ No records in this tab. Switch tabs above.</h5></td></tr>';
                        }
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include('../template/ahkweb/footer.php');
?>
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/js/jquery.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/jquery.dataTables.min.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    var t = $('#example2').DataTable({ lengthChange: false, buttons: ['copy','excel','pdf','print'], pageLength: 25, order: [[0,'asc']] });
    t.buttons().container().appendTo('#example2_wrapper .col-md-6:eq(0)');
});
</script>
</body>
</html>
