<?php
include('../includes/session.php');
include('../includes/config.php');
if (!checkAdmin($udata['type'])) {
    echo '<script>window.location="index.php";</script>';
    die();
}

// ===== SELF-HEAL: Ensure wallet-related tables + columns exist =====
function _v2EnsureWalletSchema_mu($conn) {
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
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `users` (
      `id` INT NOT NULL AUTO_INCREMENT, `order_id` VARCHAR(255) NULL, `phone` VARCHAR(255) NULL,
      `status` VARCHAR(10) NULL DEFAULT '0', `name` VARCHAR(255) NULL, `type` VARCHAR(50) NULL,
      `balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00, PRIMARY KEY (`id`), KEY `idx_users_order` (`order_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $done = true;
}
_v2EnsureWalletSchema_mu($ahk_conn);

$actionMsg = '';
$actionType = '';

function _muV2Alert($m, $t = 'success') { global $actionMsg, $actionType; $actionMsg=$m; $actionType=$t; }

// Approve Manual User Signup → activate user + mark wallet success
if (isset($_GET['apid']) && isset($_GET['approve']) && $_GET['approve'] == 1) {
    try {
        $txnId = base64_decode(getSafe($_GET['apid']));
        $wQ = ahkQuery("SELECT w.*, u.id as uid, u.status as ustatus FROM wallet w LEFT JOIN users u ON w.txn_id = u.order_id WHERE w.txn_id='$txnId' LIMIT 1");
        $wRow = $wQ ? mysqli_fetch_assoc($wQ) : null;
        if (!$wRow) { _muV2Alert('Order / Wallet record not found', 'error'); }
        else {
            if (function_exists('mysqli_begin_transaction')) mysqli_begin_transaction($ahk_conn);
            else ahkQuery("START TRANSACTION");
            $ok = true;
            $ok = $ok && ahkQuery("UPDATE wallet SET status='success' WHERE txn_id='$txnId' LIMIT 1");
            $ok = $ok && ahkQuery("UPDATE users SET status='1' WHERE order_id='$txnId' LIMIT 1");
            if ($ok) {
                $_refPhone = trim((string)($wRow['phone'] ?? ''));
                if ($_refPhone === '') {
                    $uRef = ahkQuery("SELECT phone FROM users WHERE order_id='".mysqli_real_escape_string($ahk_conn,$txnId)."' LIMIT 1");
                    if ($uRef && ($uRefR = mysqli_fetch_assoc($uRef))) $_refPhone = trim((string)($uRefR['phone'] ?? ''));
                }
                if ($_refPhone !== '' && function_exists('gpGiveReferralBonusIfEligible')) {
                    gpGiveReferralBonusIfEligible($_refPhone, $ahk_conn);
                }
                if (function_exists('mysqli_commit')) @mysqli_commit($ahk_conn); else ahkQuery("COMMIT");
                _muV2Alert('User Approved & Activated successfully!');
            } else {
                if (function_exists('mysqli_rollback')) @mysqli_rollback($ahk_conn); else ahkQuery("ROLLBACK");
                _muV2Alert('Approve failed: ' . mysqli_error($ahk_conn), 'error');
            }
        }
    } catch (Throwable $e) {
        _muV2Alert('Server Error: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')', 'error');
    }
}
// Reject
if (isset($_GET['rejid']) && isset($_GET['reject']) && $_GET['reject'] == 1) {
    try {
        $txnId = base64_decode(getSafe($_GET['rejid']));
        $ok = ahkQuery("UPDATE wallet SET status='rejected' WHERE txn_id='$txnId' LIMIT 1");
        if ($ok) _muV2Alert('User Rejected (marked rejected).');
        else _muV2Alert('Reject failed: '.mysqli_error($ahk_conn), 'error');
    } catch (Throwable $e) {
        _muV2Alert('Server Error: ' . $e->getMessage(), 'error');
    }
}

$tab = getSafe($_GET['tab'] ?? 'pending');
if (!in_array($tab, ['pending','all','success','rejected'])) $tab = 'pending';

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
                        <h5 class="mb-0">📝 Manual User Signups (Admin v2)</h5>
                        <p class="text-muted mb-0">REGQR / REGFEE paid users waiting for approval. Approve → activates user account. View ALL records.</p>
                    </div>
                </div>
                <ul class="nav nav-tabs mb-3" role="tablist">
                    <?php $tabs = ['pending'=>'🟡 Pending','all'=>'📋 ALL','success'=>'✅ Approved','rejected'=>'❌ Rejected'];
                    foreach ($tabs as $tk=>$tl) { $active = ($tab===$tk)?'active':''; ?>
                      <li class="nav-item"><a class="nav-link <?=$active?>" href="?tab=<?=urlencode($tk)?>"><?=$tl?></a></li>
                    <?php } ?>
                </ul>
                <hr>
                <div class="table-responsive">
                    <table id="example2" class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center">SL</th>
                                <th class="text-center">Name</th>
                                <th class="text-center">Phone / Email</th>
                                <th class="text-center">Mode</th>
                                <th class="text-center">User Type</th>
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
                        $where = "(w.PAYMENTMODE IN ('REGQR','REGFEE') OR w.txn_id LIKE 'REG%' OR w.txn_id LIKE 'ACT%')";
                        if ($tab === 'pending') $where .= " AND w.status = 'pending'";
                        elseif ($tab === 'success') $where .= " AND w.status = 'success'";
                        elseif ($tab === 'rejected') $where .= " AND w.status = 'rejected'";
                        $sql = "SELECT w.id, w.txn_id, w.phone, w.email, w.amount, w.BANKTXNID, w.status, w.PAYMENTMODE, w.txn_date, w.date,
                                       u.name, u.type, u.status AS u_status, u.id as uid
                                FROM wallet w
                                LEFT JOIN users u ON w.txn_id = u.order_id
                                WHERE $where
                                ORDER BY w.id DESC
                                LIMIT 500";
                        $res = ahkQuery($sql);
                        $found = $res ? mysqli_num_rows($res) : 0;
                        if ($found > 0) {
                            $x = 0;
                            while ($d = mysqli_fetch_assoc($res)) { $x++;
                                $stBadge = '';
                                if ($d['status'] === 'pending') $stBadge = '<span class="badge-pending">PENDING</span>';
                                elseif ($d['status'] === 'success') $stBadge = '<span class="badge-success">APPROVED</span>';
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
                                <td class="text-center"><?= htmlspecialchars($d['name'] ?? '-') ?></td>
                                <td class="text-center">
                                    <strong>📞 <?= htmlspecialchars($d['phone']) ?></strong><br>
                                    📧 <?= htmlspecialchars($d['email']) ?>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($d['PAYMENTMODE'])) { ?>
                                        <span class="badge bg-info"><?= htmlspecialchars(strtoupper($d['PAYMENTMODE'])) ?></span>
                                    <?php } else { ?>
                                        <span class="badge bg-secondary">REG</span>
                                    <?php } ?>
                                </td>
                                <td class="text-center"><span class="badge bg-info"><?= strtoupper(htmlspecialchars($d['type'] ?? '-')) ?></span></td>
                                <td class="text-center"><strong><?= htmlspecialchars($d['txn_id']) ?></strong></td>
                                <td class="text-center">
                                    <?php if (!empty($d['BANKTXNID'])) { ?>
                                        <span class="badge bg-success"><?= htmlspecialchars($d['BANKTXNID']) ?></span>
                                    <?php } else { ?>
                                        <span class="text-danger">TXN not provided</span>
                                    <?php } ?>
                                </td>
                                <td class="text-center col-amt">Rs.<?= number_format((float)$d['amount'], 2) ?></td>
                                <td class="text-center"><?= $stBadge ?></td>
                                <td class="text-center small text-muted"><?= htmlspecialchars($dt) ?></td>
                                <td class="text-center">
                                    <div class="act-wrap">
                                        <?php if ($d['status'] !== 'success') { ?>
                                            <a class="bg-light-success text-success" title="Approve & Activate user" href="?tab=<?=urlencode($tab)?>&apid=<?= base64_encode($d['txn_id']) ?>&approve=1" onclick="return confirm('Approve user: <?= htmlspecialchars($d['name'] ?? $d['phone']) ?>? Their account will become ACTIVE (login allowed).');">✅</a>
                                        <?php } ?>
                                        <?php if ($d['status'] !== 'rejected') { ?>
                                            <a class="bg-light-danger text-danger" title="Reject user" href="?tab=<?=urlencode($tab)?>&rejid=<?= base64_encode($d['txn_id']) ?>&reject=1" onclick="return confirm('Reject signup TXN <?= htmlspecialchars($d['txn_id']) ?>?');">❌</a>
                                        <?php } ?>
                                    </div>
                                </td>
                            </tr>
                        <?php
                            }
                        } else {
                            echo '<tr><td colspan="11" class="text-center text-muted py-5"><h5>🗂️ No records in this tab. Switch tabs above.</h5></td></tr>';
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
