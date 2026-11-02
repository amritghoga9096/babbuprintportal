<?php
include('../includes/session.php');
include('../includes/config.php');
include('samagra_pdf_common.php');

// ===== DEBUG MODE: 500 error ke liye exception + error handler =====
$gpp_debug_errors = [];
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return;
    $GLOBALS['gpp_debug_errors'][] = "[$severity] $message in $file:$line";
    return true;
});
set_exception_handler(function ($e) {
    $msg = $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
    error_log('samagra_pdf_list FATAL: ' . $msg);
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Error</title><script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script></head><body>';
    echo '<script>Swal.fire({icon:"error",title:"Page Load Error",text:"Debug: ' . htmlspecialchars($msg, ENT_QUOTES) . '",footer:"No data corruption occurred."}).then(()=>window.history.back());</script>';
    echo '</body></html>';
    exit;
});
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $msg = $err['message'] . ' in ' . $err['file'] . ':' . $err['line'];
        error_log('samagra_pdf_list SHUTDOWN FATAL: ' . $msg);
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Fatal Error</title><script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script></head><body>';
        echo '<script>Swal.fire({icon:"error",title:"Fatal Error",text:"Debug: ' . htmlspecialchars($msg, ENT_QUOTES) . '",footer:"Refresh or go back and try again."}).then(()=>window.history.back());</script>';
        echo '</body></html>';
    }
});
// ===================================================================

checkSession();
ensureSamagraPdfTable($ahk_conn);
include('../template/ahkweb/header.php');

$phone = (string) ($udata['phone'] ?? '');
if ($phone === '') {
    echo '<script>Swal.fire({icon:"warning",title:"Session Expired",text:"Please login again."}).then(()=>{window.location="../login.php";});</script>';
    include('footers.php');
    exit;
}

$stmt = mysqli_prepare($ahk_conn, "SELECT * FROM samagra_pdf_requests WHERE user_phone=? ORDER BY id DESC");
if (!$stmt) {
    echo '<script>Swal.fire({icon:"error",title:"DB Error (prepare)",text:"Debug: ' . htmlspecialchars(mysqli_error($ahk_conn), ENT_QUOTES) . '"});</script>';
    $requests = [];
} else {
    mysqli_stmt_bind_param($stmt, 's', $phone);
    if (!mysqli_stmt_execute($stmt)) {
        echo '<script>Swal.fire({icon:"error",title:"DB Error (execute)",text:"Debug: ' . htmlspecialchars(mysqli_stmt_error($stmt), ENT_QUOTES) . '"});</script>';
        $requests = [];
    } else {
        $requests = db_stmt_fetch_all_assoc($stmt);
    }
    @mysqli_stmt_close($stmt);
}
?>
<div class="page-wrapper"><div class="page-content">
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">My Samagra PDF Requests</h4>
    <a href="samagra_pdf_without_otp.php" class="btn btn-primary">New Request</a>
</div>
<div class="card"><div class="card-body"><div class="table-responsive">
<table class="table table-bordered align-middle">
<thead><tr><th>Request</th><th>Samagra ID</th><th>Amount</th><th>Status</th><th>Admin Details</th><th>Result</th><th>Date</th></tr></thead>
<tbody>
<?php if (!empty($requests)): ?>
<?php foreach ($requests as $row): ?>
<tr>
    <td><?= htmlspecialchars($row['request_no']) ?></td>
    <td><?= htmlspecialchars($row['samagra_id']) ?><br><small><?= ucfirst($row['id_type']) ?> ID</small></td>
    <td>Rs. <?= number_format((float) $row['amount'], 2) ?></td>
    <td><span class="badge bg-<?= $row['status'] === 'approved' ? 'success' : ($row['status'] === 'rejected' ? 'danger' : 'warning') ?>"><?= ucfirst($row['status']) ?></span></td>
    <td><?= nl2br(htmlspecialchars($row['status'] === 'rejected' ? ($row['rejection_reason'] ?? 'Request rejected') : ($row['admin_details'] ?? '-'))) ?></td>
    <td><?php if ($row['status'] === 'approved' && !empty($row['pdf_path'])): ?><a class="btn btn-sm btn-success" href="samagra_pdf_download.php?id=<?= (int) $row['id'] ?>">Download PDF</a><?php else: ?>-<?php endif; ?></td>
    <td><?= htmlspecialchars($row['created_at']) ?></td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr><td colspan="7" class="text-center">No requests found.</td></tr>
<?php endif; ?>
</tbody></table>
</div></div></div>
</div></div>
<?php
if (isset($_GET['submitted'])) {
    samagraAlert('Request Submitted', 'Rs. ' . SAMAGRA_PDF_FEE . ' deducted. Your request has been sent to admin.', 'success');
}
if (!empty($gpp_debug_errors)) {
    echo '<script>console.log(' . json_encode($gpp_debug_errors, JSON_UNESCAPED_UNICODE) . ');</script>';
}
include('footers.php');
?>
