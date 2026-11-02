<?php
include('../includes/session.php');
include('../includes/config.php');

$mobile = $udata['phone'] ?? '';
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0 || $mobile === '') {
    header('Location: ll_request_list.php');
    exit;
}

$db = $ahk_conn;
$stmt = mysqli_prepare($db, "SELECT id, application_no, tracking_id FROM ll_exam_list WHERE id = ? AND userid = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'is', $id, $mobile);
mysqli_stmt_execute($stmt);
$row = db_stmt_fetch_assoc($stmt);
mysqli_stmt_close($stmt);

if (!$row) {
    $_SESSION['toast_error'] = 'Record not found.';
    header('Location: ll_request_list.php');
    exit;
}

$delete = mysqli_prepare($db, "DELETE FROM ll_exam_list WHERE id = ? AND userid = ? LIMIT 1");
mysqli_stmt_bind_param($delete, 'is', $id, $mobile);
mysqli_stmt_execute($delete);
$ok = mysqli_stmt_affected_rows($delete) > 0;
mysqli_stmt_close($delete);

$_SESSION['toast_success'] = $ok ? 'Record removed successfully.' : 'Failed to remove record.';
header('Location: ll_request_list.php');
exit;
