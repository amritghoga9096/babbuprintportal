<?php
// ============================================================
// 🔥 ULTRA-SAFE DEBUG HANDLERS - SABSE PEHLE
// ============================================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('html_errors', 0);
error_reporting(E_ALL);

$GLOBALS['_tbuy_debug'] = ['errors' => [], 'page_title' => 'Trick Buy'];

if (!function_exists('_tbuy_err_handler')) {
    function _tbuy_err_handler($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) return false;
        $map = [E_WARNING=>'E_WARNING',E_NOTICE=>'E_NOTICE',E_STRICT=>'E_STRICT',E_DEPRECATED=>'E_DEPRECATED',E_RECOVERABLE_ERROR=>'E_RECOVERABLE_ERROR',E_USER_ERROR=>'E_USER_ERROR',E_USER_WARNING=>'E_USER_WARNING',E_USER_NOTICE=>'E_USER_NOTICE',E_USER_DEPRECATED=>'E_USER_DEPRECATED'];
        $typeName = isset($map[$severity]) ? $map[$severity] : 'E_#'.$severity;
        $GLOBALS['_tbuy_debug']['errors'][] = "[$typeName] $message in $file line $line";
        @error_log("TBUY_WARN: [$typeName] $message in $file:$line");
        return true;
    }
    function _tbuy_exception_handler($e) {
        $msg = $e->getMessage() . "\nFile: " . $e->getFile() . "\nLine: " . $e->getLine();
        @error_log("TBUY_EXCEPTION: " . $e->getMessage() . " " . $e->getFile() . ":" . $e->getLine());
        while (ob_get_level()) { @ob_end_clean(); }
        if (!headers_sent()) { header('Content-Type: text/html; charset=utf-8'); }
        $title = 'PHP Exception (Trick Buy)';
        $safeTitle = htmlspecialchars($title, ENT_QUOTES);
        $safeBody = htmlspecialchars($msg, ENT_QUOTES);
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Error - Trick Buy</title><script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';
        echo '<style>body{font-family:system-ui,Arial,sans-serif;background:#1a1a1a;color:#fff;margin:0;padding:18px}.ebox{max-width:720px;margin:24px auto;background:#2a2a2a;border:1px solid #444;border-radius:12px;padding:18px 20px}.etitle{font-size:20px;font-weight:700;color:#ff6b6b;margin-bottom:10px}.ebody{background:#111;padding:12px;border-radius:8px;white-space:pre-wrap;word-break:break-word;margin:10px 0;color:#ffd966;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:13px;line-height:1.5;max-height:260px;overflow:auto}.ebtn{display:inline-block;margin-top:10px;padding:9px 16px;background:#4f46e5;color:#fff;border:0;border-radius:8px;cursor:pointer;font-weight:600}details{margin-top:10px}summary{cursor:pointer;color:#60a5fa}</style></head><body>';
        echo '<div class="ebox"><div class="etitle">🚨 '.$safeTitle.'</div><div class="ebody">'.$safeBody.'</div>';
        if (!empty($GLOBALS['_tbuy_debug']['errors'])) {
            echo '<details><summary>Show PHP Warnings ('.count($GLOBALS['_tbuy_debug']['errors']).')</summary><div class="ebody" style="color:#a78bfa">';
            foreach ($GLOBALS['_tbuy_debug']['errors'] as $w) { echo htmlspecialchars($w)."\n"; }
            echo '</div></details>';
        }
        echo '<br><button class="ebtn" onclick="window.history.back();">← Back</button> &nbsp; <button class="ebtn" style="background:#10b981" onclick="window.location.href=\'index.php\'">🏠 Dashboard</button></div>';
        echo '<script>(function(){var opts={icon:"error",showConfirmButton:true,confirmButtonText:"OK",title:'.json_encode($safeTitle).',text:'.json_encode($msg).'};var fired=false;function fb(){if(fired)return;fired=true;alert('.json_encode($title."\n\n".$msg).');if(window.history.length>1)window.history.back();}function fs(){if(fired)return;fired=true;if(typeof Swal!=="undefined"){Swal.fire(opts).then(function(){if(window.history.length>1)window.history.back();else window.location.href="index.php";});}else{fb();}}try{fs();}catch(e){fb();}setTimeout(function(){try{fs();}catch(e){fb();}},1500);})();</script>';
        if (!empty($GLOBALS['_tbuy_debug']['errors'])) {
            echo '<script>try{console.log('.json_encode($GLOBALS['_tbuy_debug']['errors'], JSON_UNESCAPED_UNICODE).');}catch(e){}</script>';
        }
        echo '</body></html>';
        exit;
    }
    function _tbuy_shutdown_handler() {
        $err = error_get_last();
        if (!$err) return;
        $fatals = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR);
        if (!in_array($err['type'], $fatals, true)) return;
        $msg = $err['message'] . "\nFile: " . $err['file'] . "\nLine: " . $err['line'];
        @error_log("TBUY_FATAL: " . $err['message'] . " " . $err['file'] . ":" . $err['line']);
        while (ob_get_level()) { @ob_end_clean(); }
        if (!headers_sent()) { header('Content-Type: text/html; charset=utf-8'); }
        $title = 'PHP Fatal Error (Trick Buy)';
        $safeTitle = htmlspecialchars($title, ENT_QUOTES);
        $safeBody = htmlspecialchars($msg, ENT_QUOTES);
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Fatal - Trick Buy</title><script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';
        echo '<style>body{font-family:system-ui,Arial,sans-serif;background:#1a1a1a;color:#fff;margin:0;padding:18px}.ebox{max-width:720px;margin:24px auto;background:#2a2a2a;border:1px solid #444;border-radius:12px;padding:18px 20px}.etitle{font-size:20px;font-weight:700;color:#ff6b6b;margin-bottom:10px}.ebody{background:#111;padding:12px;border-radius:8px;white-space:pre-wrap;word-break:break-word;margin:10px 0;color:#ffd966;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:13px;line-height:1.5;max-height:260px;overflow:auto}.ebtn{display:inline-block;margin-top:10px;padding:9px 16px;background:#4f46e5;color:#fff;border:0;border-radius:8px;cursor:pointer;font-weight:600}</style></head><body>';
        echo '<div class="ebox"><div class="etitle">💀 '.$safeTitle.'</div><div class="ebody">'.$safeBody.'</div>';
        if (!empty($GLOBALS['_tbuy_debug']['errors'])) {
            echo '<details><summary>Warnings ('.count($GLOBALS['_tbuy_debug']['errors']).')</summary><div class="ebody" style="color:#a78bfa">';
            foreach ($GLOBALS['_tbuy_debug']['errors'] as $w) { echo htmlspecialchars($w)."\n"; }
            echo '</div></details>';
        }
        echo '<br><button class="ebtn" onclick="window.history.back();">← Back</button> &nbsp; <button class="ebtn" style="background:#10b981" onclick="window.location.href=\'index.php\'">🏠 Dashboard</button></div>';
        echo '<script>(function(){var opts={icon:"error",showConfirmButton:true,confirmButtonText:"OK",title:'.json_encode($safeTitle).',text:'.json_encode($msg).'};var f=false;function fb(){if(f)return;f=true;alert('.json_encode($title."\n\n".$msg).');}function fs(){if(f)return;f=true;if(typeof Swal!=="undefined")Swal.fire(opts);else fb();}try{fs();}catch(e){fb();}setTimeout(fs,1500);})();</script>';
        echo '</body></html>';
    }
    set_error_handler('_tbuy_err_handler');
    set_exception_handler('_tbuy_exception_handler');
    register_shutdown_function('_tbuy_shutdown_handler');
}

include('../includes/session.php');
include('../includes/config.php');
include('trick_buy_common.php');
include('../template/ahkweb/header.php');

header('Content-Type: text/html; charset=UTF-8');

$services = trick_get_services();
$serviceKey = $_POST['service_key'] ?? '';
$whatsappNo = trim($_POST['whatsapp_number'] ?? '');
$anydeskId  = trim($_POST['anydesk_id'] ?? '');
$customerNote = trim($_POST['customer_note'] ?? '');
$pageAlert = null;

// --------- Ensure trick service is enabled (admin on/off switch) ---------
$trickEnabled = 1;
if (isset($webdata['trick_service_enabled'])) $trickEnabled = (int)$webdata['trick_service_enabled'];
if ($trickEnabled !== 1) {
    $pageAlert = ['Service Disabled', 'Trick Buy service is temporarily turned off by admin. Please try later.', 'warning'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $trickEnabled === 1 && !$pageAlert) {
    try {
        if (!isset($services[$serviceKey])) throw new Exception('Invalid service selected.');
        $amount = trick_service_fee($serviceKey, $webdata);
        if ($amount <= 0) throw new Exception('Invalid service fee.');
        if (!preg_match('#^[0-9+\-\s]{7,20}$#', $whatsappNo)) throw new Exception('WhatsApp number invalid.');
        if (!preg_match('#^[A-Za-z0-9\-\s]{4,30}$#', $anydeskId))  throw new Exception('AnyDesk ID invalid.');
        if (!isset($udata)) throw new Exception('Login required.');

        // Wallet balance check
        $balance = 0.0;
        if (isset($udata['id'])) {
            $uid = (int)$udata['id'];
            $stmt = mysqli_prepare($ahk_conn, "SELECT balance FROM users WHERE id=? LIMIT 1");
            mysqli_stmt_bind_param($stmt, 'i', $uid);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $balance);
            mysqli_stmt_fetch($stmt);
            mysqli_stmt_close($stmt);
            $balance = (float)$balance;
        }
        if ($balance < $amount) {
            throw new Exception('Insufficient wallet balance. Required: ₹' . number_format($amount, 2) . ' | Your balance: ₹' . number_format($balance, 2) . '. Please top up wallet first.');
        }

        // Deduct wallet
        $deduct = $amount;
        $stmt = mysqli_prepare($ahk_conn, "UPDATE users SET balance = balance - ? WHERE id=? AND balance >= ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'did', $deduct, $uid, $deduct);
        if (!mysqli_stmt_execute($stmt) || mysqli_stmt_affected_rows($stmt) < 1) {
            throw new Exception('Wallet deduct failed. Insufficient balance.');
        }
        mysqli_stmt_close($stmt);

        // Insert order
        $appNo = trickAppNumber();
        $userId  = isset($udata['id']) ? (int)$udata['id'] : null;
        $userPhone = $udata['phone'] ?? '';
        $userName  = $udata['name']  ?? '';
        $sLabel = $services[$serviceKey]['label'];

        $stmt = mysqli_prepare($ahk_conn,
            "INSERT INTO trick_orders
             (application_no, user_id, user_phone, user_name, service_key, service_label, whatsapp_number, anydesk_id, customer_note, amount, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        $status = 'pending';
        mysqli_stmt_bind_param($stmt, 'sisssssssd',
            $appNo, $userId, $userPhone, $userName, $serviceKey, $sLabel, $whatsappNo, $anydeskId, $customerNote, $amount);
        if (!mysqli_stmt_execute($stmt)) throw new Exception('Order failed. Try again.');
        $orderId = mysqli_insert_id($ahk_conn);
        mysqli_stmt_close($stmt);

        // Log wallet transaction (correct table columns: uid, amount, description, txn_ref, date, status)
        $desc = "TRICK BUY | {$sLabel} | App:{$appNo}";
        $stmt = mysqli_prepare($ahk_conn,
            "INSERT INTO transactions (uid, amount, description, txn_ref, date, status)
             VALUES (?, ?, ?, ?, NOW(), 'success')");
        $ref = $appNo;
        $deductSigned = -1 * abs((float)$deduct);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'idss', $uid, $deductSigned, $desc, $ref);
            @mysqli_stmt_execute($stmt);
            @mysqli_stmt_close($stmt);
        }

        header('Location: trick_buy_list.php?submitted=1');
        exit;
    } catch (Throwable $e) {
        $pageAlert = ['Order Failed', $e->getMessage(), 'error'];
    }
}

?>

  <div class="page-wrapper">
    <div class="page-content">
      <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
          <div class="card border-0 shadow">
            <div class="card-body p-4 p-md-5">
              <h3 class="mb-3">TRICK Buy</h3>
              <p class="text-muted mb-4">Select a trick service → Enter details → Pay & Submit. Admin will deliver via AnyDesk / WhatsApp within 1-4 hours.</p>

              <?php if ($trickEnabled !== 1): ?>
                <div class="alert alert-warning"><i class="bi bi-slash-circle me-2"></i> Trick Buy service is currently <b>OFF</b> by admin. Please check later.</div>
              <?php endif; ?>

              <?php if ($pageAlert): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i> <b><?= htmlspecialchars($pageAlert[0]) ?>:</b> <?= htmlspecialchars($pageAlert[1]) ?></div>
              <?php endif; ?>

              <form method="POST" class="trick-form mb-4" novalidate>
                <div class="mb-3">
                  <label class="form-label fw-semibold">Select Service</label>
                  <select name="service_key" id="service_key" class="form-select form-select-lg" required onchange="updatePrice()">
                    <option value="">-- Choose service --</option>
                    <?php foreach ($services as $k => $s):
                        $fee = trick_service_fee($k, $webdata);
                        $selected = $serviceKey === $k ? 'selected' : '';
                    ?>
                      <option value="<?= $k ?>" data-fee="<?= $fee ?>" <?= $selected ?>>
                        <?= htmlspecialchars($s['label']) ?> (₹<?= number_format($fee) ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="mb-3">
                  <label class="form-label fw-semibold">Whatsapp Number</label>
                  <input type="tel" name="whatsapp_number" pattern="[0-9+\-\s]{7,20}" class="form-control form-control-lg"
                         placeholder="e.g. 9999999999" value="<?= htmlspecialchars($whatsappNo) ?>" required>
                  <div class="form-text">Admin contact you on this WhatsApp for updates.</div>
                </div>
                <div class="mb-3">
                  <label class="form-label fw-semibold">Anydesk ID</label>
                  <input type="text" name="anydesk_id" pattern="[A-Za-z0-9\-\s]{4,30}" class="form-control form-control-lg"
                         placeholder="e.g. 123 456 789" value="<?= htmlspecialchars($anydeskId) ?>" required>
                  <div class="form-text">AnyDesk ID of your PC so admin can work remotely.</div>
                </div>
                <div class="mb-3">
                  <label class="form-label fw-semibold">Additional info (optional)</label>
                  <textarea name="customer_note" rows="2" maxlength="500" class="form-control"
                            placeholder="e.g. Applicant name, Reference no, etc."><?= htmlspecialchars($customerNote) ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-100" <?= $trickEnabled !== 1 ? 'disabled' : '' ?>>
                  Pay &amp; Submit (<span id="price_display">₹0</span>)
                </button>
              </form>

              <a class="btn btn-outline-primary w-100" href="trick_buy_list.php">
                <i class="bi bi-list-ul me-1"></i> My Trick Orders
              </a>

            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

<script>
function updatePrice() {
    var sel = document.getElementById('service_key');
    var opt = sel.options[sel.selectedIndex];
    var fee = opt ? opt.getAttribute('data-fee') : 0;
    var pd = document.getElementById('price_display');
    if (pd) pd.textContent = '₹' + Number(fee).toLocaleString('en-IN');
}
document.addEventListener('DOMContentLoaded', updatePrice);
</script>

<?php include('footers.php');
if ($pageAlert) trickAlert($pageAlert[0], $pageAlert[1], $pageAlert[2]);
?>
