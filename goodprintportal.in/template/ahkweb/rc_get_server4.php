<?php
include('header.php');

 $pdf_preview = '';
 $show_preview = false;
 $api_error = '';
 $rc_number_display = '';
 $final_file_name = '';
 $last_rcnc_id = 0;

// ============================================================
// 🔗 [SETTINGS API SYNC] Pre-read pariprint_api_key FROM settings DB
//    Admin → Settings.php → PariPrint API Key field → yehi Server 4 me use hoga
//    Agar admin settings me change kare → yaha auto reflect hoga (hard refresh)
// ============================================================
$s4_db_api_key = '';
$s4_db_key_source = 'NOT READ YET';
$s4_key_len = 0;
$s4_key_prefix = '----';
$s4_key_last4 = '----';
$s4_settings_link_status_class = 'warn';
$s4_settings_link_status_text = 'NOT READ';
if (is_object($GLOBALS['ahk_conn'] ?? $ahk_conn)) {
    $_s4cx = $GLOBALS['ahk_conn'] ?? $ahk_conn;
    $_s4r = @mysqli_fetch_assoc(@mysqli_query($_s4cx, "SELECT pariprint_api_key FROM settings WHERE id=1 LIMIT 1"));
    if (is_array($_s4r) && isset($_s4r['pariprint_api_key']) && is_string($_s4r['pariprint_api_key'])) {
        $s4_db_api_key = trim((string)$_s4r['pariprint_api_key']);
        $s4_db_key_source = 'EXACT DB: settings.pariprint_api_key';
        $s4_settings_link_status_class = 'ok';
        $s4_settings_link_status_text = 'CONNECTED (Live from Settings)';
    } else {
        $s4_db_key_source = 'FALLBACK: column missing / empty';
    }
}
$s4_key_len = strlen($s4_db_api_key);
if ($s4_key_len >= 4) {
    $s4_key_prefix = substr($s4_db_api_key, 0, 4);
    $s4_key_last4  = substr($s4_db_api_key, -4);
}
if ($s4_key_len >= 40 && $s4_key_prefix === 'phk_') {
    $s4_settings_link_status_class = 'ok';
    $s4_settings_link_status_text = 'VALID (phk_ prefix OK)';
} else if ($s4_key_len > 0) {
    $s4_settings_link_status_class = 'bad';
    $s4_settings_link_status_text = 'INVALID in Settings (expect phk_… 44 chars)';
}
// Also hydrate globals (belt + suspenders) so form-submit flow has it ready instantly
if ($s4_key_len >= 40) {
    $GLOBALS['pariprint_api_key'] = $s4_db_api_key;
    $pariprint_api_key = $s4_db_api_key;
}

if (isset($_POST['find'])) {
    $rc_no = mysqli_real_escape_string($ahk_conn, strtoupper(trim($_POST['rc_dl'])));
    $username = $udata['phone'];

    // MARK - Duplicate RC check (Server 4): same user + same RC already paid & saved
    $dupCheck = mysqli_fetch_assoc(mysqli_query($ahk_conn,
        "SELECT id FROM rc_vehical_server4 WHERE username = '$username' AND rc_vehical_no = '$rc_no' AND status = 'Success' LIMIT 1"
    ));
    if ($dupCheck) {
        $listUrl = 'rc_get_server4_list?rc=' . rawurlencode($rc_no) . '&dup=1&_=' . time();
        while (@ob_get_level()) { @ob_end_clean(); }
        @header('HTTP/1.1 307 Temporary Redirect', true, 307);
        @header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true);
        @header('Pragma: no-cache', true);
        @header('Location: ' . $listUrl, true, 307);
        ?>
<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="refresh" content="0; url=<?php echo $listUrl; ?>"></head>
<body><script>try{ window.location.replace(<?php echo json_encode($listUrl); ?>); }catch(e){}</script></body></html>
<?php
        exit;
    }

    $priceQuery = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT price FROM pricing WHERE service_name='rc_server4_fee'"));
    $fee = $priceQuery['price'] ?? 0;

    $username = $udata['phone'];
    $wallet_amount = $udata['balance'];
    $debit_fee = $wallet_amount - $fee;

    if ($wallet_amount >= $fee) {

                // ============================================================
        // 🔥🔥🔥 NUCLEAR FIX: Read EXACT settings.pariprint_api_key column (Admin "PariPrint API Key" field)
        // ============================================================
        $pp_send_api_key = ''; $pp_key_source = 'NOT FOUND';
        if (is_object($GLOBALS['ahk_conn'] ?? $ahk_conn)) {
            $_cx = $GLOBALS['ahk_conn'] ?? $ahk_conn;
            // (1) EXACT COLUMN READ (Admin saves to this column)
            $_r = @mysqli_fetch_assoc(@mysqli_query($_cx, "SELECT pariprint_api_key FROM settings WHERE id=1 LIMIT 1"));
            if (is_array($_r) && !empty($_r['pariprint_api_key']) && is_string($_r['pariprint_api_key'])) {
                $pp_send_api_key = trim((string)$_r['pariprint_api_key']);
                $pp_key_source = 'EXACT settings.pariprint_api_key';
            }
            // (2) FALLBACK: scan * for phk_ prefix
            if (strlen($pp_send_api_key) < 40 || substr($pp_send_api_key,0,4) !== 'phk_') {
                $_r2 = @mysqli_fetch_assoc(@mysqli_query($_cx, "SELECT * FROM settings WHERE id=1 LIMIT 1"));
                if (is_array($_r2)) {
                    foreach ($_r2 as $_cc => $_vv) {
                        if (!is_string($_vv) || strlen($_vv) < 40) continue;
                        if (substr($_vv,0,4)==='phk_' || (strpos(strtolower($_cc),'pari')!==false && strpos(strtolower($_cc),'key')!==false)) {
                            $pp_send_api_key = trim($_vv); $pp_key_source = 'fallback col: '.$_cc; break;
                        }
                    }
                }
            }
            // (3) LAST FALLBACK: existing global
            if (strlen($pp_send_api_key) < 40) {
                $_ex = (string)($GLOBALS['pariprint_api_key'] ?? $pariprint_api_key ?? '');
                if (strlen($_ex) >= 40) { $pp_send_api_key = $_ex; $pp_key_source = 'existing global'; }
            }
        }
        // Sync to globals (popup reads these)
        $GLOBALS['pariprint_api_key'] = $pp_send_api_key;
        $pariprint_api_key = $pp_send_api_key;
        // ============================================================
        // ✅ PRE-CHECK STOP IF KEY INVALID (NO Pariprint hit, NO wallet debit)
        // ============================================================
        $pp_ok = (strlen($pp_send_api_key) >= 40 && substr($pp_send_api_key,0,4) === 'phk_');
        if (!$pp_ok) {
            $eh  = '<div style="text-align:left;font-size:13px;line-height:1.6;">';
            $eh .= '<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:12px 14px;">';
            $eh .= '<div style="font-weight:800;color:#991b1b;margin-bottom:6px;">&#128308; PARIPRINT KEY INVALID / EMPTY — STOPPED BEFORE HITTING PARIPRINT.</div>';
            $eh .= '<div style="margin-top:4px;"><b>Fix:</b> Open → <a href="settings.php" target="_blank" style="color:#1d4ed8;text-decoration:underline;font-weight:700;">Admin → Settings → PariPrint API Key</a> field me <code>phk_XXXXXXXXXXXX... (44 chars)</code> paste karke SAVE karo.</div>';
            $eh .= '</div><div style="margin-top:10px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px;display:grid;grid-template-columns:max-content 1fr;row-gap:3px;column-gap:14px;">';
            $eh .= '<div style="color:#6b7280;">Read Source:</div><div style="font-family:ui-monospace,Menlo,Consolas,monospace;">'.htmlspecialchars($pp_key_source).'</div>';
            $eh .= '<div style="color:#6b7280;">Actual Value:</div><div style="font-family:ui-monospace,Menlo,Consolas,monospace;color:#'.(strlen($pp_send_api_key)>=4?'059669':'dc2626').';">'.strlen($pp_send_api_key).' chars / prefix='.(strlen($pp_send_api_key)>=4?htmlspecialchars(substr($pp_send_api_key,0,4)):'----').'.. / last4=..'.(strlen($pp_send_api_key)>=4?htmlspecialchars(substr($pp_send_api_key,-4)):'----').'</div>';
            $eh .= '<div style="color:#6b7280;">Expected:</div><div style="font-family:ui-monospace,Menlo,Consolas,monospace;">phk_… (len 44)</div>';
            $eh .= '</div></div>';
            ?>
            <script>$(function(){ Swal.fire({title:'PariPrint Key Missing / Invalid', html: <?php echo json_encode($eh); ?>, icon:'error', confirmButtonColor:'#dc2626', width: 680}); });</script>
            <?php
        } else {

        // Build URL using VERIFIED UNIQUE var.
        $url = $pariprint_instant_rc_endpoint . "?" . http_build_query([
            'slug'   => 'instant-rc-pdf-server-4',
            'rcno'   => $rc_no,
            'api_key' => $pp_send_api_key
        ]);

        // IMMEDIATELY WRITE EXACT URL (ACTUAL KEY UNMASKED) + KEY TO LOG BEFORE CURL EVEN RUNS
        $_logDir = __DIR__ . '/../../logs';
        if (!is_dir($_logDir)) { @mkdir($_logDir, 0755, true); }
        $GLOBALS['_debug_log_path'] = $_logDir . '/rc_server4_debug_' . $rc_no . '_' . date('Ymd_His') . '.txt';
        $debug_log = $GLOBALS['_debug_log_path'];
        @file_put_contents($debug_log,
            "RC_NO: {$rc_no}
SLUG: instant-rc-pdf-server-4
KEY_SOURCE: {$pp_key_source}
KEY_LEN: ".strlen($pp_send_api_key)."
KEY_VALUE: {$pp_send_api_key}
EXACT_URL_SENT: {$url}
CURL_INIT_AT: ".date('Y-m-d H:i:s')."

"
        );
        $GLOBALS['_pp_url_sent'] = $url;
        $GLOBALS['_pp_key_source'] = $pp_key_source;

        $curl = curl_init();
        // Also sync global debug_log so existing deduped log writes after cURL keep appending to same file.
        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($curl);
        curl_close($curl);

        $debug_dir = __DIR__ . '/../../logs';
        if (!is_dir($debug_dir)) { @mkdir($debug_dir, 0755, true); }
        $debug_log = $debug_dir . '/rc_server4_debug_' . $rc_no . '_' . date('Ymd_His') . '.txt';
        @file_put_contents($debug_log, "URL: $url\nHTTP: $http_code\nRAW RESPONSE:\n" . print_r($response, true) . "\n");

        $result = json_decode($response, true);
        $json_error = json_last_error();
        @file_put_contents($debug_log, "\nPARSED ARRAY KEYS: " . (is_array($result) ? implode(', ', array_keys($result)) : 'N/A') . "\nPARSED RESULT:\n" . print_r($result, true) . "\n", FILE_APPEND);

        $api_status = $result['status'] ?? false;
        $api_message = $result['message'] ?? 'No response from API';
        $rc_number_display = $result['rcno'] ?? $result['rc_number'] ?? $rc_no;

        $rv = $result['result_value'] ?? $result['data'] ?? $result['result'] ?? $result['response'] ?? null;
        $wrap_pdf_link = '';
        $wrap_pdf_base64 = '';
        $wrap_rcno = '';
        if (is_array($rv)) {
            $wrap_pdf_link = trim($rv['pdf_link'] ?? $rv['download_url'] ?? $rv['download_link'] ?? $rv['url'] ?? $rv['link'] ?? '');
            $wrap_pdf_base64 = trim($rv['pdf'] ?? $rv['pdf_base64'] ?? $rv['base64'] ?? $rv['file'] ?? '');
            $wrap_rcno = $rv['rcno'] ?? $rv['rc_number'] ?? $rv['rc_vehical_no'] ?? '';
        } elseif (is_string($rv) && $rv !== '') {
            $rvTrim = trim($rv);
            if (filter_var($rvTrim, FILTER_VALIDATE_URL) || stripos($rvTrim, 'http') === 0 || preg_match('#^/[\w\-./]+#', $rvTrim)) {
                $wrap_pdf_link = $rvTrim;
            } elseif (strlen($rvTrim) > 200 && preg_match('#^[A-Za-z0-9+/=\s]+$#', $rvTrim)) {
                $wrap_pdf_base64 = $rvTrim;
            }
        }

        $pdf_link = trim($result['pdf_link'] ?? '');
        if ($pdf_link === '') $pdf_link = trim($result['download_url'] ?? '');
        if ($pdf_link === '') $pdf_link = trim($result['download_link'] ?? '');
        if ($pdf_link === '') $pdf_link = trim($result['file_url'] ?? '');
        if ($pdf_link === '') $pdf_link = trim($result['pdf_url'] ?? '');
        if ($pdf_link === '') $pdf_link = trim($result['pdf'] ?? '');
        if ($pdf_link === '' && $wrap_pdf_link !== '') $pdf_link = $wrap_pdf_link;
        $pdf_base64 = trim($result['pdf'] ?? $result['pdf_base64'] ?? '');
        if ($pdf_base64 === '') $pdf_base64 = trim($result['base64'] ?? '');
        if ($pdf_base64 === '' && $wrap_pdf_base64 !== '') $pdf_base64 = $wrap_pdf_base64;
        if ($wrap_rcno !== '' && $rc_number_display === $rc_no) $rc_number_display = $wrap_rcno;

        $is_success = (
            $api_status === true ||
            $api_status === 1 ||
            $api_status === 'true' ||
            $api_status === 'success' ||
            (isset($result['status_code']) && (string)$result['status_code'] === '100')
        );

        @file_put_contents($debug_log, "\nDEBUG CHECKS:\nstatus=$api_status\nis_success=" . ($is_success ? 'yes' : 'no') . "\npdf_link_len=" . strlen($pdf_link) . "\npdf_base64_len=" . strlen($pdf_base64) . "\nwrap_key_found=" . (is_array($rv) ? 'yes' : 'no') . "\n", FILE_APPEND);

        if ($response && $http_code === 200 && $json_error === JSON_ERROR_NONE && $is_success && (!empty($pdf_link) || !empty($pdf_base64))) {

            if (!empty($pdf_link)) {
                $ppBase = defined('CENTRAL_API_BASE') ? CENTRAL_API_BASE : (function_exists('get_central_api_domain') ? get_central_api_domain() : 'https://pariprint.in');
                if (is_string($ppBase) && $ppBase !== '' && strpos($pdf_link, '/') === 0 && strpos($pdf_link, '//') !== 0) {
                    $pdf_link = rtrim($ppBase, '/') . '/' . ltrim($pdf_link, '/');
                }

                $pdf_bytes = '';
                $fetch_error = '';
                if (function_exists('curl_init')) {
                    $ch2 = curl_init();
                    curl_setopt($ch2, CURLOPT_URL, $pdf_link);
                    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch2, CURLOPT_CONNECTTIMEOUT, 15);
                    curl_setopt($ch2, CURLOPT_TIMEOUT, 60);
                    curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, false);
                    curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch2, CURLOPT_MAXREDIRS, 5);
                    curl_setopt($ch2, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) GoodPrintPortal');
                    $pdf_bytes = curl_exec($ch2);
                    $http_pdf = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                    $ct_pdf  = curl_getinfo($ch2, CURLINFO_CONTENT_TYPE);
                    if ($pdf_bytes === false || $http_pdf < 200 || $http_pdf >= 400) {
                        $fetch_error = 'HTTP '.$http_pdf.' / curl error: '.curl_error($ch2);
                    }
                    curl_close($ch2);
                }
                @file_put_contents($debug_log, "\nPDF FETCH: url=".strlen($pdf_link)." len=".(is_string($pdf_bytes)?strlen($pdf_bytes):0)." http=".($http_pdf??'?')." type=".($ct_pdf??'?')." err=$fetch_error\n", FILE_APPEND);

                if (is_string($pdf_bytes) && $pdf_bytes !== '' && stripos($ct_pdf, 'pdf') !== false) {
                    $pdf_preview = 'data:application/pdf;base64,' . base64_encode($pdf_bytes);
                    $file_for_db = mysqli_real_escape_string($ahk_conn, $pdf_preview);
                } elseif (is_string($pdf_bytes) && $pdf_bytes !== '' && strpos($pdf_bytes, '%PDF-') === 0) {
                    $pdf_preview = 'data:application/pdf;base64,' . base64_encode($pdf_bytes);
                    $file_for_db = mysqli_real_escape_string($ahk_conn, $pdf_preview);
                } else {
                    $clean_base64 = '';
                    if (is_string($pdf_bytes) && $pdf_bytes !== '' && preg_match('#^[A-Za-z0-9+/=\s]+$#', $pdf_bytes) && strlen($pdf_bytes) > 200) {
                        $clean_base64 = $pdf_bytes;
                    }
                    if ($clean_base64 !== '') {
                        $pdf_preview = 'data:application/pdf;base64,' . preg_replace('#^data:application/pdf;base64,#i', '', $clean_base64);
                        $file_for_db = mysqli_real_escape_string($ahk_conn, $pdf_preview);
                    } else {
                        $pdf_preview = $pdf_link;
                        $file_for_db = mysqli_real_escape_string($ahk_conn, $pdf_link);
                    }
                }
            } else {
                $clean_base64 = preg_replace('#^data:application/pdf;base64,#i', '', $pdf_base64);
                $pdf_preview = 'data:application/pdf;base64,' . $clean_base64;
                $file_for_db = mysqli_real_escape_string($ahk_conn, $pdf_preview);
            }

            if ($final_file_name === '') {
                $rcfn = preg_replace('/[^A-Za-z0-9]/', '', $rc_number_display);
                if ($rcfn === '') $rcfn = 'DOC';
                $final_file_name = 'RC_' . $rcfn . '_Server4.pdf';
            }
            $show_preview = true;

            mysqli_query($ahk_conn, "UPDATE users SET balance = balance - $fee WHERE phone = '$username'");

            date_default_timezone_set("Asia/Kolkata");
            $time_hkb = date('d/m/Y g:i:s');

            $insert = mysqli_query($ahk_conn, "INSERT INTO `rc_vehical_server4` 
                (`username`, `rc_vehical_no`, `status`, `pdf`, `date`) 
                VALUES ('$username', '$rc_no', 'Success', '$file_for_db', '$time_hkb')");

            $last_rcnc_id = $insert ? (int)mysqli_insert_id($ahk_conn) : 0;

            @file_put_contents($debug_log, "\nINSERT done: " . ($insert ? 'OK id='.$last_rcnc_id : 'FAIL: ' . mysqli_error($ahk_conn)) . "\n", FILE_APPEND);

            mysqli_query($ahk_conn, "INSERT INTO `wallethistory` 
                (`userid`, `amount`, `balance`, `purpose`, `status`, `type`) 
                VALUES ('$username', '$fee', '$debit_fee', 'Instant RC Pdf Server 4 - $rc_no', '1', 'Debit')");

            /* 🔴 ABSOLUTE HARD RESET REDIRECT.
               - 307 Temporary (keeps method semantics, avoids aggressive cache)
               - Cache-Control: no-store, no-cache (avoids ANY bfcache / browser cache)
               - Pragma: no-cache, Expires past
               - Also emit tiny fallback HTML in case any proxy strips Location.
               - This avoids ALL client-side auto-submit form / meta refresh issues. */
            while (@ob_get_level()) { @ob_end_clean(); }
            $listUrl = 'rc_get_server4_list?rc=' . rawurlencode($rc_number_display) . '&newid=' . (int)$last_rcnc_id . '&_=' . time();
            @header('HTTP/1.1 307 Temporary Redirect', true, 307);
            @header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0', true);
            @header('Pragma: no-cache', true);
            @header('Expires: Thu, 01 Jan 1970 00:00:00 GMT', true);
            @header('Vary: Accept-Encoding', true);
            @header('Location: ' . $listUrl, true, 307);
            @header('X-RCNC: redirect-list', true);
            ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Redirecting…</title>
<meta http-equiv="cache-control" content="no-store, no-cache, must-revalidate, post-check=0, pre-check=0">
<meta http-equiv="pragma" content="no-cache">
<meta http-equiv="expires" content="Thu, 01 Jan 1970 00:00:00 GMT">
<meta http-equiv="refresh" content="0; url=<?php echo $listUrl; ?>">
</head>
<body style="font-family:Arial,sans-serif;text-align:center;padding:40px;color:#065f46;background:#ecfdf5;">
  <h2 style="margin:0 0 12px;font-size:18px;">RC PDF Generated. Redirecting to list…</h2>
  <p style="margin:0 0 18px;">RC: <b style="background:#fff;padding:4px 10px;border-radius:6px;"><?php echo htmlspecialchars($rc_number_display); ?></b> &nbsp; Amount debited: <b>₹<?php echo (float)$fee; ?></b></p>
  <a href="<?php echo $listUrl; ?>" style="display:inline-block;padding:10px 18px;background:#059669;color:#fff;text-decoration:none;border-radius:8px;font-weight:700;">If not redirected click here →</a>
  <script> try{ window.location.replace(<?php echo json_encode($listUrl); ?>); }catch(e){} </script>
</body>
</html>
<?php
            exit;

        } else {
            if ($response === false || !empty($curl_error) || $http_code !== 200) {
                $api_error = 'Failed to connect to API server.';
            } elseif ($json_error !== JSON_ERROR_NONE) {
                $api_error = 'Invalid JSON response from API.';
            } else {
                $api_error = $api_message ?: 'RC record not found. Please verify the vehicle number.';
                if ($is_success && empty($pdf_link) && empty($pdf_base64)) {
                    $keys_found = is_array($result) ? implode(', ', array_keys($result)) : '';
                    $api_error = $api_error . ' (PDF missing. Keys: ' . $keys_found . ')';
                }
            }
            @file_put_contents($debug_log, "\nERROR PATH: $api_error\n", FILE_APPEND);
            ?>
            <script>
                $(function () {
                    Swal.fire({
                        title: 'Request Failed',
                        text: '<?php echo addslashes(htmlspecialchars($api_error)); ?>',
                        icon: 'error',
                        confirmButtonColor: '#e74c3c'
                    });
                });
            </script>
            <?php
        }
    }
    } else {
        ?>
        <script>
            $(function () {
                Swal.fire({
                    title: 'Insufficient Balance',
                    html: 'Required: <b>₹<?php echo $fee; ?></b><br>Your Balance: <b>₹<?php echo $wallet_amount; ?></b><br><small class="text-warning">Please recharge to continue.</small>',
                    icon: 'warning',
                    confirmButtonColor: '#f39c12',
                    confirmButtonText: 'Go to Wallet'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'wallet.php';
                    }
                });
            });
        </script>
        <?php
    }
}

if ($show_preview && (int)$last_rcnc_id <= 0 && !empty($rc_number_display) && !empty($udata['phone'])) {
    $safePhone = mysqli_real_escape_string($ahk_conn, (string)$udata['phone']);
    $safeRc = mysqli_real_escape_string($ahk_conn, (string)$rc_number_display);
    $safeRcAlt = mysqli_real_escape_string($ahk_conn, isset($rc_no) ? (string)$rc_no : (string)$rc_number_display);
    $lq = "SELECT id FROM rc_vehical_server4 WHERE username='{$safePhone}' AND (rc_vehical_no='{$safeRc}' OR rc_vehical_no='{$safeRcAlt}') ORDER BY id DESC LIMIT 1";
    $lres = mysqli_query($ahk_conn, $lq);
    if ($lres && ($lrow = mysqli_fetch_assoc($lres))) {
        $last_rcnc_id = (int)$lrow['id'];
    }
}
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap');

    :root {
        --bg-body: #f0f2f5;
        --bg-white: #ffffff;
        --bg-light: #f8f9fb;
        --bg-input: #f5f6f8;
        --border-light: #e8eaed;
        --border-focus: #10b981;
        --accent: #10b981;
        --accent-hover: #059669;
        --accent-light: #ecfdf5;
        --accent-border: #a7f3d0;
        --accent-glow: rgba(16, 185, 129, 0.15);
        --danger: #ef4444;
        --warning: #f59e0b;
        --text-dark: #111827;
        --text-body: #374151;
        --text-secondary: #6b7280;
        --text-muted: #9ca3af;
        --shadow-xs: 0 1px 2px rgba(0,0,0,0.04);
        --shadow-sm: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
        --shadow-md: 0 4px 12px rgba(0,0,0,0.07), 0 2px 4px rgba(0,0,0,0.04);
        --shadow-lg: 0 10px 30px rgba(0,0,0,0.08), 0 4px 8px rgba(0,0,0,0.04);
        --shadow-xl: 0 20px 50px rgba(0,0,0,0.1), 0 8px 16px rgba(0,0,0,0.04);
        --radius-sm: 8px;
        --radius-md: 12px;
        --radius-lg: 16px;
        --radius-xl: 20px;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        font-family: 'Inter', sans-serif;
        background: var(--bg-body);
        color: var(--text-body);
        min-height: 100vh;
        -webkit-font-smoothing: antialiased;
    }

    body::before {
        content: '';
        position: fixed;
        inset: 0;
        z-index: 0;
        background-image: radial-gradient(circle at 1px 1px, rgba(0,0,0,0.03) 1px, transparent 0);
        background-size: 32px 32px;
        pointer-events: none;
    }

    .page-wrapper {
        position: relative;
        z-index: 1;
        padding: 28px;
        width: calc(100% - 250px);
        max-width: none;
        margin-top: 60px;
        margin-right: 0;
        margin-bottom: 30px;
        margin-left: 250px;
    }

    .top-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 18px 24px;
        background: var(--bg-white);
        border: 1px solid var(--border-light);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-sm);
        margin-bottom: 24px;
    }

    .top-bar-left {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .top-bar-icon {
        width: 46px;
        height: 46px;
        border-radius: var(--radius-md);
        background: linear-gradient(135deg, var(--accent), #34d399);
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);
    }

    .top-bar-icon svg {
        width: 22px;
        height: 22px;
        color: #fff;
    }

    .top-bar-title {
        font-size: 17px;
        font-weight: 700;
        color: var(--text-dark);
        letter-spacing: -0.3px;
    }

    .top-bar-subtitle {
        font-size: 12px;
        color: var(--text-muted);
        font-family: 'JetBrains Mono', monospace;
        margin-top: 2px;
    }

    .top-bar-right {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .status-tag {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 14px;
        border-radius: 50px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.6px;
    }

    .status-tag.live {
        background: var(--accent-light);
        color: var(--accent-hover);
        border: 1px solid var(--accent-border);
    }

    .status-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: var(--accent);
        animation: pulseDot 2s ease-in-out infinite;
    }

    @keyframes pulseDot {
        0%, 100% { opacity: 1; box-shadow: 0 0 0 0 rgba(16,185,129,0.4); }
        50% { opacity: 0.7; box-shadow: 0 0 0 4px rgba(16,185,129,0); }
    }

    .btn-ghost {
        padding: 9px 18px;
        border-radius: 50px;
        border: none;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #fff;
        font-family: 'Inter', sans-serif;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 7px;
        transition: all 0.25s cubic-bezier(.4,0,.2,1);
        cursor: pointer;
        position: relative;
        overflow: hidden;
        box-shadow: 0 4px 14px rgba(102, 126, 234, 0.35), inset 0 1px 0 rgba(255,255,255,0.2);
        letter-spacing: 0.2px;
    }

    .btn-ghost::before {
        content: '';
        position: absolute;
        top: 0;
        left: -75%;
        width: 50%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.35), transparent);
        transform: skewX(-20deg);
        transition: left 0.5s ease;
    }

    .btn-ghost:hover::before { left: 125%; }

    .btn-ghost:hover {
        transform: translateY(-2px) scale(1.02);
        box-shadow: 0 8px 22px rgba(102, 126, 234, 0.5), inset 0 1px 0 rgba(255,255,255,0.25);
        color: #fff;
    }

    .btn-ghost:active {
        transform: translateY(0) scale(0.98);
        box-shadow: 0 3px 10px rgba(102, 126, 234, 0.3);
    }

    .btn-ghost svg { width: 15px; height: 15px; filter: drop-shadow(0 1px 1px rgba(0,0,0,0.15)); }

    .main-grid {
        display: grid;
        grid-template-columns: 400px 1fr;
        gap: 24px;
        align-items: start;
    }

    @media (max-width: 1024px) {
        .main-grid { grid-template-columns: 1fr; }
    }

    .white-card {
        background: var(--bg-white);
        border: 1px solid var(--border-light);
        border-radius: var(--radius-xl);
        box-shadow: var(--shadow-md);
        overflow: hidden;
        transition: box-shadow 0.3s ease;
    }

    .white-card:hover {
        box-shadow: var(--shadow-lg);
    }

    .card-head {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border-light);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .card-head-title {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-dark);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .card-head-badge {
        font-size: 11px;
        color: var(--text-muted);
        font-family: 'JetBrains Mono', monospace;
        background: var(--bg-light);
        padding: 3px 10px;
        border-radius: 50px;
        border: 1px solid var(--border-light);
    }

    .card-content {
        padding: 24px;
    }

    .form-label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        color: var(--text-secondary);
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.8px;
    }

    .input-wrap {
        position: relative;
        margin-bottom: 22px;
    }

    .input-wrap .input-icon {
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
        transition: color 0.2s;
        z-index: 2;
    }

    .input-wrap .input-icon svg { width: 18px; height: 18px; }

    .rc-input {
        width: 100%;
        padding: 15px 16px 15px 48px;
        background: var(--bg-input);
        border: 2px solid transparent;
        border-radius: var(--radius-md);
        color: var(--text-dark);
        font-family: 'JetBrains Mono', monospace;
        font-size: 18px;
        font-weight: 700;
        letter-spacing: 3px;
        text-transform: uppercase;
        outline: none;
        transition: all 0.25s ease;
    }

    .rc-input::placeholder {
        color: var(--text-muted);
        font-weight: 400;
        letter-spacing: 2px;
        font-size: 14px;
    }

    .rc-input:focus {
        background: var(--bg-white);
        border-color: var(--accent);
        box-shadow: 0 0 0 4px var(--accent-glow);
    }

    .rc-input:focus ~ .input-icon {
        color: var(--accent);
    }

    .price-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 18px;
        background: var(--accent-light);
        border: 1px solid var(--accent-border);
        border-radius: var(--radius-md);
        margin-bottom: 12px;
    }

    .price-row-label {
        font-size: 13px;
        font-weight: 500;
        color: var(--text-secondary);
    }

    .price-row-value {
        font-family: 'JetBrains Mono', monospace;
        font-size: 22px;
        font-weight: 800;
        color: var(--accent-hover);
    }

    .balance-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 18px;
        background: var(--bg-light);
        border: 1px solid var(--border-light);
        border-radius: var(--radius-sm);
        margin-bottom: 24px;
        font-size: 13px;
    }

    .balance-row-label { color: var(--text-muted); }

    .balance-row-value {
        font-family: 'JetBrains Mono', monospace;
        font-weight: 700;
        color: var(--text-dark);
    }

    .btn-submit {
        width: 100%;
        padding: 16px;
        border: none;
        border-radius: var(--radius-md);
        background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        color: #fff;
        font-family: 'Inter', sans-serif;
        font-size: 15px;
        font-weight: 700;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        transition: all 0.25s cubic-bezier(.4,0,.2,1);
        position: relative;
        overflow: hidden;
        box-shadow: 0 6px 20px rgba(250, 112, 154, 0.4), inset 0 1px 0 rgba(255,255,255,0.28);
        letter-spacing: 0.3px;
        text-shadow: 0 1px 2px rgba(0,0,0,0.12);
    }

    .btn-submit::before {
        content: '';
        position: absolute;
        top: 0;
        left: -80%;
        width: 55%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.5), transparent);
        transform: skewX(-25deg);
        transition: left 0.6s cubic-bezier(.4,0,.2,1);
        z-index: 2;
    }

    .btn-submit::after {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, rgba(255,255,255,0.18), transparent 60%);
        opacity: 0.75;
        z-index: 1;
    }

    .btn-submit:hover {
        transform: translateY(-3px) scale(1.01);
        box-shadow: 0 12px 32px rgba(250, 112, 154, 0.55), inset 0 1px 0 rgba(255,255,255,0.32);
    }

    .btn-submit:hover::before { left: 130%; }
    .btn-submit:active { transform: translateY(-1px) scale(0.995); }

    .btn-submit svg { width: 20px; height: 20px; position: relative; z-index: 3; filter: drop-shadow(0 1px 2px rgba(0,0,0,0.15)); }
    .btn-submit span { position: relative; z-index: 3; }

    .alt-servers {
        margin-top: 20px;
        padding-top: 18px;
        border-top: 1px solid var(--border-light);
        text-align: center;
    }

    .alt-servers p {
        font-size: 11px;
        color: var(--text-muted);
        margin-bottom: 10px;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        font-weight: 500;
    }

    .alt-server-btns {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: center;
    }

    .alt-btn {
        padding: 11px 20px;
        border-radius: 50px;
        border: none;
        color: #fff;
        font-family: 'Inter', sans-serif;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
        transition: all 0.28s cubic-bezier(.4,0,.2,1);
        cursor: pointer;
        position: relative;
        overflow: hidden;
        box-shadow: 0 4px 14px rgba(0,0,0,0.18), inset 0 1px 0 rgba(255,255,255,0.22);
        letter-spacing: 0.25px;
        flex: 1 1 calc(50% - 5px);
        max-width: calc(50% - 5px);
        text-align: center;
    }

    .alt-btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -85%;
        width: 55%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.45), transparent);
        transform: skewX(-20deg);
        transition: left 0.55s ease;
    }

    .alt-btn:hover::before { left: 125%; }

    .alt-btn:hover {
        transform: translateY(-2.5px) scale(1.045);
        color: #fff;
    }

    .alt-btn:active {
        transform: translateY(0) scale(0.98);
    }

    .alt-btn.is-current {
        pointer-events: none;
        cursor: default;
        outline: 2.5px solid #fff;
        outline-offset: -2.5px;
    }
    .alt-btn.is-current::after {
        content: '✓ CURRENT';
        position: absolute;
        bottom: 4px;
        right: 8px;
        font-size: 9px;
        font-weight: 900;
        letter-spacing: 0.7px;
        opacity: 0.95;
        padding: 1px 5px;
        border-radius: 6px;
        background: rgba(255,255,255,0.28);
        backdrop-filter: blur(4px);
    }

    .alt-btn.s1 {
        background: linear-gradient(135deg, #2563eb 0%, #3b82f6 50%, #60a5fa 100%);
        box-shadow: 0 6px 18px rgba(59,130,246,0.42), inset 0 1px 0 rgba(255,255,255,0.22);
    }
    .alt-btn.s1:hover {
        box-shadow: 0 10px 26px rgba(59,130,246,0.6), inset 0 1px 0 rgba(255,255,255,0.28);
    }

    .alt-btn.s2 {
        background: linear-gradient(135deg, #10b981 0%, #34d399 50%, #6ee7b7 100%);
        box-shadow: 0 6px 18px rgba(16,185,129,0.42), inset 0 1px 0 rgba(255,255,255,0.22);
    }
    .alt-btn.s2:hover {
        box-shadow: 0 10px 26px rgba(16,185,129,0.6), inset 0 1px 0 rgba(255,255,255,0.28);
    }

    .alt-btn.s3 {
        background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 50%, #fcd34d 100%);
        box-shadow: 0 6px 18px rgba(245,158,11,0.42), inset 0 1px 0 rgba(255,255,255,0.22);
    }
    .alt-btn.s3:hover {
        box-shadow: 0 10px 26px rgba(245,158,11,0.6), inset 0 1px 0 rgba(255,255,255,0.28);
    }

    .alt-btn.s4 {
        background: linear-gradient(135deg, #8b5cf6 0%, #a78bfa 50%, #c4b5fd 100%);
        box-shadow: 0 6px 18px rgba(139,92,246,0.42), inset 0 1px 0 rgba(255,255,255,0.22);
    }
    .alt-btn.s4:hover {
        box-shadow: 0 10px 26px rgba(139,92,246,0.6), inset 0 1px 0 rgba(255,255,255,0.28);
    }

    .alt-btn.s5 {
        background: linear-gradient(135deg, #ec4899 0%, #f472b6 50%, #f9a8d4 100%);
        box-shadow: 0 6px 18px rgba(236,72,153,0.42), inset 0 1px 0 rgba(255,255,255,0.22);
    }
    .alt-btn.s5:hover {
        box-shadow: 0 10px 26px rgba(236,72,153,0.6), inset 0 1px 0 rgba(255,255,255,0.28);
    }

    .card-foot {
        padding: 14px 24px;
        border-top: 1px solid var(--border-light);
        text-align: center;
        font-size: 12px;
        color: var(--text-muted);
    }

    .card-foot a {
        color: var(--accent);
        text-decoration: none;
        font-weight: 600;
    }

    .card-foot a:hover { text-decoration: underline; }

    .preview-area {
        min-height: 600px;
        display: flex;
        flex-direction: column;
    }

    .preview-empty {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 16px;
        padding: 40px;
    }

    .preview-empty-icon {
        width: 96px;
        height: 96px;
        border-radius: var(--radius-lg);
        background: var(--bg-light);
        border: 2px dashed var(--border-light);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-muted);
        animation: gentleBounce 3s ease-in-out infinite;
    }

    .preview-empty-icon svg { width: 40px; height: 40px; }

    @keyframes gentleBounce {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-8px); }
    }

    .preview-empty h3 {
        font-size: 16px;
        font-weight: 600;
        color: var(--text-secondary);
    }

    .preview-empty p {
        font-size: 13px;
        color: var(--text-muted);
        max-width: 260px;
        text-align: center;
        line-height: 1.7;
    }

    .preview-features {
        display: flex;
        gap: 24px;
        margin-top: 8px;
    }

    .preview-feat {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        color: var(--text-muted);
    }

    .preview-feat svg { width: 14px; height: 14px; color: var(--accent); }

    .pdf-wrap {
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .pdf-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 20px;
        background: var(--accent-light);
        border-bottom: 1px solid var(--accent-border);
    }

    .pdf-rc-no {
        font-family: 'JetBrains Mono', monospace;
        font-size: 14px;
        font-weight: 700;
        color: var(--accent-hover);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .pdf-rc-no svg { width: 16px; height: 16px; }

    .pdf-btns {
        display: flex;
        gap: 8px;
    }

    .pdf-btn {
        padding: 8px 16px;
        border-radius: var(--radius-sm);
        border: 1px solid var(--border-light);
        background: var(--bg-white);
        color: var(--text-body);
        font-family: 'Inter', sans-serif;
        font-size: 12px;
        font-weight: 500;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s ease;
        text-decoration: none;
    }

    .pdf-btn:hover {
        border-color: #d1d5db;
        box-shadow: var(--shadow-xs);
    }

    .pdf-btn.primary {
        background: linear-gradient(135deg, var(--accent), #34d399);
        border-color: transparent;
        color: #fff;
        font-weight: 600;
        box-shadow: 0 3px 10px rgba(16, 185, 129, 0.25);
    }

    .pdf-btn.primary:hover {
        box-shadow: 0 5px 16px rgba(16, 185, 129, 0.35);
        transform: translateY(-1px);
    }

    .pdf-btn svg { width: 14px; height: 14px; }

    .pdf-frame-box {
        flex: 1;
        background: #e5e7eb;
        position: relative;
    }

    .pdf-frame-box iframe {
        width: 100%;
        height: 100%;
        min-height: 500px;
        border: none;
    }

    .fade-up {
        opacity: 0;
        transform: translateY(16px);
        animation: fadeUp 0.5s ease forwards;
    }

    .fade-up-delay { animation-delay: 0.12s; }

    @keyframes fadeUp {
        to { opacity: 1; transform: translateY(0); }
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    @media (max-width: 1024px) {
        .page-wrapper {
            width: 100%;
            margin-left: 0;
            margin-top: 60px;
            padding: 14px;
        }
        .top-bar {
            flex-direction: column;
            gap: 14px;
            padding: 16px;
        }
        .top-bar-left { flex-direction: column; text-align: center; }
        .top-bar-right { width: 100%; justify-content: center; }
        .rc-input { font-size: 16px; letter-spacing: 2px; padding: 14px 14px 14px 44px; }
        .preview-area { min-height: 420px; }
        .pdf-bar { flex-direction: column; gap: 10px; padding: 10px 16px; }
        .pdf-btns { width: 100%; }
        .pdf-btn { flex: 1; justify-content: center; }
        .preview-features { flex-direction: column; gap: 8px; align-items: center; }
    }

    @media (min-width: 1025px) {
        .wrapper.toggled .page-wrapper,
        .wrapper.toggled:not(.sidebar-hovered) .page-wrapper {
            width: calc(100% - 70px);
            margin-left: 70px;
        }
    }

    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 3px; }
    ::-webkit-scrollbar-thumb:hover { background: #9ca3af; }

</style>

<div class="page-wrapper">

    <div class="top-bar fade-up">
        <div class="top-bar-left">
            <div class="top-bar-icon">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13" rx="2"/><path d="M16 8h4l3 3v5a2 2 0 01-2 2h-1M6 19a2 2 0 100-4 2 2 0 000 4zM18 19a2 2 0 100-4 2 2 0 000 4z"/></svg>
            </div>
            <div>
                <div class="top-bar-title">Instant RC PDF Server 4</div>
                <div class="top-bar-subtitle">Server 4 Variant &mdash; pariprint.in</div>
            </div>
        </div>
        <div class="top-bar-right">
            <span class="status-tag live">
                <span class="status-dot"></span>
                Live
            </span>
            <a href="rc_get_server4_list" class="btn-ghost">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                History
            </a>
        </div>
    </div>

    <!-- Multi-Server Notice (Green Premium) -->
    <div style="margin:14px 0 0 0;padding:14px 18px;border-radius:14px;background:linear-gradient(135deg,#ecfdf5 0%,#d1fae5 40%,#a7f3d0 100%);border:1.5px solid #6ee7b7;display:flex;gap:14px;align-items:center;box-shadow:0 6px 20px rgba(16,185,129,0.12), inset 0 1px 0 rgba(255,255,255,0.7);">
      <div style="min-width:46px;height:46px;border-radius:12px;background:linear-gradient(135deg,#10b981 0%,#059669 60%,#0f766e 100%);display:flex;align-items:center;justify-content:center;color:#fff;flex-shrink:0;box-shadow:0 8px 22px rgba(16,185,129,0.4), inset 0 1px 0 rgba(255,255,255,0.25);">
        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
      </div>
      <div style="flex:1;">
        <div style="font-weight:800;color:#064e3b;font-size:15px;margin-bottom:3px;letter-spacing:0.2px;">&#9989; 5 Servers Available</div>
        <div style="font-size:13.5px;color:#047857;line-height:1.5;font-weight:500;">Agar ek Server kaam nahi kar raha hai, to kisi dusre Server se try kariye. Sabhi Servers ke alag-alag Backend hain — ek fail ho to doosra jaroor kaam karega.</div>
      </div>
    </div>

    <div class="main-grid">

        <div class="white-card fade-up">
            <div class="card-head">
                <div class="card-head-title">
                    <svg width="16" height="16" fill="none" stroke="var(--accent)" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                    Fetch Instant RC PDF (Server 4)
                </div>
                <span class="card-head-badge">Server 4</span>
            </div>
            <div class="card-content">
                <form method="post" id="rcForm">
                    <label class="form-label">Vehicle Registration Number</label>
                    <div class="input-wrap">
                        <input 
                            type="text" 
                            required 
                            class="rc-input" 
                            name="rc_dl" 
                            id="rc_dl" 
                            placeholder="MH12GE4749" 
                            oninput="removeSpaces(this)"
                            autocomplete="off"
                            spellcheck="false"
                        >
                        <span class="input-icon">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13" rx="2"/><path d="M16 8h4l3 3v5a2 2 0 01-2 2h-1M6 19a2 2 0 100-4 2 2 0 000 4zM18 19a2 2 0 100-4 2 2 0 000 4z"/></svg>
                        </span>
                    </div>

                    <div class="price-row">
                        <span class="price-row-label">Service Charge</span>
                        <span class="price-row-value">
                            <?php  
                                $price = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT price FROM pricing WHERE service_name='rc_server4_fee'")); 
                                echo "₹" . $price['price'];
                            ?>
                        </span>
                    </div>

                    <div class="balance-row">
                        <span class="balance-row-label">Available Balance</span>
                        <span class="balance-row-value">₹<?php echo $udata['balance']; ?></span>
                    </div>

                    <button type="submit" name="find" class="btn-submit" id="submitBtn">
                        <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
                        <span>Get RC PDF (Server 4)</span>
                    </button>
                </form>

                                <div class="alt-servers">
                    <p>&#128260; Try Other Server (Agar ek fail ho to dusra use kare)</p>
                    <div class="alt-server-btns">
                        <a href="rc_get"              class="alt-btn s1">Server 1 (Chip)</a>
                        <a href="rc_get_server2"      class="alt-btn s2">Server 2</a>
                        <a href="rc_get_server3"      class="alt-btn s3">Server 3</a>
                        <a                            class="alt-btn s4 is-current">Server 4 (You)</a>
                        <a href="rc_get_nochip"       class="alt-btn s5">Server 5 (NoChip)</a>
                    </div>
                </div>
            </div>
            <div class="card-foot">
                Powered by <a href="https://goodprintportal.in" target="_blank">goodprintportal.in</a>
            </div>
        </div>

        <div class="white-card fade-up fade-up-delay">
            <div class="card-head">
                <div class="card-head-title">
                    <svg width="16" height="16" fill="none" stroke="var(--accent)" stroke-width="2.5" viewBox="0 0 24 24"><path d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    Document Preview
                </div>
                <?php if ($show_preview): ?>
                    <span class="status-tag live">Ready</span>
                <?php else: ?>
                    <span class="card-head-badge">Waiting</span>
                <?php endif; ?>
            </div>

            <div class="preview-area">
                <?php if ($show_preview && !empty($pdf_preview)): ?>
                    <div class="pdf-wrap">
                        <div class="pdf-bar">
                            <div class="pdf-rc-no">
                                <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <?php echo htmlspecialchars($rc_number_display); ?>
                            </div>
                            <div class="pdf-btns">
                                <form action="rc_server4_pdf_download.php" method="get" target="_blank" rel="noopener noreferrer" style="margin:0;padding:0;display:inline-flex;">
                                    <input type="hidden" name="id" value="<?php echo (int)$last_rcnc_id; ?>">
                                    <button type="submit" class="pdf-btn primary" style="all:unset;display:inline-flex;align-items:center;gap:6px;cursor:pointer;">
                                        <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
                                        Download
                                    </button>
                                </form>
                                <a href="rc_get_server4_list" class="pdf-btn">
                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                    All Records
                                </a>
                            </div>
                        </div>
                        <div class="pdf-frame-box" style="display:flex;align-items:center;justify-content:center;flex-direction:column;padding:60px 30px;gap:28px;background:linear-gradient(135deg, #ecfdf5 0%, #d1fae5 50%, #a7f3d0 100%);">
                            <div style="width:96px;height:96px;border-radius:24px;background:#fff;display:flex;align-items:center;justify-content:center;box-shadow:0 12px 32px rgba(16,185,129,0.18);">
                                <svg fill="none" stroke="#10b981" stroke-width="1.8" viewBox="0 0 24 24" style="width:52px;height:52px;"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6M12 18v-6M9 15l3 3 3-3"/></svg>
                            </div>
                            <div style="text-align:center;">
                                <h2 style="margin:0 0 8px 0;font-size:26px;font-weight:800;color:#065f46;letter-spacing:-0.3px;">RC PDF Server 4 — Ready</h2>
                                <p style="margin:0;font-size:14px;color:#047857;font-weight:500;">Vehicle RC Number: <span style="font-family:'JetBrains Mono',monospace;font-weight:700;font-size:15px;color:#064e3b;background:#fff;padding:4px 10px;border-radius:6px;margin-left:6px;border:1px solid #6ee7b7;"><?php echo htmlspecialchars($rc_number_display); ?></span></p>
                            </div>
                            <form action="rc_server4_pdf_download.php" method="get" target="_blank" rel="noopener noreferrer" style="margin:0;padding:0;display:inline-block;">
                                <input type="hidden" name="id" value="<?php echo (int)$last_rcnc_id; ?>">
                                <button type="submit" style="display:inline-flex;align-items:center;gap:12px;padding:18px 44px;background:linear-gradient(135deg, #10b981 0%, #059669 100%);color:#fff;border-radius:14px;font-size:17px;font-weight:700;box-shadow:0 16px 30px rgba(16,185,129,0.28);border:2px solid #047857;letter-spacing:0.2px;cursor:pointer;transition:transform .12s ease, box-shadow .12s ease;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 22px 38px rgba(16,185,129,0.34)'" onmouseout="this.style.transform='';this.style.boxShadow='0 16px 30px rgba(16,185,129,0.28)'">
                                    <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="width:24px;height:24px;"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
                                    Download RC PDF (Server 4)
                                </button>
                            </form>
                            <div style="display:flex;gap:24px;margin-top:4px;">
                                <div style="display:flex;align-items:center;gap:8px;font-size:13px;color:#065f46;font-weight:500;">
                                    <svg fill="none" stroke="#10b981" stroke-width="2" viewBox="0 0 24 24" style="width:16px;height:16px;"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                    Secure Download
                                </div>
                                <div style="display:flex;align-items:center;gap:8px;font-size:13px;color:#065f46;font-weight:500;">
                                    <svg fill="none" stroke="#10b981" stroke-width="2" viewBox="0 0 24 24" style="width:16px;height:16px;"><path d="M22 11.08V12a10 10 0 11-5.93-9.14M22 4L12 14.01l-3-3"/></svg>
                                    Service Charge ₹30 Applied
                                </div>
                            </div>
                            <a href="rc_get_server4_list" style="margin-top:2px;color:#047857;font-size:14px;font-weight:600;text-decoration:underline;text-underline-offset:3px;">
                                ← View All Previous Records in History
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="preview-empty">
                        <div class="preview-empty-icon">
                            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/><path d="M9 7h6M9 11h6M9 15h4"/></svg>
                        </div>
                        <h3>No Document Loaded</h3>
                        <p>Enter a valid vehicle registration number and submit to generate the RC PDF (Server 4) here instantly.</p>
                        <div class="preview-features">
                            <span class="preview-feat">
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 15V3m0 12l-4-4m4 4l4-4M2 17l.621 2.485A2 2 0 004.561 21h14.878a2 2 0 001.94-1.515L22 17"/></svg>
                                PDF Download
                            </span>
                            <span class="preview-feat">
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                Live Preview
                            </span>
                            <span class="preview-feat">
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                Secure
                            </span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

</div>

<script>
function removeSpaces(input) {
    input.value = input.value.replace(/\s/g, "").toUpperCase();
}

document.getElementById('rcForm').addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.innerHTML = '<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="animation:spin 1s linear infinite"><path d="M12 2v4m0 12v4m-7.07-15.07l2.83 2.83m8.48 8.48l2.83 2.83M2 12h4m12 0h4M4.93 19.07l2.83-2.83m8.48-8.48l2.83-2.83"/></svg><span>Processing...</span>';
    btn.style.opacity = '0.75';
    btn.style.pointerEvents = 'none';
});
</script>

<?php include("footer.php"); ?>
</body>
</html>
