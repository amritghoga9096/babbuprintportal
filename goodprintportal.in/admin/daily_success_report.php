<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

if (!checkAdmin($udata['type'])) {
    echo '<script>window.location="index.php";</script>';
    die();
}

$totalSuccessAll = 0;
$totalPendingAll = 0;
$totalSuccessAmtAll = 0;
$totalPendingAmtAll = 0;
$totalRejectedAll = 0;
$totalRejectedAmtAll = 0;
$totalUsersAll = 0;

$dateRecords = [];

for ($i = 0; $i < 200; $i++) {
    $dateKey = date('Y-m-d', strtotime("-" . $i . " days"));

    $successAmt = 0;
    $successCnt = 0;
    $pendingAmt = 0;
    $pendingCnt = 0;
    $rejectedAmt = 0;
    $rejectedCnt = 0;
    $userRegCnt = 0;

    $sr = @mysqli_query($ahk_conn,
        "SELECT COALESCE(SUM(CAST(amount AS DECIMAL(12,2))),0) AS amt, COUNT(*) AS cnt
         FROM wallet
         WHERE status = 'success'
           AND DATE(COALESCE(txn_date, date)) = '{$dateKey}'"
    );
    if ($sr && ($srow = mysqli_fetch_assoc($sr))) {
        $successAmt = (float)($srow['amt'] ?? 0);
        $successCnt = (int)($srow['cnt'] ?? 0);
    }
    @mysqli_free_result($sr);

    $pr = @mysqli_query($ahk_conn,
        "SELECT COALESCE(SUM(CAST(amount AS DECIMAL(12,2))),0) AS amt, COUNT(*) AS cnt
         FROM wallet
         WHERE status = 'pending'
           AND DATE(COALESCE(txn_date, date)) = '{$dateKey}'"
    );
    if ($pr && ($prow = mysqli_fetch_assoc($pr))) {
        $pendingAmt = (float)($prow['amt'] ?? 0);
        $pendingCnt = (int)($prow['cnt'] ?? 0);
    }
    @mysqli_free_result($pr);

    $rr = @mysqli_query($ahk_conn,
        "SELECT COALESCE(SUM(CAST(amount AS DECIMAL(12,2))),0) AS amt, COUNT(*) AS cnt
         FROM wallet
         WHERE status = 'rejected'
           AND DATE(COALESCE(txn_date, date)) = '{$dateKey}'"
    );
    if ($rr && ($rrow = mysqli_fetch_assoc($rr))) {
        $rejectedAmt = (float)($rrow['amt'] ?? 0);
        $rejectedCnt = (int)($rrow['cnt'] ?? 0);
    }
    @mysqli_free_result($rr);

    $ur = @mysqli_query($ahk_conn,
        "SELECT COUNT(*) AS cnt
         FROM users
         WHERE DATE(COALESCE(created_at, STR_TO_DATE(date, '%Y-%m-%d'))) = '{$dateKey}'
           AND LOWER(type) != 'admin'"
    );
    if ($ur && ($urow = mysqli_fetch_assoc($ur))) {
        $userRegCnt = (int)($urow['cnt'] ?? 0);
    }
    @mysqli_free_result($ur);

    if ($successCnt == 0 && $pendingCnt == 0 && $rejectedCnt == 0 && $userRegCnt == 0) {
        $dateRecords[] = [
            'date' => $dateKey,
            'success_amt' => 0,
            'success_cnt' => 0,
            'pending_amt' => 0,
            'pending_cnt' => 0,
            'rejected_amt' => 0,
            'rejected_cnt' => 0,
            'user_reg_cnt' => 0,
            'has_data' => false,
        ];
    } else {
        $dateRecords[] = [
            'date' => $dateKey,
            'success_amt' => $successAmt,
            'success_cnt' => $successCnt,
            'pending_amt' => $pendingAmt,
            'pending_cnt' => $pendingCnt,
            'rejected_amt' => $rejectedAmt,
            'rejected_cnt' => $rejectedCnt,
            'user_reg_cnt' => $userRegCnt,
            'has_data' => true,
        ];
    }

    $totalSuccessAll += $successCnt;
    $totalPendingAll += $pendingCnt;
    $totalSuccessAmtAll += $successAmt;
    $totalPendingAmtAll += $pendingAmt;
    $totalRejectedAll += $rejectedCnt;
    $totalRejectedAmtAll += $rejectedAmt;
    $totalUsersAll += $userRegCnt;
}
?>

<style>
.summary-card {
    border-radius: 14px;
    padding: 1.5rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    border: 1px solid #eaeaea;
}
.row-success { background: linear-gradient(90deg, #d4edda 0%, #ffffff 40%); }
.row-pending { background: linear-gradient(90deg, #fff3cd 0%, #ffffff 40%); }
.row-rejected { background: linear-gradient(90deg, #f8d7da 0%, #ffffff 40%); }
.date-label { font-weight: 700; }
.amt-cell { font-weight: 800; }
</style>

<div class="page-wrapper">
    <div class="page-content">
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-4">
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="index.php"><i class="bx bx-home-alt"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Date-wise Payment Report (Last 200 Days)</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-md">
                <div class="summary-card bg-white">
                    <div class="small-title text-muted mb-1">Total Successful (200d)</div>
                    <div class="metric-value text-success">
                        ₹ <?php echo number_format($totalSuccessAmtAll, 2); ?>
                    </div>
                    <small class="text-muted"><?php echo $totalSuccessAll; ?> transactions</small>
                </div>
            </div>
            <div class="col-6 col-md">
                <div class="summary-card bg-white">
                    <div class="small-title text-muted mb-1">Total Pending (200d)</div>
                    <div class="metric-value text-warning">
                        ₹ <?php echo number_format($totalPendingAmtAll, 2); ?>
                    </div>
                    <small class="text-muted"><?php echo $totalPendingAll; ?> transactions (not deposited yet)</small>
                </div>
            </div>
            <div class="col-6 col-md">
                <div class="summary-card bg-white">
                    <div class="small-title text-muted mb-1">Total Rejected (200d)</div>
                    <div class="metric-value text-danger">
                        ₹ <?php echo number_format($totalRejectedAmtAll, 2); ?>
                    </div>
                    <small class="text-muted"><?php echo $totalRejectedAll; ?> transactions</small>
                </div>
            </div>
            <div class="col-6 col-md">
                <div class="summary-card bg-white" style="background:linear-gradient(135deg,#e7f1ff 0%,#ffffff 100%);">
                    <div class="small-title text-muted mb-1">Net (Success - Pending - Rejected)</div>
                    <div class="metric-value text-primary">
                        ₹ <?php echo number_format($totalSuccessAmtAll - $totalPendingAmtAll - $totalRejectedAmtAll, 2); ?>
                    </div>
                    <small class="text-muted">Success minus pending and rejected</small>
                </div>
            </div>
            <div class="col-6 col-md">
                <div class="summary-card bg-white" style="background:linear-gradient(135deg,#e8f5e9 0%,#ffffff 100%);">
                    <div class="small-title text-muted mb-1">New Users Registered (200d)</div>
                    <div class="metric-value text-primary">
                        <?php echo number_format($totalUsersAll); ?>
                    </div>
                    <small class="text-muted">Total new signups (excl. admins)</small>
                </div>
            </div>
        </div>

        <div class="card radius-10">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap">
                    <div>
                        <h5 class="mb-0">📅 Date-wise Payment Records (Last 200 Days)</h5>
                        <p class="text-muted mb-0">
                            Successful = Amount confirmed received &nbsp; | &nbsp;
                            Pending = User tried but deposit NOT confirmed yet &nbsp; | &nbsp;
                            Rejected = Admin rejected
                        </p>
                    </div>
                    <a href="qrpay_pending_v2.php?tab=success" class="btn btn-success btn-sm">
                        <i class="bx bx-list-check me-1"></i> View All Success
                    </a>
                </div>
                <hr>
                <div class="table-responsive">
                    <table id="example2" class="table align-middle table-hover mb-0" style="width:100%;">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width:80px;">#</th>
                                <th class="text-center" style="width:150px;">Date</th>
                                <th class="text-center" style="width:130px;">👥 New Users</th>
                                <th class="text-center text-success">✅ Successful Amount</th>
                                <th class="text-center text-success">Success Count</th>
                                <th class="text-center text-warning">⏳ Pending Amount</th>
                                <th class="text-center text-warning">Pending Count</th>
                                <th class="text-center text-danger">❌ Rejected Amount</th>
                                <th class="text-center text-danger">Rejected Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $x = 0;
                            foreach ($dateRecords as $rec) {
                                $x++;
                                $displayDate = date('d M Y (D)', strtotime($rec['date']));
                                $isToday = ($rec['date'] === date('Y-m-d'));
                                $rowClass = '';
                                if ($rec['success_cnt'] > 0 && $rec['success_amt'] >= $rec['pending_amt']) $rowClass = 'row-success';
                                elseif ($rec['pending_cnt'] > 0 && $rec['pending_amt'] > $rec['success_amt']) $rowClass = 'row-pending';
                                elseif ($rec['rejected_cnt'] > 0) $rowClass = 'row-rejected';
                                $todayBadge = $isToday ? '<span class="badge bg-danger ms-2">TODAY</span>' : '';
                            ?>
                            <tr class="<?php echo $rowClass; ?>">
                                <td class="text-center"><?php echo $x; ?></td>
                                <td class="text-center date-label">
                                    <?php echo $displayDate; ?>
                                    <?php echo $todayBadge; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($rec['user_reg_cnt'] > 0) { ?>
                                        <span class="badge bg-primary"><?php echo $rec['user_reg_cnt']; ?></span>
                                    <?php } else { ?>
                                        <span class="text-muted">0</span>
                                    <?php } ?>
                                </td>
                                <td class="text-center amt-cell text-success">
                                    <?php if ($rec['success_cnt'] > 0) { ?>
                                        ₹ <?php echo number_format($rec['success_amt'], 2); ?>
                                    <?php } else { ?>
                                        <span class="text-muted">—</span>
                                    <?php } ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($rec['success_cnt'] > 0) { ?>
                                        <span class="badge bg-success"><?php echo $rec['success_cnt']; ?></span>
                                    <?php } else { ?>
                                        <span class="text-muted">0</span>
                                    <?php } ?>
                                </td>
                                <td class="text-center amt-cell text-warning">
                                    <?php if ($rec['pending_cnt'] > 0) { ?>
                                        ₹ <?php echo number_format($rec['pending_amt'], 2); ?>
                                    <?php } else { ?>
                                        <span class="text-muted">—</span>
                                    <?php } ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($rec['pending_cnt'] > 0) { ?>
                                        <span class="badge bg-warning text-dark"><?php echo $rec['pending_cnt']; ?></span>
                                    <?php } else { ?>
                                        <span class="text-muted">0</span>
                                    <?php } ?>
                                </td>
                                <td class="text-center amt-cell text-danger">
                                    <?php if ($rec['rejected_cnt'] > 0) { ?>
                                        ₹ <?php echo number_format($rec['rejected_amt'], 2); ?>
                                    <?php } else { ?>
                                        <span class="text-muted">—</span>
                                    <?php } ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($rec['rejected_cnt'] > 0) { ?>
                                        <span class="badge bg-danger"><?php echo $rec['rejected_cnt']; ?></span>
                                    <?php } else { ?>
                                        <span class="text-muted">0</span>
                                    <?php } ?>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                        <tfoot class="table-dark fw-bold">
                            <tr>
                                <td colspan="2" class="text-end">TOTAL (200 Days)</td>
                                <td class="text-center"><span class="badge bg-primary"><?php echo $totalUsersAll; ?></span></td>
                                <td class="text-center text-success">₹ <?php echo number_format($totalSuccessAmtAll, 2); ?></td>
                                <td class="text-center"><span class="badge bg-success"><?php echo $totalSuccessAll; ?></span></td>
                                <td class="text-center text-warning">₹ <?php echo number_format($totalPendingAmtAll, 2); ?></td>
                                <td class="text-center"><span class="badge bg-warning text-dark"><?php echo $totalPendingAll; ?></span></td>
                                <td class="text-center text-danger">₹ <?php echo number_format($totalRejectedAmtAll, 2); ?></td>
                                <td class="text-center"><span class="badge bg-danger"><?php echo $totalRejectedAll; ?></span></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    if ($.fn.DataTable) {
        $('#example2').DataTable({
            "pageLength": 50,
            "order": [],
            "dom": 'Bfrtip',
            "buttons": [
                { extend: 'excel', text: '<i class="bx bx-spreadsheet me-1"></i> Excel', className: 'btn btn-success btn-sm' },
                { extend: 'csv', text: '<i class="bx bx-file me-1"></i> CSV', className: 'btn btn-info btn-sm' },
                { extend: 'print', text: '<i class="bx bx-printer me-1"></i> Print', className: 'btn btn-secondary btn-sm' },
                'pageLength'
            ]
        });
    }
});
</script>
