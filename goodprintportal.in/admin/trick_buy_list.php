<?php
// ============================================================
// 🔥 ULTRA-SAFE DEBUG HANDLERS - SABSE PEHLE (includes se bhi pehle)
//  PHP 7.4 compatible, procedural, no closures
// ============================================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('html_errors', 0);
error_reporting(E_ALL);

$GLOBALS['_tlist_debug'] = [
    'errors'     => [],
    'page_title' => 'Trick Buy Orders'
];

if (!function_exists('_tlist_err_handler')) {
    function _tlist_err_handler($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) return false;
        $map = [
            E_WARNING => 'E_WARNING', E_NOTICE => 'E_NOTICE',
            E_STRICT => 'E_STRICT', E_DEPRECATED => 'E_DEPRECATED',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_USER_ERROR => 'E_USER_ERROR', E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE', E_USER_DEPRECATED => 'E_USER_DEPRECATED'
        ];
        $typeName = isset($map[$severity]) ? $map[$severity] : 'E_#'.$severity;
        $GLOBALS['_tlist_debug']['errors'][] = "[$typeName] $message in $file line $line";
        @error_log("TLIST_WARN: [$typeName] $message in $file:$line");
        return true;
    }

    function _tlist_render_fatal($title, $body, $footer = null) {
        while (ob_get_level()) { @ob_end_clean(); }
        if (!headers_sent()) { @header('Content-Type: text/html; charset=utf-8'); }
        $safeTitle = htmlspecialchars((string)$title, ENT_QUOTES);
        $safeBody = htmlspecialchars((string)$body, ENT_QUOTES);
        $warnings = '';
        if (!empty($GLOBALS['_tlist_debug']['errors'])) {
            foreach ($GLOBALS['_tlist_debug']['errors'] as $w) { $warnings .= htmlspecialchars($w, ENT_QUOTES)."\n"; }
        }
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Error - ', $safeTitle, '</title>';
        echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';
        echo '<style>
            body{font-family:system-ui,Arial,sans-serif;background:#1a1a1a;color:#fff;margin:0;padding:18px}
            .ebox{max-width:720px;margin:24px auto;background:#2a2a2a;border:1px solid #444;border-radius:12px;padding:18px 20px}
            .etitle{font-size:20px;font-weight:700;color:#ff6b6b;margin-bottom:10px}
            .ebody{background:#111;padding:12px;border-radius:8px;white-space:pre-wrap;word-break:break-word;margin:10px 0;color:#ffd966;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:13px;line-height:1.5;max-height:260px;overflow:auto}
            .ebtn{display:inline-block;margin-top:10px;padding:9px 16px;background:#4f46e5;color:#fff;border:0;border-radius:8px;cursor:pointer;font-weight:600}
            .efoot{color:#aaa;font-size:12px;margin-top:12px}
            details{margin-top:10px}summary{cursor:pointer;color:#60a5fa}
        </style></head><body>';
        echo '<div class="ebox">';
        echo '<div class="etitle">🚨 ', $safeTitle, '</div>';
        echo '<div class="ebody">', $safeBody, '</div>';
        if ($footer) echo '<div class="efoot">', htmlspecialchars((string)$footer), '</div>';
        if (!empty($GLOBALS['_tlist_debug']['errors'])) {
            $cnt = count($GLOBALS['_tlist_debug']['errors']);
            echo '<details><summary>Show PHP Warnings (', $cnt, ')</summary><div class="ebody" style="color:#a78bfa;margin-top:6px">', htmlspecialchars($warnings), '</div></details>';
        }
        echo '<br><button class="ebtn" onclick="window.history.back();">← Back</button>';
        echo ' &nbsp; <button class="ebtn" style="background:#10b981" onclick="window.location.href=\'index.php\'">🏠 Dashboard</button>';
        echo '</div>';
        echo '<script>
            (function(){
                var opts = {icon:"error",showConfirmButton:true,confirmButtonText:"OK",title:', json_encode($safeTitle), ', text:', json_encode((string)$body), '};
                var fired = false;
                function fb(){ if(fired)return; fired=true; alert(', json_encode($safeTitle."\n\n".$body), '); if(window.history.length>1) window.history.back(); }
                function fs(){ if(fired)return; fired=true; if(typeof Swal!=="undefined"){ Swal.fire(opts).then(function(){ if(window.history.length>1) window.history.back(); else window.location.href="index.php";});} else { fb(); } }
                try{ fs(); }catch(e){ fb(); }
                setTimeout(function(){ try{ fs(); }catch(e){ fb(); } }, 1500);
            })();
        </script>';
        if (!empty($GLOBALS['_tlist_debug']['errors'])) {
            echo '<script>try{console.log(' . json_encode($GLOBALS['_tlist_debug']['errors'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ');}catch(e){}</script>';
        }
        echo '</body></html>';
        exit;
    }

    function _tlist_exception_handler($e) {
        $msg = $e->getMessage() . "\nFile: " . $e->getFile() . "\nLine: " . $e->getLine();
        @error_log("TLIST_EXCEPTION: " . $e->getMessage() . " " . $e->getFile() . ":" . $e->getLine());
        _tlist_render_fatal('PHP Exception (Trick Orders)', $msg, 'Admin ko screenshot bhejo');
    }

    function _tlist_shutdown_handler() {
        $err = error_get_last();
        if (!$err) return;
        $fatals = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR);
        if (!in_array($err['type'], $fatals, true)) return;
        $msg = $err['message'] . "\nFile: " . $err['file'] . "\nLine: " . $err['line'];
        @error_log("TLIST_FATAL: " . $err['message'] . " " . $err['file'] . ":" . $err['line']);
        _tlist_render_fatal('PHP Fatal Error (Trick Orders)', $msg, 'Check PHP error_log + share screenshot');
    }

    set_error_handler('_tlist_err_handler');
    set_exception_handler('_tlist_exception_handler');
    register_shutdown_function('_tlist_shutdown_handler');
}

// ---- INCLUDES (handlers ACTIVE now - includes mein error aaye to pakad me aayega)
include('../includes/session.php');
include('../includes/config.php');
include('trick_buy_common.php');
include('../template/ahkweb/header.php');

header('Content-Type: text/html; charset=UTF-8');

$userPhone = (string)(isset($udata['phone']) ? $udata['phone'] : '');
$userId    = isset($udata['id']) ? (int)$udata['id'] : 0;
$submitted = isset($_GET['submitted']) ? (int)$_GET['submitted'] === 1 : false;

if ($userPhone === '' && $userId <= 0) {
    echo '<script>if(typeof Swal!=="undefined"){Swal.fire({icon:"warning",title:"Session Expired",text:"Please login again"}).then(()=>window.location.href="index.php");}else{alert("Session expired");window.location.href="index.php";}</script>';
    exit;
}

$orders = array();
if ($userPhone !== '' || $userId > 0) {
    $sql = "SELECT * FROM trick_orders WHERE ";
    $params = array();
    $types  = '';
    if ($userPhone !== '') { $sql .= "user_phone=? "; $types .= 's'; $params[] = $userPhone; }
    if ($userId > 0) {
        if ($userPhone !== '') $sql .= "OR ";
        $sql .= "user_id=? ";
        $types .= 'i';
        $params[] = $userId;
    }
    $sql .= "ORDER BY id DESC";
    $stmt = mysqli_prepare($ahk_conn, $sql);
    if (!$stmt) {
        _tlist_render_fatal('DB Prepare Failed', "MySQL: " . mysqli_error($ahk_conn) . "\nSQL: $sql");
    }
    if (count($params) > 0) {
        // bind_param with variable params
        array_unshift($params, $types);
        $refs = array();
        foreach ($params as $k => $v) { $refs[$k] = &$params[$k]; }
        call_user_func_array(array($stmt, 'bind_param'), $refs);
    }
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        @mysqli_stmt_close($stmt);
        _tlist_render_fatal('DB Execute Failed', "MySQL Error: $err");
    }
    $orders = db_stmt_fetch_all_assoc($stmt);
    @mysqli_stmt_close($stmt);
}
?>
  <div class="page-wrapper">
    <div class="page-content">
      <div class="row justify-content-center">
        <div class="col-12">
          <div class="card border-0 shadow">
            <div class="card-body p-3 p-md-4">
              <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
                <h3 class="mb-0">My Trick Orders</h3>
                <a href="trick_buy.php" class="btn btn-primary"><i class="bi bi-plus me-1"></i> New Order</a>
              </div>

              <div class="table-responsive">
                <table class="table table-hover align-middle">
                  <thead class="table-light">
                    <tr>
                      <th>#</th>
                      <th>App No.</th>
                      <th>Service</th>
                      <th>WhatsApp</th>
                      <th>AnyDesk</th>
                      <th>Note</th>
                      <th>Amount</th>
                      <th>Status</th>
                      <th>Date</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($orders)): ?>
                      <tr><td colspan="10" class="text-center py-5 text-muted"><i class="bi bi-inbox"></i> No orders yet. Place your first order!</td></tr>
                    <?php
                    endif;
                    $idx = 0;
                    foreach ($orders as $row):
                        $idx++;
                        $rowStatus = isset($row['status']) ? (string)$row['status'] : '';
                        // PHP 7.4 compatible: switch() instead of match()
                        switch ($rowStatus) {
                            case 'pending':    $statusBadge = '<span class="badge bg-warning text-dark">PENDING</span>'; break;
                            case 'processing': $statusBadge = '<span class="badge bg-info text-white">PROCESSING</span>'; break;
                            case 'completed':  $statusBadge = '<span class="badge bg-success">COMPLETED</span>'; break;
                            case 'rejected':   $statusBadge = '<span class="badge bg-danger">REJECTED</span>'; break;
                            case 'refunded':   $statusBadge = '<span class="badge bg-secondary">REFUNDED</span>'; break;
                            case 'cancelled':  $statusBadge = '<span class="badge bg-dark">CANCELLED</span>'; break;
                            default:           $statusBadge = '<span class="badge bg-light text-dark">'.htmlspecialchars($rowStatus).'</span>';
                        }
                        $download = '';
                        $attachPath = isset($row['attachment_path']) ? (string)$row['attachment_path'] : '';
                        if ($attachPath !== '') {
                            $download = '<a class="btn btn-sm btn-outline-success" href="uploads/trick_orders/' . htmlspecialchars($attachPath) . '" target="_blank"><i class="bi bi-download"></i> Download</a>';
                        }
                        $appNo = isset($row['application_no']) ? htmlspecialchars((string)$row['application_no']) : '';
                        $svc   = isset($row['service_label'])  ? htmlspecialchars((string)$row['service_label'])  : '';
                        $wa    = isset($row['whatsapp_number'])? htmlspecialchars((string)$row['whatsapp_number']): '';
                        $ad    = isset($row['anydesk_id'])     ? htmlspecialchars((string)$row['anydesk_id'])     : '';
                        $note  = isset($row['customer_note'])  ? htmlspecialchars(mb_substr((string)$row['customer_note'], 0, 60)) : '';
                        $rejReason = isset($row['rejection_reason']) ? (string)$row['rejection_reason'] : '';
                        $adminNote = isset($row['admin_note']) ? (string)$row['admin_note'] : '';
                        $amt   = isset($row['amount']) ? (float)$row['amount'] : 0;
                        $dt    = isset($row['created_at']) ? htmlspecialchars((string)$row['created_at']) : '';
                    ?>
                      <tr>
                        <td><?= (int)$idx ?></td>
                        <td class="font-monospace small"><?= $appNo ?></td>
                        <td><?= $svc ?></td>
                        <td><?= $wa ?></td>
                        <td><?= $ad ?></td>
                        <td class="small text-muted"><?= $note ?>
                            <?php if ($rejReason !== ''): ?><br><span class="text-danger">Reason: <?= htmlspecialchars($rejReason) ?></span><?php endif; ?>
                            <?php if ($adminNote !== ''): ?><br><span class="text-primary">Admin: <?= htmlspecialchars(mb_substr($adminNote, 0, 60)) ?></span><?php endif; ?>
                        </td>
                        <td>₹<?= number_format($amt, 2) ?></td>
                        <td><?= $statusBadge ?></td>
                        <td class="small text-muted"><?= $dt ?></td>
                        <td><?= $download ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

<?php include('footers.php');
if ($submitted && function_exists('trickAlert')) {
    trickAlert('Order Submitted', 'Amount deducted from wallet. Admin will review and deliver soon.', 'success');
} elseif ($submitted) {
    echo '<script>if(typeof Swal!=="undefined"){Swal.fire({icon:"success",title:"Order Submitted",text:"Amount deducted from wallet. Admin will review soon."});}</script>';
}
// Dump warnings to console
if (!empty($GLOBALS['_tlist_debug']['errors'])) {
    echo '<script>try{console.log(' . json_encode($GLOBALS['_tlist_debug']['errors'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ');}catch(e){}</script>';
}
?>
