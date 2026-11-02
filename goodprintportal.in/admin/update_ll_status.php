<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
include('../includes/config.php');
include('../includes/session.php');
require_once __DIR__ . '/../includes/pp_secure.php';

header('Content-Type: application/json');

// ============================================================================
// LL Exam status update + auto refund
// SECURITY: the status sent by the browser is IGNORED. The real status is read
// from PariPrint here on the server, so a user cannot post "rejected" to get a
// refund. A rejected (refunded) application is final: refund happens only once.
// ============================================================================

/** Map a PariPrint status text to our DB value (same mapping as before). */
function ll_map_status(string $raw): string {
    $low = strtolower(trim($raw));
    if (in_array($low, ['passed','pass','success','completed','ok','true','1','approved'], true)) return 'passed';
    if (in_array($low, ['failed','fail','rejected','reject','error','denied','false','0'], true)) return 'rejected';
    return 'pending';
}

if (!isset($_POST['order_id'])) {
    echo json_encode(['status' => false, 'message' => 'Invalid Data']);
    exit;
}

$mobile = isset($udata['phone']) ? (string)$udata['phone'] : '';
if ($mobile === '') {
    echo json_encode(['status' => false, 'message' => 'Login required.']);
    exit;
}

$order_id = mysqli_real_escape_string($ahk_conn, trim((string)$_POST['order_id']));
$safeMobile = mysqli_real_escape_string($ahk_conn, $mobile);

// ---- The user's own record ----
$where = "(tracking_id='$order_id' OR application_no='$order_id') AND userid='$safeMobile'";
if (strlen($mobile) === 10) {
    $suff = mysqli_real_escape_string($ahk_conn, '%' . substr($mobile, -10));
    $where = "(tracking_id='$order_id' OR application_no='$order_id') AND (userid='$safeMobile' OR userid LIKE '$suff')";
}
$sr = @mysqli_query($ahk_conn, "SELECT id, tracking_id, application_no, userid, amount, status FROM ll_exam_list WHERE $where LIMIT 1");
$ll_old = ($sr && mysqli_num_rows($sr) > 0) ? mysqli_fetch_assoc($sr) : null;
if (!$ll_old) {
    echo json_encode(['status' => false, 'message' => 'Update Failed: record not found']);
    exit;
}
$rowId = (int)$ll_old['id'];
$old_status = strtolower((string)($ll_old['status'] ?? ''));

// Rejected = already refunded = final.
if ($old_status === 'rejected') {
    echo json_encode(['status' => true, 'message' => 'Status Updated to rejected', 'applied_status' => 'rejected',
        'refund' => false, 'refund_amount' => 0, 'refund_message' => 'Already refunded earlier.']);
    exit;
}

// ---- Real status from PariPrint (server side, key never reaches the browser) ----
$tracking = trim((string)($ll_old['tracking_id'] ?? '')) !== '' ? (string)$ll_old['tracking_id'] : (string)$ll_old['application_no'];
$r = pp_http(pp_api_base() . '/api-proxy.php?slug=ll-exam-request&api_key=' . rawurlencode(pp_api_key()) . '&order_id=' . rawurlencode($tracking), 'GET', [], 30);
$data = pp_json($r['body']);
if (!is_array($data)) {
    echo json_encode(['status' => false, 'message' => 'Update Failed: PariPrint status not available, try again.']);
    exit;
}
$ppRaw = '';
foreach (['status_name', 'status_text', 'service_status', 'status'] as $k) {
    if (isset($data[$k]) && is_string($data[$k])) { $ppRaw = $data[$k]; break; }
}
$new_status = ll_map_status($ppRaw);

// ---- Update status; for "rejected" the update itself is the once-only refund lock ----
$refundDone = false;
$refundMsg = '';
$refundAmount = 0;
if ($new_status === 'rejected') {
    @mysqli_query($ahk_conn, "UPDATE ll_exam_list SET status='rejected' WHERE id=$rowId AND status<>'rejected' LIMIT 1");
    $won = (int)mysqli_affected_rows($ahk_conn) === 1;   // only one request can win this
    if ($won) {
        try {
            $refAmt = (float)($ll_old['amount'] ?? 0);
            $refUser = (string)($ll_old['userid'] ?? '');
            $refAppNo = (string)($ll_old['application_no'] ?? $ll_old['tracking_id'] ?? '');
            $safePur  = mysqli_real_escape_string($ahk_conn, 'LL Exam Rejected Refund - App:'.$refAppNo);
            $safeUser = mysqli_real_escape_string($ahk_conn, $refUser);

            // Extra guard for records refunded by the old code
            $already = @mysqli_query($ahk_conn, "SELECT 1 FROM wallethistory WHERE userid='$safeUser' AND purpose='$safePur' AND type='Credit' LIMIT 1");
            if ($already && mysqli_num_rows($already) > 0) {
                $refundMsg = 'Already refunded earlier.';
            } elseif ($refAmt > 0 && $refUser !== '') {
                $refAmtI = (int)$refAmt == $refAmt ? (int)$refAmt : number_format($refAmt, 2, '.', '');

                // 1) Credit wallet balance
                $wUpd = @mysqli_query($ahk_conn, "UPDATE users SET balance = balance + {$refAmtI} WHERE phone='$safeUser' LIMIT 1");
                if ($wUpd) {
                    // 2) New balance for wallethistory
                    $newBal = 0;
                    $ubR = @mysqli_query($ahk_conn, "SELECT balance FROM users WHERE phone='$safeUser' LIMIT 1");
                    if ($ubR && ($ubrw = mysqli_fetch_assoc($ubR))) $newBal = (float)($ubrw['balance'] ?? 0);

                    // 3) wallethistory (type=Credit)
                    date_default_timezone_set("Asia/Kolkata");
                    $safeAmt  = mysqli_real_escape_string($ahk_conn, (string)$refAmtI);
                    $safeBal  = mysqli_real_escape_string($ahk_conn, (string)$newBal);
                    @mysqli_query($ahk_conn, "INSERT INTO wallethistory(userid, amount, balance, purpose, status, type, date)
                        VALUES ('$safeUser', '$safeAmt', '$safeBal', '$safePur', '1', 'Credit', NOW())");

                    // 4) transactions table
                    $uId = 0;
                    $uidR = @mysqli_query($ahk_conn, "SELECT id FROM users WHERE phone='$safeUser' LIMIT 1");
                    if ($uidR && ($uidrw = mysqli_fetch_assoc($uidR))) $uId = (int)($uidrw['id'] ?? 0);
                    if ($uId > 0) {
                        $txnRef = 'LLREF-' . strtoupper(substr(md5(uniqid((string)mt_rand() . $refAppNo, true)), 0, 10));
                        $desc = 'LL Exam Rejected Refund Rs.'.$refAmtI.' | App: '.$refAppNo.' | User: '.$refUser;
                        $stIns = @mysqli_prepare($ahk_conn, "INSERT INTO transactions (uid, amount, description, txn_ref, date, status) VALUES (?, ?, ?, ?, NOW(), 'success')");
                        if ($stIns) {
                            $dAmt = (float)$refAmtI;
                            @mysqli_stmt_bind_param($stIns, 'idss', $uId, $dAmt, $desc, $txnRef);
                            @mysqli_stmt_execute($stIns);
                            @mysqli_stmt_close($stIns);
                        }
                    }

                    $refundDone = true;
                    $refundAmount = $refAmt;
                    $refundMsg = '₹' . number_format($refAmt, 2) . ' refunded to wallet.';
                }
            }
        } catch (Throwable $e) {
            $refundMsg = 'Refund error. Support se contact karein.';
        }
    } else {
        $refundMsg = 'Already refunded earlier.';
    }
} else {
    @mysqli_query($ahk_conn, "UPDATE ll_exam_list SET status='" . mysqli_real_escape_string($ahk_conn, $new_status) . "' WHERE id=$rowId AND status<>'rejected' LIMIT 1");
}

$resp = ['status' => true, 'message' => 'Status Updated to '.$new_status, 'applied_status' => $new_status];
if ($new_status === 'rejected') {
    $resp['refund'] = $refundDone;
    $resp['refund_amount'] = $refundAmount;
    if ($refundMsg !== '') $resp['refund_message'] = $refundMsg;
}
echo json_encode($resp);
