<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('../includes/session.php');
include('../includes/config.php');
if (!checkAdmin($udata['type'])) {
    echo '<script>window.location="index.php";</script>';
    die();
}

function _v2EnsureSchemaWUR($conn) {
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
    $done = true;
}
_v2EnsureSchemaWUR($ahk_conn);

$searchPhone = trim((string)($_GET['phone'] ?? ''));
$from_date   = trim((string)($_GET['from_date'] ?? ''));
$to_date     = trim((string)($_GET['to_date'] ?? ''));
$searched    = !empty($searchPhone);
$userInfo    = null;
$allTxns     = [];
$summary     = [
    'current_balance' => 0,
    'total_credited'  => 0,
    'total_debited'   => 0,
    'pending_deposit' => 0,
    'pending_count'   => 0,
    'rejected_count'  => 0,
    'success_count'   => 0,
];

if ($searched) {
    $safePhone = mysqli_real_escape_string($ahk_conn, $searchPhone);
    $uRes = @mysqli_query($ahk_conn, "SELECT id, name, phone, email, balance, type FROM users WHERE phone='{$safePhone}' LIMIT 1");
    if ($uRes && mysqli_num_rows($uRes) > 0) {
        $userInfo = mysqli_fetch_assoc($uRes);
        $summary['current_balance'] = (float)($userInfo['balance'] ?? 0);
    }

    $dateWhere = '';
    if (!empty($from_date) && !empty($to_date)) {
        $fd = mysqli_real_escape_string($ahk_conn, $from_date);
        $td = mysqli_real_escape_string($ahk_conn, $to_date);
        $dateWhere = " AND (DATE(date) BETWEEN '{$fd}' AND '{$td}' OR DATE(txn_date) BETWEEN '{$fd}' AND '{$td}' OR DATE(created_at) BETWEEN '{$fd}' AND '{$td}') ";
    }

    $wRes = @mysqli_query($ahk_conn,
        "SELECT id, txn_id, amount, PAYMENTMODE, BANKTXNID, RESPMSG, status, txn_date, date
         FROM wallet
         WHERE phone='{$safePhone}' " . (
            (!empty($from_date) && !empty($to_date))
            ? " AND (DATE(COALESCE(txn_date, date)) BETWEEN '".mysqli_real_escape_string($ahk_conn,$from_date)."' AND '".mysqli_real_escape_string($ahk_conn,$to_date)."') "
            : ""
         ) . " ORDER BY id DESC LIMIT 5000"
    );
    if ($wRes) while ($r = mysqli_fetch_assoc($wRes)) {
        $ts = strtotime($r['txn_date'] ?? $r['date'] ?? 'now') ?: time();
        $amt = (float)$r['amount'];
        $st  = strtolower($r['status'] ?? 'pending');
        $row = [
            'sort_time'   => $ts,
            'source'      => 'wallet',
            'source_id'   => $r['id'],
            'category'    => 'deposit',
            'type'        => 'CREDIT',
            'status'      => $st,
            'amount'      => $amt,
            'balance'     => null,
            'purpose'     => 'Wallet Add Request (Gateway/QR)',
            'ref_no'      => $r['txn_id'] ?? '',
            'bank_txn'    => $r['BANKTXNID'] ?? '',
            'mode'        => $r['PAYMENTMODE'] ?? '',
            'remark'      => $r['RESPMSG'] ?? '',
            'display_dt'  => date('Y-m-d H:i:s', $ts),
        ];
        if ($st === 'success') {
            $summary['total_credited'] += $amt;
            $summary['success_count']++;
        } elseif ($st === 'pending') {
            $summary['pending_deposit'] += $amt;
            $summary['pending_count']++;
        } elseif ($st === 'rejected') {
            $summary['rejected_count']++;
        }
        $allTxns[] = $row;
    }

    $wtRes = @mysqli_query($ahk_conn,
        "SELECT id, order_id, amount, txn_status, created_at, email
         FROM wallet_transactions
         WHERE phone='{$safePhone}' " . (
            (!empty($from_date) && !empty($to_date))
            ? " AND DATE(created_at) BETWEEN '".mysqli_real_escape_string($ahk_conn,$from_date)."' AND '".mysqli_real_escape_string($ahk_conn,$to_date)."' "
            : ""
         ) . " ORDER BY id DESC LIMIT 5000"
    );
    if ($wtRes) while ($r = mysqli_fetch_assoc($wtRes)) {
        $ts = strtotime($r['created_at'] ?? 'now') ?: time();
        $amt = (float)$r['amount'];
        $st  = strtoupper($r['txn_status'] ?? 'PENDING');
        $stNorm = 'pending';
        if ($st === 'SUCCESS' || $st === 'CREDITED' || $st === 'COMPLETED') $stNorm = 'success';
        elseif ($st === 'FAILED' || $st === 'REJECTED') $stNorm = 'rejected';
        $row = [
            'sort_time'   => $ts,
            'source'      => 'wallet_transactions',
            'source_id'   => $r['id'],
            'category'    => 'deposit',
            'type'        => 'CREDIT',
            'status'      => $stNorm,
            'amount'      => $amt,
            'balance'     => null,
            'purpose'     => 'Wallet Add (PaytmUPI)',
            'ref_no'      => $r['order_id'] ?? '',
            'bank_txn'    => '',
            'mode'        => 'PaytmUPI',
            'remark'      => $st,
            'display_dt'  => date('Y-m-d H:i:s', $ts),
        ];
        if ($stNorm === 'success') {
            $summary['total_credited'] += $amt;
            $summary['success_count']++;
        } elseif ($stNorm === 'pending') {
            $summary['pending_deposit'] += $amt;
            $summary['pending_count']++;
        } elseif ($stNorm === 'rejected') {
            $summary['rejected_count']++;
        }
        $allTxns[] = $row;
    }

    $whRes = @mysqli_query($ahk_conn,
        "SELECT id, userid, amount, balance, purpose, status, type, date
         FROM wallethistory
         WHERE userid='{$safePhone}' " . (
            (!empty($from_date) && !empty($to_date))
            ? " AND DATE(date) BETWEEN '".mysqli_real_escape_string($ahk_conn,$from_date)."' AND '".mysqli_real_escape_string($ahk_conn,$to_date)."' "
            : ""
         ) . " ORDER BY id DESC LIMIT 10000"
    );
    if ($whRes) while ($r = mysqli_fetch_assoc($whRes)) {
        $ts = strtotime($r['date'] ?? 'now') ?: time();
        $amt = (float)$r['amount'];
        $typ = strtoupper(trim((string)($r['type'] ?? '')));
        $isDebit = ($typ === 'DEBIT');
        $stNorm  = 'success';
        $whStatus = strtolower(trim((string)($r['status'] ?? '')));
        if ($whStatus === 'pending' || $whStatus === 'fail' || $whStatus === 'failed') $stNorm = $whStatus;
        $row = [
            'sort_time'   => $ts,
            'source'      => 'wallethistory',
            'source_id'   => $r['id'],
            'category'    => $isDebit ? 'debit' : 'credit',
            'type'        => $typ,
            'status'      => $stNorm,
            'amount'      => $amt,
            'balance'     => $r['balance'] ?? null,
            'purpose'     => $r['purpose'] ?? '',
            'ref_no'      => '',
            'bank_txn'    => '',
            'mode'        => $isDebit ? 'Wallet Debit' : 'Wallet Credit',
            'remark'      => $r['status'] ?? '',
            'display_dt'  => date('Y-m-d H:i:s', $ts),
        ];
        if ($stNorm === 'success') {
            if ($isDebit) $summary['total_debited'] += $amt;
            else $summary['total_credited'] += $amt;
        }
        $allTxns[] = $row;
    }

    usort($allTxns, fn($a,$b) => $b['sort_time'] - $a['sort_time']);
}

include('../template/ahkweb/header.php');
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<style>
.sum-card { border-radius:14px; padding:18px; color:#fff; box-shadow:0 6px 18px rgba(0,0,0,.10); }
.sc-1 { background: linear-gradient(135deg,#00c6ff,#0072ff); }
.sc-2 { background: linear-gradient(135deg,#11998e,#38ef7d); }
.sc-3 { background: linear-gradient(135deg,#eb3349,#f45c43); }
.sc-4 { background: linear-gradient(135deg,#f7971e,#ffd200); color:#333; }
.sum-val { font-size:26px; font-weight:800; font-family:'Inter',sans-serif; }
.sum-lbl { font-size:13px; opacity:.95; font-weight:600; }
.badge-pill { padding:3px 10px; border-radius:20px; font-weight:700; font-size:11px; }
.bp-pending { background:#fff3cd; color:#856404; }
.bp-success { background:#d4edda; color:#155724; }
.bp-rejected{ background:#f8d7da; color:#721c24; }
.bp-credit  { background:#d1e7dd; color:#0f5132; }
.bp-debit   { background:#f8d7da; color:#842029; }
.type-tag { font-weight:800; font-size:12px; padding:2px 8px; border-radius:8px; }
.tg-dep  { background:#e7f1ff; color:#084298; }
.tg-deb  { background:#fde2e1; color:#b02a37; }
.tg-cred { background:#d1e7dd; color:#0f5132; }
.luxury-thead th { background: linear-gradient(135deg,#1e3c72,#2a5298); color:#fff; font-weight:700; }
.amount-pos { color:#198754; font-weight:800; }
.amount-neg { color:#dc3545; font-weight:800; }
.ref-col { font-family: monospace; font-size:11px; color:#555; }
.search-card { border-radius:16px; box-shadow:0 4px 16px rgba(0,0,0,.06); border:1px solid #eef; }
</style>

<div class="page-wrapper">
<div class="page-content">

<div class="card radius-15 border-0 search-card">
  <div class="card-body">
    <div class="d-flex align-items-center justify-content-between flex-wrap mb-3">
      <div>
        <h4 class="mb-0 fw-bold"><i class="fa-solid fa-file-invoice-dollar text-danger me-2"></i> User Wallet Transaction Record (A2Z)</h4>
        <p class="text-muted mb-0 mt-1">Enter user mobile number → Full wallet activity: deposits, pending, rejected, service debits, credits, date-time wise.</p>
      </div>
      <a href="?" class="btn btn-outline-danger rounded-pill px-4 fw-bold"><i class="fa-solid fa-rotate me-1"></i> Reset</a>
    </div>

    <form method="GET" class="row g-3 align-items-end">
      <div class="col-md-3">
        <label class="form-label fw-bold">User Mobile Number</label>
        <div class="input-group">
          <span class="input-group-text bg-light"><i class="fa-solid fa-mobile-screen text-primary"></i></span>
          <input type="text" name="phone" id="phoneInp" value="<?= htmlspecialchars($searchPhone) ?>"
                 class="form-control" placeholder="e.g. 9876543210" maxlength="15" required>
        </div>
      </div>
      <div class="col-md-2">
        <label class="form-label fw-bold">From Date</label>
        <input type="date" name="from_date" value="<?= htmlspecialchars($from_date) ?>" class="form-control">
      </div>
      <div class="col-md-2">
        <label class="form-label fw-bold">To Date</label>
        <input type="date" name="to_date" value="<?= htmlspecialchars($to_date) ?>" class="form-control">
      </div>
      <div class="col-md-5 text-start text-md-end">
        <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm me-2">
          <i class="fa-solid fa-magnifying-glass me-1"></i> Search User
        </button>
        <?php if ($searched): ?>
        <a href="?phone=<?= urlencode($searchPhone) ?>" class="btn btn-outline-secondary rounded-pill px-4 fw-bold">
          <i class="fa-solid fa-xmark me-1"></i> Clear Dates
        </a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<?php if ($searched): ?>

  <?php if (empty($userInfo)): ?>
    <div class="alert alert-warning rounded-12 shadow-sm fw-bold" role="alert">
      <i class="fa-solid fa-triangle-exclamation me-2"></i>
      No user found with mobile number <b><?= htmlspecialchars($searchPhone) ?></b>. Please check and try again.
    </div>
  <?php else: ?>

    <div class="card radius-15 border-0 shadow-sm mb-4">
      <div class="card-body">
        <div class="row align-items-center">
          <div class="col-md-5">
            <h5 class="mb-1 fw-bold">
              <i class="fa-solid fa-user-circle text-primary me-2"></i>
              <?= htmlspecialchars($userInfo['name'] ?? 'N/A') ?>
            </h5>
            <div class="text-muted">
              <i class="fa-solid fa-mobile me-1"></i> <b><?= htmlspecialchars($userInfo['phone']) ?></b>
              <?php if (!empty($userInfo['email'])): ?>
                 &nbsp;·&nbsp; <i class="fa-solid fa-envelope me-1"></i> <?= htmlspecialchars($userInfo['email']) ?>
              <?php endif; ?>
            </div>
            <div class="text-muted small mt-1">
              ID: #<?= (int)$userInfo['id'] ?> · Role: <b><?= htmlspecialchars(ucfirst($userInfo['type'] ?? 'user')) ?></b>
            </div>
          </div>
          <div class="col-md-7">
            <div class="row g-3">
              <div class="col-md-3 col-6">
                <div class="sum-card sc-1">
                  <div class="sum-lbl"><i class="fa-solid fa-wallet me-1"></i> Current Balance</div>
                  <div class="sum-val mt-1">₹<?= number_format($summary['current_balance'],2) ?></div>
                </div>
              </div>
              <div class="col-md-3 col-6">
                <div class="sum-card sc-2">
                  <div class="sum-lbl"><i class="fa-solid fa-arrow-trend-up me-1"></i> Total Added</div>
                  <div class="sum-val mt-1">₹<?= number_format($summary['total_credited'],2) ?></div>
                  <div class="small" style="opacity:.85;">Success: <?= (int)$summary['success_count'] ?> txns</div>
                </div>
              </div>
              <div class="col-md-3 col-6">
                <div class="sum-card sc-3">
                  <div class="sum-lbl"><i class="fa-solid fa-arrow-trend-down me-1"></i> Total Spent</div>
                  <div class="sum-val mt-1">₹<?= number_format($summary['total_debited'],2) ?></div>
                  <div class="small" style="opacity:.85;"><?= count(array_filter($allTxns,fn($x)=>$x['category']==='debit'&&$x['status']==='success')) ?> services used</div>
                </div>
              </div>
              <div class="col-md-3 col-6">
                <div class="sum-card sc-4">
                  <div class="sum-lbl"><i class="fa-solid fa-clock-rotate-left me-1"></i> Pending Add</div>
                  <div class="sum-val mt-1">₹<?= number_format($summary['pending_deposit'],2) ?></div>
                  <div class="small" style="opacity:.9;"><b><?= (int)$summary['pending_count'] ?></b> pending · <?= (int)$summary['rejected_count'] ?> rejected</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card radius-15 border-0 shadow-sm">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between flex-wrap mb-3">
          <div>
            <h5 class="mb-0 fw-bold"><i class="fa-solid fa-list-check text-success me-2"></i> A2Z Wallet Activity (Chronological)</h5>
            <p class="text-muted mb-0 small">
              <?= count($allTxns) ?> records found
              <?php if (!empty($from_date) && !empty($to_date)): ?>
                · Filtered: <b><?= htmlspecialchars($from_date) ?></b> → <b><?= htmlspecialchars($to_date) ?></b>
              <?php endif; ?>
            </p>
          </div>
        </div>
        <div class="table-responsive">
          <table id="wurTable" class="table table-hover align-middle" style="width:100%">
            <thead class="luxury-thead">
              <tr>
                <th class="text-center">#</th>
                <th>Date & Time</th>
                <th>Type</th>
                <th>Category</th>
                <th>Amount</th>
                <th>Purpose / Service</th>
                <th>Status</th>
                <th>Ref / TXN / UTR</th>
                <th>Mode</th>
                <th>Wallet Balance</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($allTxns)): ?>
                <?php $sno = 0; foreach ($allTxns as $t): $sno++;
                    $isDebit = ($t['category']==='debit');
                    $isDeposit = ($t['category']==='deposit');
                    $amtClass = $isDebit ? 'amount-neg' : 'amount-pos';
                    $amtSign  = $isDebit ? '-' : '+';
                    $_stL = strtolower($t['status']);
                    if ($_stL === 'success') { $statusBadge = '<span class="badge-pill bp-success">SUCCESS</span>'; }
                    elseif ($_stL === 'pending') { $statusBadge = '<span class="badge-pill bp-pending">PENDING</span>'; }
                    elseif ($_stL === 'rejected') { $statusBadge = '<span class="badge-pill bp-rejected">REJECTED</span>'; }
                    elseif ($_stL === 'fail' || $_stL === 'failed') { $statusBadge = '<span class="badge-pill bp-rejected">FAILED</span>'; }
                    else { $statusBadge = '<span class="badge-pill bp-pending">'.strtoupper($t['status']).'</span>'; }
                    $_cat = $t['category'];
                    if ($_cat === 'deposit') { $catTag = '<span class="type-tag tg-dep">DEPOSIT</span>'; }
                    elseif ($_cat === 'credit') { $catTag = '<span class="type-tag tg-cred">CREDIT</span>'; }
                    elseif ($_cat === 'debit') { $catTag = '<span class="type-tag tg-deb">DEBIT</span>'; }
                    else { $catTag = '<span class="type-tag">'.strtoupper($_cat).'</span>'; }
                ?>
                  <tr>
                    <td class="text-center fw-bold"><?= $sno ?></td>
                    <td>
                      <div style="font-weight:600;"><?= htmlspecialchars(date('d M Y', $t['sort_time'])) ?></div>
                      <small class="text-muted"><i class="fa-regular fa-clock me-1"></i><?= htmlspecialchars(date('h:i A', $t['sort_time'])) ?></small>
                    </td>
                    <td><?= $catTag ?></td>
                    <td>
                      <?php
                        if ($isDeposit) echo '<span class="badge-pill bp-credit">'.$t['type'].'</span>';
                        else echo $isDebit ? '<span class="badge-pill bp-debit">'.$t['type'].'</span>' : '<span class="badge-pill bp-credit">'.$t['type'].'</span>';
                      ?>
                    </td>
                    <td class="<?= $amtClass ?> fs-6"><?= $amtSign ?>₹<?= number_format((float)$t['amount'],2) ?></td>
                    <td>
                      <div style="font-weight:600;"><?= htmlspecialchars($t['purpose'] ?? '-') ?></div>
                      <?php if (!empty($t['remark'])): ?>
                        <small class="text-muted">Remark: <?= htmlspecialchars($t['remark']) ?></small>
                      <?php endif; ?>
                    </td>
                    <td><?= $statusBadge ?></td>
                    <td class="ref-col">
                      <?php if (!empty($t['ref_no'])): ?><div>Txn: <?= htmlspecialchars($t['ref_no']) ?></div><?php endif; ?>
                      <?php if (!empty($t['bank_txn'])): ?><div>UTR: <?= htmlspecialchars($t['bank_txn']) ?></div><?php endif; ?>
                      <?php if (empty($t['ref_no']) && empty($t['bank_txn'])) echo '<span class="text-muted">-</span>'; ?>
                    </td>
                    <td><?= htmlspecialchars($t['mode'] ?? '-') ?></td>
                    <td>
                      <?php if ($t['balance'] !== null): ?>
                        <b style="color:#0f5132;">₹<?= number_format((float)$t['balance'],2) ?></b>
                      <?php else: ?>
                        <span class="text-muted"><i class="fa-regular fa-circle-minus"></i></span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="10" class="text-center py-5">
                    <i class="fa-solid fa-inbox fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No wallet activity found</h5>
                    <p class="text-muted mb-0">This user has no wallet transactions yet.</p>
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  <?php endif; ?>

<?php endif; ?>

</div>
</div>

<?php include('../template/ahkweb/footer.php'); ?>
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/js/jquery.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function(){
    $('#phoneInp').inputmask('9999999999', { placeholder: ' ' });
    if ($.fn.DataTable && $('#wurTable').length && $('#wurTable tbody tr').length > 1) {
        try {
            new DataTable('#wurTable', {
                pageLength: 25,
                order: [],
                responsive: true,
                lengthMenu: [10, 25, 50, 100, 250, 500],
                language: { search: "<i class='fa-solid fa-magnifying-glass me-1'></i>" },
                dom: "<'row mb-2'<'col-md-6'l><'col-md-6 text-end'f>>" +
                     "<'row'<'col-12'tr>>" +
                     "<'row mt-2'<'col-md-5'i><'col-md-7 text-end'p>>"
            });
        } catch(e) { console.warn('DT init',e); }
    }
});
</script>
