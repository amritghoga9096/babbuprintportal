<?php
include('../includes/session.php');
include('../includes/config.php');
include('samagra_recover_member_common.php');
checkSession();

if (!checkAdmin($udata['type'])) {
    header('Location: index.php');
    exit;
}
ensureSamagraRecoverMemberTable($ahk_conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $details = trim($_POST['admin_details'] ?? '');
    $reason = trim($_POST['rejection_reason'] ?? '');

    if ($action === 'approve') {
        $resultPdfPath = null;
        if (isset($_FILES['result_pdf']) && $_FILES['result_pdf']['error'] === UPLOAD_ERR_OK) {
            if ((int) $_FILES['result_pdf']['size'] > SAMAGRA_RECOVER_MEMBER_MAX_FILE_SIZE) {
                $adminAlert = ['Invalid File', 'Result PDF must be 5MB or smaller.', 'error'];
            } else {
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['result_pdf']['tmp_name']);
                if ($mime !== 'application/pdf') {
                    $adminAlert = ['Invalid File', 'Only PDF files are allowed.', 'error'];
                } else {
                    $directory = __DIR__ . '/uploads/samagra_recover_member/results/';
                    if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
                        $adminAlert = ['Upload Failed', 'Could not create the upload directory.', 'error'];
                    } else {
                        $filename = bin2hex(random_bytes(16)) . '.pdf';
                        if (move_uploaded_file($_FILES['result_pdf']['tmp_name'], $directory . $filename)) {
                            $resultPdfPath = 'uploads/samagra_recover_member/results/' . $filename;
                        } else {
                            $adminAlert = ['Upload Failed', 'Could not save the PDF file.', 'error'];
                        }
                    }
                }
            }
        }

        if (!isset($adminAlert) && !$resultPdfPath && $details === '') {
            $adminAlert = ['Result Required', 'Upload a PDF or enter result details.', 'warning'];
        }

        if (!isset($adminAlert)) {
            if ($resultPdfPath) {
                $stmt = mysqli_prepare($ahk_conn, "UPDATE samagra_recover_member_requests SET status='approved', admin_details=?, result_pdf_path=?, rejection_reason=NULL WHERE id=? AND status='pending'");
                mysqli_stmt_bind_param($stmt, 'ssi', $details, $resultPdfPath, $id);
            } else {
                $stmt = mysqli_prepare($ahk_conn, "UPDATE samagra_recover_member_requests SET status='approved', admin_details=?, rejection_reason=NULL WHERE id=? AND status='pending'");
                mysqli_stmt_bind_param($stmt, 'si', $details, $id);
            }
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $adminAlert = ['Request Approved', 'The user can now view details or download the PDF.', 'success'];
        }
    } elseif ($action === 'reject' && $reason !== '') {
        $stmt = mysqli_prepare($ahk_conn, "UPDATE samagra_recover_member_requests SET status='rejected', rejection_reason=? WHERE id=? AND status='pending'");
        mysqli_stmt_bind_param($stmt, 'si', $reason, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $adminAlert = ['Request Rejected', 'The rejection reason is visible to the user.', 'success'];
    } else {
        $adminAlert = ['Reason Required', 'Enter a rejection reason.', 'warning'];
    }
}

include('../template/ahkweb/header.php');
$requests = mysqli_query($ahk_conn, "SELECT * FROM samagra_recover_member_requests ORDER BY FIELD(status,'pending','approved','rejected'), id DESC");
?>
<div class="page-wrapper"><div class="page-content">
<h4>Recover Member Requests</h4>
<div class="card"><div class="card-body"><div class="table-responsive">
<table class="table table-bordered align-middle">
<thead><tr><th>Application/User</th><th>Full Name</th><th>Family ID</th><th>Member ID</th><th>User PDF</th><th>Status</th><th>Submitted</th><th style="min-width:340px">Admin Action</th></tr></thead>
<tbody>
<?php while ($row = mysqli_fetch_assoc($requests)): ?>
<tr>
<td><?= htmlspecialchars($row['application_no']) ?><br><small><?= htmlspecialchars($row['user_phone']) ?> | Rs. <?= number_format((float)$row['amount'], 2) ?></small></td>
<td><?= htmlspecialchars($row['full_name']) ?></td>
<td><?= htmlspecialchars($row['family_id']) ?></td>
<td><?= htmlspecialchars($row['member_id']) ?></td>
<td><a class="btn btn-sm btn-outline-primary" href="samagra_recover_member_download.php?id=<?= (int)$row['id'] ?>&file=userdoc">Member Aadhar PDF</a></td>
<td><strong><?= ucfirst($row['status']) ?></strong></td>
<td><?= htmlspecialchars($row['created_at']) ?></td>
<td>
<?php if ($row['status'] === 'pending'): ?>
<form method="post" enctype="multipart/form-data">
    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
    <textarea class="form-control mb-2" name="admin_details" rows="2" placeholder="Result / recover member details"></textarea>
    <input class="form-control mb-2" type="file" name="result_pdf" accept="application/pdf">
    <button class="btn btn-success btn-sm" name="action" value="approve">Approve</button>
    <div class="input-group mt-2">
        <input class="form-control" name="rejection_reason" placeholder="Rejection reason">
        <button class="btn btn-danger btn-sm" name="action" value="reject">Reject</button>
    </div>
</form>
<?php else: ?>
<?= nl2br(htmlspecialchars($row['status'] === 'rejected' ? $row['rejection_reason'] : $row['admin_details'])) ?>
<?php if ($row['result_pdf_path']): ?><br><a href="samagra_recover_member_download.php?id=<?= (int)$row['id'] ?>&file=result" class="btn btn-sm btn-primary mt-2">Download Result PDF</a><?php endif; ?>
<?php endif; ?>
</td>
</tr>
<?php endwhile; ?>
</tbody></table>
</div></div></div>
</div></div>
<?php
if (isset($adminAlert)) {
    samagraRecoverMemberAlert($adminAlert[0], $adminAlert[1], $adminAlert[2], 'samagra_recover_member_admin.php');
}
include('footers.php');
?>
