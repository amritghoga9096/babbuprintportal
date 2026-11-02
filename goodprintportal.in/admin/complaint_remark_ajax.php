<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

include(__DIR__ . '/../includes/session.php');
include(__DIR__ . '/../includes/config.php');

$out = ['ok' => false, 'msg' => 'Invalid request'];

if (!isset($udata) || empty($udata['id'])) {
    echo json_encode($out); exit;
}
$userId = (int)$udata['id'];

$action = isset($_REQUEST['action']) ? trim((string)$_REQUEST['action']) : '';

if ($action === 'mark_seen_all') {
    $sql = "UPDATE complaints SET remark_seen = 1
            WHERE user_id = {$userId}
              AND remark IS NOT NULL
              AND TRIM(remark) <> ''";
    $ok = @mysqli_query($GLOBALS['ahk_conn'], $sql);
    if ($ok) {
        $out = ['ok' => true, 'msg' => 'All marked seen',
                'remaining' => _userUnreadComplaintCount($GLOBALS['ahk_conn'], $userId)];
    } else {
        $out = ['ok' => false, 'msg' => 'Update failed: ' . mysqli_error($GLOBALS['ahk_conn'])];
    }
    echo json_encode($out); exit;
}

if ($action === 'mark_seen' && !empty($_REQUEST['cid'])) {
    $cid = (int)$_REQUEST['cid'];
    $sql = "UPDATE complaints SET remark_seen = 1
            WHERE complaint_id = {$cid} AND user_id = {$userId}
              AND remark IS NOT NULL AND TRIM(remark) <> ''
            LIMIT 1";
    $ok = @mysqli_query($GLOBALS['ahk_conn'], $sql);
    if ($ok) {
        $out = ['ok' => true, 'msg' => 'Marked seen',
                'remaining' => _userUnreadComplaintCount($GLOBALS['ahk_conn'], $userId)];
    } else {
        $out = ['ok' => false, 'msg' => 'Update failed'];
    }
    echo json_encode($out); exit;
}

if ($action === 'unread_count') {
    $out = ['ok' => true,
            'count' => _userUnreadComplaintCount($GLOBALS['ahk_conn'], $userId),
            'complaints' => _userUnreadComplaints($GLOBALS['ahk_conn'], $userId, 5)];
    echo json_encode($out); exit;
}

echo json_encode($out);
exit;

function _userUnreadComplaintCount($conn, $uid) {
    if (!$conn) return 0;
    $uid = (int)$uid;
    $r = @mysqli_query($conn,
        "SELECT COUNT(*) AS c FROM complaints
         WHERE user_id = {$uid}
           AND remark IS NOT NULL AND TRIM(remark) <> ''
           AND (remark_seen IS NULL OR remark_seen = 0)"
    );
    if ($r && ($row = mysqli_fetch_assoc($r))) return (int)$row['c'];
    return 0;
}

function _userUnreadComplaints($conn, $uid, $limit=5) {
    if (!$conn) return [];
    $uid = (int)$uid;
    $limit = (int)$limit;
    $out = [];
    $r = @mysqli_query($conn,
        "SELECT complaint_id, complaint_type, status, remark,
                remark_updated_at, date_time
         FROM complaints
         WHERE user_id = {$uid}
           AND remark IS NOT NULL AND TRIM(remark) <> ''
           AND (remark_seen IS NULL OR remark_seen = 0)
         ORDER BY COALESCE(remark_updated_at, date_time) DESC
         LIMIT {$limit}"
    );
    if ($r) while ($row = mysqli_fetch_assoc($r)) $out[] = $row;
    return $out;
}
