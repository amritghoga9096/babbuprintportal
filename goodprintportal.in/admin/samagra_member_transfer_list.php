<?php
include('../includes/session.php');
include('../includes/config.php');
include('samagra_member_transfer_common.php');
checkSession();
ensureSamagraMemberTransferTable($ahk_conn);
include('../template/ahkweb/header.php');

$phone = (string) ($udata['phone'] ?? '');
$stmt = mysqli_prepare($ahk_conn, "SELECT * FROM samagra_member_transfer_requests WHERE user_phone=? ORDER BY id DESC");
mysqli_stmt_bind_param($stmt, 's', $phone);
mysqli_stmt_execute($stmt);
$requests = db_stmt_fetch_all_assoc($stmt);
?>
<div class="page-wrapper"><div class="page-content">
<div class="d-flex justify-content-between align-items-center mb-3">
<h4 class="mb-0">My Member Transfer Requests</h4>
<a href="samagra_member_transfer.php" class="btn btn-primary">New Request</a>
</div>
<div class="card"><div class="card-body"><div class="table-responsive">
<table class="table table-bordered align-middle">
<thead><tr><th>Application</th><th>Husband Details</th><th>Wife Details</th><th>Amount</th><th>Status</th><th>Admin Details</th><th>Result</th><th>Date</th></tr></thead>
<tbody>
<?php foreach ($requests as $row): ?>
<tr>
<td><?= htmlspecialchars($row['application_no']) ?></td>
<td><?= htmlspecialchars($row['husband_name']) ?><br><small>Family: <?= htmlspecialchars($row['husband_family_id']) ?><br>Member: <?= htmlspecialchars($row['husband_member_id']) ?></small></td>
<td><?= htmlspecialchars($row['wife_name']) ?><br><small>Family: <?= htmlspecialchars($row['wife_family_id']) ?><br>Member: <?= htmlspecialchars($row['wife_member_id']) ?></small></td>
<td>Rs. <?= number_format((float)$row['amount'], 2) ?></td>
<td><span class="badge bg-<?= $row['status'] === 'approved' ? 'success' : ($row['status'] === 'rejected' ? 'danger' : 'warning') ?>"><?= ucfirst($row['status']) ?></span></td>
<td><?= nl2br(htmlspecialchars($row['status'] === 'rejected' ? ($row['rejection_reason'] ?: 'Request rejected') : ($row['admin_details'] ?: '-'))) ?></td>
<td><?php if ($row['status'] === 'approved' && $row['pdf_path']): ?><a class="btn btn-sm btn-success" href="samagra_member_transfer_download.php?id=<?= (int)$row['id'] ?>">Download PDF</a><?php else: ?>-<?php endif; ?></td>
<td><?= htmlspecialchars($row['created_at']) ?></td>
</tr>
<?php endforeach; ?>
<?php if (count($requests) === 0): ?><tr><td colspan="8" class="text-center">No requests found.</td></tr><?php endif; ?>
</tbody></table>
</div></div></div></div></div>
<?php
@mysqli_stmt_close($stmt);
if (isset($_GET['submitted'])) {
    samagraMemberTransferAlert('Request Submitted', 'Rs. ' . SAMAGRA_MEMBER_TRANSFER_FEE . ' deducted. Your request has been sent to admin.', 'success');
}
include('footers.php');
?>
