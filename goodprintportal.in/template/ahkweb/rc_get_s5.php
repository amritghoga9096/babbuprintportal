<?php
include('header.php');

$pdf_preview = '';
$show_preview = false;
$api_error = '';
$rc_number_display = '';
$final_file_name = '';
$last_rcnc_id = 0;
$selected_type = '1';

@mysqli_report(MYSQLI_REPORT_OFF);
@mysqli_query($ahk_conn, "CREATE TABLE IF NOT EXISTS `rc_vehical_s5` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) DEFAULT NULL,
  `rc_vehical_no` varchar(100) DEFAULT NULL,
  `type` varchar(20) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `pdf` longtext DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `date` varchar(100) DEFAULT NULL,
  `request_id` bigint(20) DEFAULT NULL,
  `order_id` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rc_s5_user` (`username`),
  KEY `idx_rc_s5_rc` (`rc_vehical_no`),
  KEY `idx_rc_s5_reqid` (`request_id`),
  KEY `idx_rc_s5_orderid` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

@mysqli_query($ahk_conn, "ALTER TABLE `rc_vehical_s5` ADD COLUMN IF NOT EXISTS `request_id` bigint(20) DEFAULT NULL AFTER `date`");
@mysqli_query($ahk_conn, "ALTER TABLE `rc_vehical_s5` ADD COLUMN IF NOT EXISTS `order_id` bigint(20) DEFAULT NULL AFTER `request_id`");
@mysqli_query($ahk_conn, "ALTER TABLE `rc_vehical_s5` ADD KEY IF NOT EXISTS `idx_rc_s5_reqid` (`request_id`)");
@mysqli_query($ahk_conn, "ALTER TABLE `rc_vehical_s5` ADD KEY IF NOT EXISTS `idx_rc_s5_orderid` (`order_id`)");

$prExists = @mysqli_fetch_assoc(@mysqli_query($ahk_conn, "SELECT id FROM pricing WHERE service_name='rc_s5_fee' LIMIT 1"));
if (!$prExists) {
    @mysqli_query($ahk_conn, "INSERT INTO pricing (service_name, price, description) VALUES ('rc_s5_fee', '30', 'Instant Rc Pdf S5 Service Charge')");
}

if (isset($_POST['find'])) {
    $rc_no = mysqli_real_escape_string($ahk_conn, strtoupper(trim($_POST['rc_dl'])));
    $type_in = isset($_POST['rc_type']) ? trim((string)$_POST['rc_type']) : '1';
    if (!in_array($type_in, ['1','2','3','4'], true)) $type_in = '1';
    $selected_type = $type_in;
    $username = $udata['phone'];

    $dupCheck = mysqli_fetch_assoc(mysqli_query($ahk_conn,
        "SELECT id FROM rc_vehical_s5 WHERE username = '$username' AND rc_vehical_no = '$rc_no' AND type = '$type_in' AND status = 'Success' LIMIT 1"
    ));
    if ($dupCheck) {
        $listUrl = 'rc_get_s5_list?rc=' . rawurlencode($rc_no) . '&dup=1&_=' . time();
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

    $priceQuery = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT price FROM pricing WHERE service_name='rc_s5_fee'"));
    $_s5Raw = isset($priceQuery['price']) ? trim((string)$priceQuery['price']) : '';
    $fee = (is_numeric($_s5Raw) && (float)$_s5Raw > 0) ? (float)$_s5Raw : 30.0;

    $wallet_amount = $udata['balance'];
    $debit_fee = $wallet_amount - $fee;

    if ($wallet_amount >= $fee) {

        $pp_send_api_key = ''; $pp_key_source = 'NOT FOUND';
        if (is_object($GLOBALS['ahk_conn'] ?? $ahk_conn)) {
            $_cx = $GLOBALS['ahk_conn'] ?? $ahk_conn;
            $_r = @mysqli_fetch_assoc(@mysqli_query($_cx, "SELECT pariprint_api_key FROM settings WHERE id=1 LIMIT 1"));
            if (is_array($_r) && !empty($_r['pariprint_api_key']) && is_string($_r['pariprint_api_key'])) {
                $pp_send_api_key = trim((string)$_r['pariprint_api_key']);
                $pp_key_source = 'EXACT settings.pariprint_api_key';
            }
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
            if (strlen($pp_send_api_key) < 40) {
                $_ex = (string)($GLOBALS['pariprint_api_key'] ?? $pariprint_api_key ?? '');
                if (strlen($_ex) >= 40) { $pp_send_api_key = $_ex; $pp_key_source = 'existing global'; }
            }
        }
        $GLOBALS['pariprint_api_key'] = $pp_send_api_key;
        $pariprint_api_key = $pp_send_api_key;

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

        $apiBase = (function_exists('get_central_api_domain') ? rtrim(get_central_api_domain(), '/') : (defined('CENTRAL_API_BASE') ? rtrim(CENTRAL_API_BASE,'/') : 'https://pariprint.in'));
        $url = $apiBase . "/api-proxy.php?" . http_build_query([
            'slug'     => 'instant-rc-pdf-s5',
            'vehicalNo'=> $rc_no,
            'type'     => $type_in,
            'apiKey'   => $pp_send_api_key
        ]);

        $_logDir = __DIR__ . '/../../logs';
        if (!is_dir($_logDir)) { @mkdir($_logDir, 0755, true); }
        $GLOBALS['_debug_log_path'] = $_logDir . '/rc_s5_debug_' . $rc_no . '_' . date('Ymd_His') . '.txt';
        $debug_log = $GLOBALS['_debug_log_path'];
        @file_put_contents($debug_log,
            "RC_NO: {$rc_no}
TYPE: {$type_in}
SLUG: instant-rc-pdf-s5
KEY_SOURCE: {$pp_key_source}
KEY_LEN: ".strlen($pp_send_api_key)."
EXACT_URL_SENT: {$url}
CURL_INIT_AT: ".date('Y-m-d H:i:s')."

"
        );
        $GLOBALS['_pp_url_sent'] = $url;

        $curl = curl_init();
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
        @file_put_contents($debug_log, "URL: $url\nHTTP: $http_code\nRAW RESPONSE:\n" . print_r($response, true) . "\n");

        $result = json_decode($response, true);
        $json_error = json_last_error();
        @file_put_contents($debug_log, "\nPARSED ARRAY KEYS: " . (is_array($result) ? implode(', ', array_keys($result)) : 'N/A') . "\nPARSED RESULT:\n" . print_r($result, true) . "\n", FILE_APPEND);

        $api_status = $result['status'] ?? false;
        $api_message = $result['message'] ?? $result['statusMessage'] ?? $result['msg'] ?? 'No response from API';
        $rc_number_display = $result['rc_no'] ?? $result['rcno'] ?? $result['rc_number'] ?? $result['VehicalNumberRcV1'] ?? $rc_no;

        $rv = $result['result_value'] ?? $result['data'] ?? $result['result'] ?? $result['response'] ?? null;
        $wrap_pdf_link = '';
        $wrap_pdf_base64 = '';
        $wrap_rcno = '';
        if (is_array($rv)) {
            $wrap_pdf_link = trim($rv['pdf_link'] ?? $rv['download_url'] ?? $rv['download_link'] ?? $rv['url'] ?? $rv['link'] ?? $rv['rcpdf'] ?? '');
            $wrap_pdf_base64 = trim($rv['pdf'] ?? $rv['pdf_base64'] ?? $rv['base64'] ?? $rv['file'] ?? $rv['rcpdf'] ?? '');
            $wrap_rcno = $rv['rcno'] ?? $rv['rc_number'] ?? $rv['rc_vehical_no'] ?? $rv['rc_no'] ?? '';
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

        $_rcpdf_raw = trim($result['rcpdf'] ?? '');
        if ($_rcpdf_raw !== '') {
            $_rcpdf_lower = strtolower($_rcpdf_raw);
            if (strpos($_rcpdf_lower, 'data:application/pdf') === 0 || strpos($_rcpdf_lower, 'data:application/octet-stream') === 0) {
                $pdf_base64 = $_rcpdf_raw;
            } elseif (strlen($_rcpdf_raw) > 500 && preg_match('#^[A-Za-z0-9+/=\s]+$#', $_rcpdf_raw)) {
                $pdf_base64 = $_rcpdf_raw;
            } elseif (filter_var($_rcpdf_raw, FILTER_VALIDATE_URL)) {
                if ($pdf_link === '') $pdf_link = $_rcpdf_raw;
            }
        }

        if ($pdf_link !== '' && strncasecmp($pdf_link, 'data:', 5) === 0) {
            if ($pdf_base64 === '') $pdf_base64 = $pdf_link;
            $pdf_link = '';
        }

        if ($wrap_rcno !== '' && $rc_number_display === $rc_no) $rc_number_display = $wrap_rcno;

        $_status_code_str = '';
        if (isset($result['status_code'])) { $_status_code_str = trim((string)$result['status_code']); }
        elseif (isset($result['statusCode'])) { $_status_code_str = trim((string)$result['statusCode']); }
        $_status_field_str = '';
        if (isset($result['status']) && is_scalar($result['status'])) $_status_field_str = strtolower(trim((string)$result['status']));
        $_success_field = false;
        if (isset($result['success']) && is_scalar($result['success'])) {
            $sv = strtolower(trim((string)$result['success']));
            if ($sv === 'true' || $sv === '1' || $sv === 'success' || $sv === '100' || $sv === '200') $_success_field = true;
            if ($result['success'] === true || $result['success'] === 1 || $result['success'] === '1') $_success_field = true;
        }

        $_s5_verify_pdf_bytes = static function (string $raw): bool {
            $raw = trim($raw);
            if ($raw === '') return false;
            if (preg_match('#^data:[a-z0-9.+-]+/[a-z0-9.+-]+;base64,(.*)$#is', $raw, $m)) {
                $b64 = preg_replace('/\s+/', '', (string)$m[1]);
            } else {
                $b64 = preg_replace('/\s+/', '', $raw);
                if ($b64 === '' || !preg_match('#^[A-Za-z0-9+/=]+$#', $b64)) return false;
            }
            if ($b64 === '') return false;
            $mod = strlen($b64) % 4;
            if ($mod) { $b64 .= str_repeat('=', 4 - $mod); }
            $bin = @base64_decode($b64, true);
            if (!is_string($bin) || $bin === '') return false;
            if (strncmp($bin, '%PDF-', 5) === 0) return true;
            if (strlen($bin) >= 5000) return true;
            return false;
        };

        $_pdf_content_ok = false;
        if ($pdf_base64 !== '') $_pdf_content_ok = $_s5_verify_pdf_bytes($pdf_base64);
        if (!$_pdf_content_ok && $_rcpdf_raw !== '') $_pdf_content_ok = $_s5_verify_pdf_bytes($_rcpdf_raw);
        if (!$_pdf_content_ok && $pdf_link !== '' && filter_var($pdf_link, FILTER_VALIDATE_URL)) {
            $_pdf_content_ok = (stripos($pdf_link, '.pdf') !== false || stripos($pdf_link, '/api-service-download') !== false);
        }
        if (!$_pdf_content_ok && !empty($result['order_id'])) { $_pdf_content_ok = true; }
        if (!$_pdf_content_ok && !empty($result['request_id'])) { $_pdf_content_ok = true; }
        if (!$_pdf_content_ok && !empty($result['id'])) {
            $idv = (string)$result['id'];
            if (ctype_digit($idv) && (int)$idv > 0) { $_pdf_content_ok = true; }
        }

        $HARD_FAIL_STATUS_200 = ($_status_code_str === '200');
        $isStatusOk = false;
        if ($_status_code_str === '100') $isStatusOk = true;
        if ($_status_field_str !== '' && in_array($_status_field_str, ['true','success','completed','ok','1','100'], true)) $isStatusOk = true;
        if ($api_status === true || $api_status === 1 || $api_status === 'true' || $api_status === 'success' || (string)$api_status === '100') $isStatusOk = true;
        if ($_success_field) $isStatusOk = true;

        $is_success = (!$HARD_FAIL_STATUS_200) && $isStatusOk && $_pdf_content_ok;
        if ($is_success && $pdf_link === '' && $pdf_base64 === '') {
            $_rcpdf_check_lower = strtolower($_rcpdf_raw);
            if (strpos($_rcpdf_check_lower, 'data:application/pdf') === 0 || (strlen($_rcpdf_raw) > 800 && preg_match('#^[A-Za-z0-9+/=\s]+$#', $_rcpdf_raw))) {
                $is_success = true;
                if ($pdf_base64 === '') $pdf_base64 = $_rcpdf_raw;
            }
        }

        if ($is_success && $pdf_link === '' && $pdf_base64 === '') {
            $_anyPdf = '';
            $_iter = static function ($node, $depth = 0) use (&$_iter, &$_anyPdf) {
                if ($_anyPdf !== '' || $depth > 6) return;
                if (is_string($node)) {
                    $v = trim($node);
                    if ($v !== '' && strlen($v) > 500) {
                        if (strncasecmp($v, 'data:application/pdf', 20) === 0) { $_anyPdf = $v; return; }
                        if (preg_match('#^[A-Za-z0-9+/=\s]+$#', $v)) { $_anyPdf = $v; return; }
                    }
                    if (filter_var($v, FILTER_VALIDATE_URL) && (stripos($v, '.pdf') !== false || stripos($v, '/api-service-download') !== false || stripos($v, 'download') !== false)) {
                        $_anyPdf = $v; return;
                    }
                } elseif (is_array($node)) {
                    foreach ($node as $child) { $_iter($child, $depth + 1); if ($_anyPdf !== '') return; }
                }
            };
            $_iter($result);
            if ($_anyPdf !== '') {
                if (strncasecmp($_anyPdf, 'data:', 5) === 0 || preg_match('#^[A-Za-z0-9+/=\s]+$#', $_anyPdf)) {
                    $pdf_base64 = $_anyPdf;
                } else {
                    $pdf_link = $_anyPdf;
                }
            }
        }

        if ($is_success && $pdf_link === '' && $pdf_base64 === '') {
            $_reqid = 0;
            if (isset($result['order_id']) && ctype_digit((string)$result['order_id'])) $_reqid = (int)$result['order_id'];
            if ($_reqid <= 0 && isset($result['request_id']) && ctype_digit((string)$result['request_id'])) $_reqid = (int)$result['request_id'];
            if ($_reqid <= 0 && isset($result['id']) && ctype_digit((string)$result['id'])) $_reqid = (int)$result['id'];
            if ($_reqid > 0) {
                $_apiBaseFallback = (function_exists('get_central_api_domain') ? rtrim(get_central_api_domain(), '/') : (defined('CENTRAL_API_BASE') ? rtrim(CENTRAL_API_BASE,'/') : 'https://pariprint.in'));
                $pdf_link = $_apiBaseFallback . '/api-service-download.php?source=reseller&id=' . $_reqid;
            }
        }

        @file_put_contents($debug_log, "\nDEBUG CHECKS:\nstatus=$api_status\nis_success=" . ($is_success ? 'yes' : 'no') . "\npdf_link_len=" . strlen($pdf_link) . "\npdf_base64_len=" . strlen($pdf_base64) . "\nwrap_key_found=" . (is_array($rv) ? 'yes' : 'no') . "\nrcpdf_raw_len=" . strlen($_rcpdf_raw) . "\nresult_keys=" . (is_array($result) ? implode(',', array_keys($result)) : '') . "\n", FILE_APPEND);

        $_s5_extract_pdf_bytes = static function (string $raw, ?string &$err = null): string {
            $raw = trim($raw);
            if ($raw === '') { $err = 'empty'; return ''; }
            $b64 = '';
            if (preg_match('#^data:[a-z0-9.+-]+/[a-z0-9.+-]+;base64,(.*)$#is', $raw, $m)) {
                $b64 = preg_replace('/\s+/', '', (string)$m[1]);
            } else {
                $b64 = preg_replace('/\s+/', '', $raw);
            }
            if ($b64 === '') { $err = 'no-b64'; return ''; }
            if (!preg_match('#^[A-Za-z0-9+/=]+$#', $b64)) { $err = 'bad-charset'; return ''; }
            $mod = strlen($b64) % 4;
            if ($mod) { $b64 .= str_repeat('=', 4 - $mod); }
            $binary = @base64_decode($b64, true);
            if (!is_string($binary) || $binary === '') { $err = 'decode-fail'; return ''; }
            if (strncmp($binary, '%PDF-', 5) === 0 && stripos($binary, '%%EOF') !== false) { return $binary; }
            if (strncmp($binary, '%PDF-', 5) === 0 && (strpos($binary, '/Root') !== false || strpos($binary, 'startxref') !== false)) { return $binary; }
            if (strlen($binary) >= 500) {
                if (stripos($binary, '%%EOF') !== false || strpos($binary, '/Root') !== false || strpos($binary, 'startxref') !== false) {
                    return $binary;
                }
                if (strlen($binary) >= 10000) {
                    return $binary;
                }
            }
            $err = 'no-pdf-signature';
            return '';
        };

        $_s5_clean_for_storage = static function (string $raw) use ($_s5_extract_pdf_bytes): string {
            $bytes = $_s5_extract_pdf_bytes($raw);
            if ($bytes === '') return '';
            return 'data:application/pdf;base64,' . base64_encode($bytes);
        };

        if ($response && $http_code === 200 && $json_error === JSON_ERROR_NONE && $is_success && (!empty($pdf_link) || !empty($pdf_base64))) {

            if (!empty($pdf_link)) {
                $ppBase = $apiBase;
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
                @file_put_contents($debug_log, "\nPDF FETCH: url_len=".strlen($pdf_link)." bytes_len=".(is_string($pdf_bytes)?strlen($pdf_bytes):0)." http=".($http_pdf??'?')." type=".($ct_pdf??'?')." err=$fetch_error\n", FILE_APPEND);

                $gotBinary = false;
                if (is_string($pdf_bytes) && $pdf_bytes !== '') {
                    if (strncmp($pdf_bytes, '%PDF-', 5) === 0) {
                        $pdf_preview = 'data:application/pdf;base64,' . base64_encode($pdf_bytes);
                        $file_for_db = mysqli_real_escape_string($ahk_conn, $pdf_preview);
                        $gotBinary = true;
                    } elseif (stripos(($ct_pdf ?? ''), 'pdf') !== false) {
                        $pdf_preview = 'data:application/pdf;base64,' . base64_encode($pdf_bytes);
                        $file_for_db = mysqli_real_escape_string($ahk_conn, $pdf_preview);
                        $gotBinary = true;
                    }
                }
                if (!$gotBinary) {
                    $cleaned = $_s5_clean_for_storage((string)$pdf_bytes);
                    if ($cleaned !== '') {
                        $file_for_db = mysqli_real_escape_string($ahk_conn, $cleaned);
                        $gotBinary = true;
                    } else {
                        $file_for_db = mysqli_real_escape_string($ahk_conn, $pdf_link);
                    }
                }
            } else {
                $cleanedStorage = $_s5_clean_for_storage((string)$pdf_base64);
                if ($cleanedStorage !== '') {
                    $file_for_db = mysqli_real_escape_string($ahk_conn, $cleanedStorage);
                } else {
                    $maybeBare = preg_replace('/\s+/', '', (string)$pdf_base64);
                    if ($maybeBare !== '' && preg_match('#^[A-Za-z0-9+/=]+$#', $maybeBare)) {
                        $mod = strlen($maybeBare) % 4; if ($mod) $maybeBare .= str_repeat('=', 4 - $mod);
                        $bin = @base64_decode($maybeBare);
                        if (is_string($bin) && $bin !== '' && strncmp($bin, '%PDF-', 5) === 0) {
                            $file_for_db = mysqli_real_escape_string($ahk_conn, 'data:application/pdf;base64,' . base64_encode($bin));
                        } else {
                            $file_for_db = mysqli_real_escape_string($ahk_conn, (string)$pdf_base64);
                        }
                    } else {
                        $file_for_db = mysqli_real_escape_string($ahk_conn, (string)$pdf_base64);
                    }
                }
            }

            @file_put_contents($debug_log, "\nPDF extraction: bytes-from-rcpdf_len=" . strlen((string)$pdf_from_rcpdf) . " bytes-from-download_url_len=" . strlen((string)$pdf_from_url) . " bytes-from-result_len=" . strlen((string)$pdf_from_result) . " clean_base64_len=" . strlen((string)$clean_pdf_base64) . "\n", FILE_APPEND);

            $file_for_db = '';
            $_orderId = 0;
            $_requestId = 0;
            if (isset($result['order_id']) && ctype_digit((string)$result['order_id'])) $_orderId = (int)$result['order_id'];
            if ($_orderId <= 0 && isset($result['orderId']) && ctype_digit((string)$result['orderId'])) $_orderId = (int)$result['orderId'];
            if (isset($result['request_id']) && ctype_digit((string)$result['request_id'])) $_requestId = (int)$result['request_id'];
            if ($_requestId <= 0 && isset($result['requestId']) && ctype_digit((string)$result['requestId'])) $_requestId = (int)$result['requestId'];
            if (isset($result['id']) && ctype_digit((string)$result['id'])) {
                if ($_requestId <= 0) $_requestId = (int)$result['id'];
                if ($_orderId <= 0) $_orderId = (int)$result['id'];
            }
            if (isset($rv) && is_array($rv)) {
                if (($_orderId <= 0 || $_requestId <= 0) && !empty($rv['request_id']) && ctype_digit((string)$rv['request_id'])) $_requestId = (int)$rv['request_id'];
                if (($_orderId <= 0 || $_requestId <= 0) && !empty($rv['order_id']) && ctype_digit((string)$rv['order_id'])) $_orderId = (int)$rv['order_id'];
                if (($_orderId <= 0 || $_requestId <= 0) && !empty($rv['id']) && ctype_digit((string)$rv['id'])) {
                    if ($_requestId <= 0) $_requestId = (int)$rv['id'];
                    if ($_orderId <= 0) $_orderId = (int)$rv['id'];
                }
            }

            if ($final_file_name === '') {
                $rcfn = preg_replace('/[^A-Za-z0-9]/', '', $rc_number_display);
                if ($rcfn === '') $rcfn = 'DOC';
                $final_file_name = 'RC_' . $rcfn . '_S5_Type'.$type_in.'.pdf';
            }
            $show_preview = true;

            mysqli_query($ahk_conn, "UPDATE users SET balance = balance - $fee WHERE phone = '$username'");

            date_default_timezone_set("Asia/Kolkata");
            $time_hkb = date('d/m/Y g:i:s');

            $safe_fn = mysqli_real_escape_string($ahk_conn, $final_file_name);
            $safe_type = mysqli_real_escape_string($ahk_conn, $type_in);
            $safe_req = $_requestId > 0 ? (string)$_requestId : 'NULL';
            $safe_ord = $_orderId > 0 ? (string)$_orderId : 'NULL';
            $insert = mysqli_query($ahk_conn, "INSERT INTO `rc_vehical_s5` 
                (`username`, `rc_vehical_no`, `type`, `status`, `pdf`, `file_name`, `date`, `request_id`, `order_id`) 
                VALUES ('$username', '$rc_no', '$safe_type', 'Success', '$file_for_db', '$safe_fn', '$time_hkb', $safe_req, $safe_ord)");

            $last_rcnc_id = $insert ? (int)mysqli_insert_id($ahk_conn) : 0;

            @file_put_contents($debug_log, "\nINSERT done: " . ($insert ? 'OK id='.$last_rcnc_id : 'FAIL: ' . mysqli_error($ahk_conn)) . "\n", FILE_APPEND);

            $typeLabels = ['1'=>'Chip','2'=>'Without Chip','3'=>'Chip New','4'=>'Without Chip New'];
            $typeLabel = $typeLabels[$type_in] ?? 'Type'.$type_in;
            mysqli_query($ahk_conn, "INSERT INTO `wallethistory` 
                (`userid`, `amount`, `balance`, `purpose`, `status`, `type`) 
                VALUES ('$username', '$fee', '$debit_fee', 'Instant Rc Pdf S5 ($typeLabel) - $rc_no', '1', 'Debit')");

            while (@ob_get_level()) { @ob_end_clean(); }
            $listUrl = 'rc_get_s5_list?rc=' . rawurlencode($rc_number_display) . '&newid=' . (int)$last_rcnc_id . '&_=' . time();
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
  <h2 style="margin:0 0 12px;font-size:18px;">RC PDF S5 Generated. Redirecting to list…</h2>
  <p style="margin:0 0 18px;">RC: <b style="background:#fff;padding:4px 10px;border-radius:6px;"><?php echo htmlspecialchars($rc_number_display); ?></b> &nbsp; Type: <b><?php echo $typeLabel; ?></b> &nbsp; Amount debited: <b>₹<?php echo (float)$fee; ?></b></p>
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
    $lq = "SELECT id FROM rc_vehical_s5 WHERE username='{$safePhone}' AND (rc_vehical_no='{$safeRc}' OR rc_vehical_no='{$safeRcAlt}') ORDER BY id DESC LIMIT 1";
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
        --border-focus: #3b82f6;
        --accent: #3b82f6;
        --accent-hover: #2563eb;
        --accent-light: #eff6ff;
        --accent-border: #bfdbfe;
        --accent-glow: rgba(59, 130, 246, 0.15);
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
        background: linear-gradient(135deg, var(--accent), #60a5fa);
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 14px rgba(59, 130, 246, 0.3);
    }

    .top-bar-icon svg { width: 22px; height: 22px; color: #fff; }

    .top-bar-title { font-size: 17px; font-weight: 700; color: var(--text-dark); letter-spacing: -0.3px; }
    .top-bar-subtitle { font-size: 12px; color: var(--text-muted); font-family: 'JetBrains Mono', monospace; margin-top: 2px; }
    .top-bar-right { display: flex; align-items: center; gap: 10px; }

    .status-tag {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 5px 14px; border-radius: 50px;
        font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.6px;
    }
    .status-tag.live { background: var(--accent-light); color: var(--accent-hover); border: 1px solid var(--accent-border); }
    .status-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--accent); animation: pulseDot 2s ease-in-out infinite; }

    @keyframes pulseDot {
        0%, 100% { opacity: 1; box-shadow: 0 0 0 0 rgba(59,130,246,0.4); }
        50% { opacity: 0.7; box-shadow: 0 0 0 4px rgba(59,130,246,0); }
    }

    .btn-ghost {
        padding: 9px 18px; border-radius: 50px; border: none;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #fff; font-family: 'Inter', sans-serif; font-size: 13px; font-weight: 600;
        text-decoration: none; display: inline-flex; align-items: center; gap: 7px;
        transition: all 0.25s cubic-bezier(.4,0,.2,1); cursor: pointer;
        position: relative; overflow: hidden;
        box-shadow: 0 4px 14px rgba(102, 126, 234, 0.35), inset 0 1px 0 rgba(255,255,255,0.2);
        letter-spacing: 0.2px;
    }
    .btn-ghost::before {
        content: ''; position: absolute; top: 0; left: -75%; width: 50%; height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.35), transparent);
        transform: skewX(-20deg); transition: left 0.5s ease;
    }
    .btn-ghost:hover::before { left: 125%; }
    .btn-ghost:hover { transform: translateY(-2px) scale(1.02); box-shadow: 0 8px 22px rgba(102, 126, 234, 0.5), inset 0 1px 0 rgba(255,255,255,0.25); color: #fff; }
    .btn-ghost:active { transform: translateY(0) scale(0.98); box-shadow: 0 3px 10px rgba(102, 126, 234, 0.3); }
    .btn-ghost svg { width: 15px; height: 15px; filter: drop-shadow(0 1px 1px rgba(0,0,0,0.15)); }

    .main-grid { display: grid; grid-template-columns: 420px 1fr; gap: 24px; align-items: start; }
    @media (max-width: 1024px) { .main-grid { grid-template-columns: 1fr; } }

    .white-card {
        background: var(--bg-white); border: 1px solid var(--border-light);
        border-radius: var(--radius-xl); box-shadow: var(--shadow-md);
        overflow: hidden; transition: box-shadow 0.3s ease;
    }
    .white-card:hover { box-shadow: var(--shadow-lg); }

    .card-head {
        padding: 20px 24px; border-bottom: 1px solid var(--border-light);
        display: flex; align-items: center; justify-content: space-between;
    }
    .card-head-title {
        font-size: 14px; font-weight: 600; color: var(--text-dark);
        display: flex; align-items: center; gap: 10px;
    }
    .card-head-badge {
        font-size: 11px; color: var(--text-muted); font-family: 'JetBrains Mono', monospace;
        background: var(--bg-light); padding: 3px 10px; border-radius: 50px; border: 1px solid var(--border-light);
    }

    .card-content { padding: 24px; }

    .form-label {
        display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary);
        margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.8px;
    }
    .form-label .req { color: #ef4444; margin-left: 2px; }

    .input-wrap { position: relative; margin-bottom: 20px; }
    .input-wrap .input-icon {
        position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
        color: var(--text-muted); transition: color 0.2s; z-index: 2;
    }
    .input-wrap .input-icon svg { width: 18px; height: 18px; }

    .rc-input {
        width: 100%; padding: 15px 16px 15px 48px;
        background: var(--bg-input); border: 2px solid transparent;
        border-radius: var(--radius-md); color: var(--text-dark);
        font-family: 'JetBrains Mono', monospace; font-size: 18px; font-weight: 700;
        letter-spacing: 3px; text-transform: uppercase; outline: none;
        transition: all 0.25s ease;
    }
    .rc-input::placeholder { color: var(--text-muted); font-weight: 400; letter-spacing: 2px; font-size: 14px; }
    .rc-input:focus { background: var(--bg-white); border-color: var(--accent); box-shadow: 0 0 0 4px var(--accent-glow); }
    .rc-input:focus ~ .input-icon { color: var(--accent); }

    .type-select {
        width: 100%; padding: 14px 16px 14px 48px;
        background: var(--bg-input); border: 2px solid transparent;
        border-radius: var(--radius-md); color: var(--text-dark);
        font-family: 'Inter', sans-serif; font-size: 15px; font-weight: 600;
        outline: none; transition: all 0.25s ease;
        -webkit-appearance: none; appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' fill='none' stroke='%236b7280' stroke-width='2.5' viewBox='0 0 24 24'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 16px center;
        cursor: pointer;
    }
    .type-select:hover { background-color: #eef1f5; }
    .type-select:focus { background-color: var(--bg-white); border-color: var(--accent); box-shadow: 0 0 0 4px var(--accent-glow); }
    .type-select option { padding: 10px; font-size: 14px; background: #fff; }

    .price-row {
        display: flex; align-items: center; justify-content: space-between;
        padding: 14px 18px; background: var(--accent-light);
        border: 1px solid var(--accent-border); border-radius: var(--radius-md);
        margin-bottom: 12px;
    }
    .price-row-label { font-size: 13px; font-weight: 500; color: var(--text-secondary); }
    .price-row-value { font-family: 'JetBrains Mono', monospace; font-size: 22px; font-weight: 800; color: var(--accent-hover); }

    .balance-row {
        display: flex; align-items: center; justify-content: space-between;
        padding: 12px 18px; background: var(--bg-light);
        border: 1px solid var(--border-light); border-radius: var(--radius-sm);
        margin-bottom: 22px; font-size: 13px;
    }
    .balance-row-label { color: var(--text-muted); }
    .balance-row-value { font-family: 'JetBrains Mono', monospace; font-weight: 700; color: var(--text-dark); }

    .btn-submit {
        width: 100%; padding: 16px; border: none; border-radius: var(--radius-md);
        background: linear-gradient(135deg, #2563eb 0%, #3b82f6 50%, #60a5fa 100%);
        color: #fff; font-family: 'Inter', sans-serif; font-size: 15px; font-weight: 700;
        cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px;
        transition: all 0.25s cubic-bezier(.4,0,.2,1);
        position: relative; overflow: hidden;
        box-shadow: 0 6px 20px rgba(59, 130, 246, 0.42), inset 0 1px 0 rgba(255,255,255,0.25);
        letter-spacing: 0.3px; text-shadow: 0 1px 2px rgba(0,0,0,0.1);
    }
    .btn-submit::before {
        content: ''; position: absolute; top: 0; left: -80%; width: 55%; height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.5), transparent);
        transform: skewX(-25deg); transition: left 0.6s cubic-bezier(.4,0,.2,1); z-index: 2;
    }
    .btn-submit::after {
        content: ''; position: absolute; inset: 0;
        background: linear-gradient(135deg, rgba(255,255,255,0.15), transparent 60%);
        opacity: 0.7; z-index: 1;
    }
    .btn-submit:hover { transform: translateY(-3px) scale(1.01); box-shadow: 0 12px 32px rgba(59, 130, 246, 0.6), inset 0 1px 0 rgba(255,255,255,0.32); }
    .btn-submit:hover::before { left: 130%; }
    .btn-submit:active { transform: translateY(-1px) scale(0.995); }
    .btn-submit svg { width: 20px; height: 20px; position: relative; z-index: 3; filter: drop-shadow(0 1px 2px rgba(0,0,0,0.15)); }
    .btn-submit span { position: relative; z-index: 3; }

    .alt-servers { margin-top: 20px; padding-top: 18px; border-top: 1px solid var(--border-light); text-align: center; }
    .alt-servers p { font-size: 11px; color: var(--text-muted); margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.8px; font-weight: 500; }
    .alt-server-btns { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; }

    .alt-btn {
        padding: 11px 18px; border-radius: 50px; border: none;
        color: #fff; font-family: 'Inter', sans-serif; font-size: 12.5px; font-weight: 700;
        text-decoration: none; transition: all 0.28s cubic-bezier(.4,0,.2,1);
        cursor: pointer; position: relative; overflow: hidden;
        box-shadow: 0 4px 14px rgba(0,0,0,0.18), inset 0 1px 0 rgba(255,255,255,0.22);
        letter-spacing: 0.25px;
        flex: 1 1 calc(33.33% - 7px);
        max-width: calc(33.33% - 7px);
        text-align: center;
    }
    @media (max-width: 480px) {
        .alt-btn { flex: 1 1 calc(50% - 5px); max-width: calc(50% - 5px); }
    }
    .alt-btn::before {
        content: ''; position: absolute; top: 0; left: -85%; width: 55%; height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.45), transparent);
        transform: skewX(-20deg); transition: left 0.55s ease;
    }
    .alt-btn:hover::before { left: 125%; }
    .alt-btn:hover { transform: translateY(-2.5px) scale(1.045); color: #fff; }
    .alt-btn:active { transform: translateY(0) scale(0.98); }

    .alt-btn.is-current { pointer-events: none; cursor: default; outline: 2.5px solid #fff; outline-offset: -2.5px; }
    .alt-btn.is-current::after {
        content: '✓ YOU';
        position: absolute; bottom: 3px; right: 6px;
        font-size: 8.5px; font-weight: 900; letter-spacing: 0.5px;
        opacity: 0.95; padding: 1px 5px; border-radius: 6px;
        background: rgba(255,255,255,0.28); backdrop-filter: blur(4px);
    }

    .alt-btn.s1 { background: linear-gradient(135deg, #2563eb 0%, #3b82f6 50%, #60a5fa 100%); box-shadow: 0 6px 18px rgba(59,130,246,0.42), inset 0 1px 0 rgba(255,255,255,0.22); }
    .alt-btn.s1:hover { box-shadow: 0 10px 26px rgba(59,130,246,0.6), inset 0 1px 0 rgba(255,255,255,0.28); }

    .alt-btn.s2 { background: linear-gradient(135deg, #10b981 0%, #34d399 50%, #6ee7b7 100%); box-shadow: 0 6px 18px rgba(16,185,129,0.42), inset 0 1px 0 rgba(255,255,255,0.22); }
    .alt-btn.s2:hover { box-shadow: 0 10px 26px rgba(16,185,129,0.6), inset 0 1px 0 rgba(255,255,255,0.28); }

    .alt-btn.s3 { background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 50%, #fcd34d 100%); box-shadow: 0 6px 18px rgba(245,158,11,0.42), inset 0 1px 0 rgba(255,255,255,0.22); }
    .alt-btn.s3:hover { box-shadow: 0 10px 26px rgba(245,158,11,0.6), inset 0 1px 0 rgba(255,255,255,0.28); }

    .alt-btn.s4 { background: linear-gradient(135deg, #8b5cf6 0%, #a78bfa 50%, #c4b5fd 100%); box-shadow: 0 6px 18px rgba(139,92,246,0.42), inset 0 1px 0 rgba(255,255,255,0.22); }
    .alt-btn.s4:hover { box-shadow: 0 10px 26px rgba(139,92,246,0.6), inset 0 1px 0 rgba(255,255,255,0.28); }

    .alt-btn.s5 { background: linear-gradient(135deg, #ec4899 0%, #f472b6 50%, #f9a8d4 100%); box-shadow: 0 6px 18px rgba(236,72,153,0.42), inset 0 1px 0 rgba(255,255,255,0.22); }
    .alt-btn.s5:hover { box-shadow: 0 10px 26px rgba(236,72,153,0.6), inset 0 1px 0 rgba(255,255,255,0.28); }

    .card-foot {
        padding: 14px 24px; border-top: 1px solid var(--border-light);
        text-align: center; font-size: 12px; color: var(--text-muted);
    }
    .card-foot a { color: var(--accent); text-decoration: none; font-weight: 600; }
    .card-foot a:hover { text-decoration: underline; }

    .preview-area { min-height: 600px; display: flex; flex-direction: column; }

    .preview-empty {
        flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: 16px; padding: 40px;
    }
    .preview-empty-icon {
        width: 96px; height: 96px; border-radius: var(--radius-lg);
        background: var(--bg-light); border: 2px dashed var(--border-light);
        display: flex; align-items: center; justify-content: center; color: var(--text-muted);
        animation: gentleBounce 3s ease-in-out infinite;
    }
    .preview-empty-icon svg { width: 40px; height: 40px; }
    @keyframes gentleBounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }
    .preview-empty h3 { font-size: 16px; font-weight: 600; color: var(--text-secondary); }
    .preview-empty p { font-size: 13px; color: var(--text-muted); max-width: 260px; text-align: center; line-height: 1.7; }

    .preview-features { display: flex; gap: 24px; margin-top: 8px; flex-wrap: wrap; justify-content: center; }
    .preview-feat { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--text-muted); }
    .preview-feat svg { width: 14px; height: 14px; color: var(--accent); }

    .pdf-wrap { flex: 1; display: flex; flex-direction: column; }
    .pdf-bar {
        display: flex; align-items: center; justify-content: space-between;
        padding: 12px 20px; background: var(--accent-light);
        border-bottom: 1px solid var(--accent-border);
    }
    .pdf-rc-no {
        font-family: 'JetBrains Mono', monospace; font-size: 14px; font-weight: 700; color: var(--accent-hover);
        display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    }
    .pdf-rc-no svg { width: 16px; height: 16px; }
    .pdf-type-badge {
        padding: 2px 8px; border-radius: 10px;
        background: rgba(59,130,246,0.15); color: #1e40af;
        font-size: 11px; font-weight: 700; letter-spacing: 0.3px;
    }

    .pdf-btns { display: flex; gap: 8px; flex-wrap: wrap; }
    .pdf-btn {
        padding: 8px 16px; border-radius: var(--radius-sm); border: 1px solid var(--border-light);
        background: var(--bg-white); color: var(--text-body);
        font-family: 'Inter', sans-serif; font-size: 12px; font-weight: 500; cursor: pointer;
        display: flex; align-items: center; gap: 6px; transition: all 0.2s ease; text-decoration: none;
    }
    .pdf-btn:hover { border-color: #d1d5db; box-shadow: var(--shadow-xs); }
    .pdf-btn.primary {
        background: linear-gradient(135deg, var(--accent), #60a5fa);
        border-color: transparent; color: #fff; font-weight: 600;
        box-shadow: 0 3px 10px rgba(59, 130, 246, 0.25);
    }
    .pdf-btn.primary:hover { box-shadow: 0 5px 16px rgba(59, 130, 246, 0.35); transform: translateY(-1px); }
    .pdf-btn svg { width: 14px; height: 14px; }

    .pdf-frame-box { flex: 1; background: #e5e7eb; position: relative; }
    .pdf-frame-box iframe { width: 100%; height: 100%; min-height: 500px; border: none; }

    .fade-up { opacity: 0; transform: translateY(16px); animation: fadeUp 0.5s ease forwards; }
    .fade-up-delay { animation-delay: 0.12s; }
    @keyframes fadeUp { to { opacity: 1; transform: translateY(0); } }
    @keyframes spin { to { transform: rotate(360deg); } }

    @media (max-width: 1024px) {
        .page-wrapper { width: 100%; margin-left: 0; margin-top: 60px; padding: 14px; }
        .top-bar { flex-direction: column; gap: 14px; padding: 16px; }
        .top-bar-left { flex-direction: column; text-align: center; }
        .top-bar-right { width: 100%; justify-content: center; }
        .rc-input { font-size: 16px; letter-spacing: 2px; padding: 14px 14px 14px 44px; }
        .type-select { font-size: 14px; padding: 13px 48px 13px 44px; }
        .preview-area { min-height: 420px; }
        .pdf-bar { flex-direction: column; gap: 10px; padding: 10px 16px; }
        .pdf-btns { width: 100%; }
        .pdf-btn { flex: 1; justify-content: center; }
        .preview-features { flex-direction: column; gap: 8px; align-items: center; }
    }

    @media (min-width: 1025px) {
        .wrapper.toggled .page-wrapper,
        .wrapper.toggled:not(.sidebar-hovered) .page-wrapper {
            width: calc(100% - 70px); margin-left: 70px;
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
                <div class="top-bar-title">Instant Rc Pdf S5</div>
                <div class="top-bar-subtitle">S5 Server &mdash; pariprint.in</div>
            </div>
        </div>
        <div class="top-bar-right">
            <span class="status-tag live">
                <span class="status-dot"></span>
                Live
            </span>
            <a href="rc_get_s5_list" class="btn-ghost">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                History
            </a>
        </div>
    </div>

    <div style="margin:14px 0 0 0;padding:14px 18px;border-radius:14px;background:linear-gradient(135deg,#eff6ff 0%,#dbeafe 40%,#bfdbfe 100%);border:1.5px solid #93c5fd;display:flex;gap:14px;align-items:center;box-shadow:0 6px 20px rgba(59,130,246,0.12), inset 0 1px 0 rgba(255,255,255,0.7);">
      <div style="min-width:46px;height:46px;border-radius:12px;background:linear-gradient(135deg,#3b82f6 0%,#2563eb 60%,#1e40af 100%);display:flex;align-items:center;justify-content:center;color:#fff;flex-shrink:0;box-shadow:0 8px 22px rgba(59,130,246,0.4), inset 0 1px 0 rgba(255,255,255,0.25);">
        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
      </div>
      <div style="flex:1;">
        <div style="font-weight:800;color:#1e3a8a;font-size:15px;margin-bottom:3px;letter-spacing:0.2px;">&#9989; 5 Servers Available</div>
        <div style="font-size:13.5px;color:#1e40af;line-height:1.5;font-weight:500;">Agar ek Server kaam nahi kar raha hai, to kisi dusre Server se try kariye. Sabhi Servers ke alag-alag Backend hain — ek fail ho to doosra jaroor kaam karega.</div>
      </div>
    </div>

    <div style="height:14px;"></div>

    <div class="main-grid">

        <div class="white-card fade-up">
            <div class="card-head">
                <div class="card-head-title">
                    <svg width="16" height="16" fill="none" stroke="var(--accent)" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                    Fetch Instant RC PDF (S5 Server)
                </div>
                <span class="card-head-badge">S5 / Type</span>
            </div>
            <div class="card-content">
                <form method="post" id="rcForm">
                    <label class="form-label">Vehicle Registration Number<span class="req">*</span></label>
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

                    <label class="form-label">Type<span class="req">*</span></label>
                    <div class="input-wrap">
                        <select
                            class="type-select"
                            name="rc_type"
                            id="rc_type"
                            required
                        >
                            <option value="" disabled>-- Select --</option>
                            <option value="1" <?php echo $selected_type==='1'?'selected':''; ?>>Chip - 1</option>
                            <option value="2" <?php echo $selected_type==='2'?'selected':''; ?>>Without Chip - 2</option>
                            <option value="3" <?php echo $selected_type==='3'?'selected':''; ?>>Chip New - 3</option>
                            <option value="4" <?php echo $selected_type==='4'?'selected':''; ?>>Without Chip New - 4</option>
                        </select>
                        <span class="input-icon" style="pointer-events:none;">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                        </span>
                    </div>

                    <div class="price-row">
                        <span class="price-row-label">Service Charge</span>
                        <span class="price-row-value">
                            <?php
                                $_s5p = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT price FROM pricing WHERE service_name='rc_s5_fee'"));
                                $_s5pv = isset($_s5p['price']) ? trim((string)$_s5p['price']) : '';
                                $_s5disp = (is_numeric($_s5pv) && (float)$_s5pv > 0) ? (string)preg_replace('/\.0+$/', '', number_format((float)$_s5pv, 2, '.', '')) : '30';
                                echo "₹" . $_s5disp;
                            ?>
                        </span>
                    </div>

                    <div class="balance-row">
                        <span class="balance-row-label">Available Balance</span>
                        <span class="balance-row-value">₹<?php echo $udata['balance']; ?></span>
                    </div>

                    <button type="submit" name="find" class="btn-submit" id="submitBtn">
                        <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
                        <span>Get RC PDF (S5)</span>
                    </button>
                </form>

                <div class="alt-servers">
                    <p>&#128260; Try Other Server (Agar ek fail ho to dusra use kare)</p>
                    <div class="alt-server-btns">
                        <a href="rc_get"              class="alt-btn s1">S1 (Chip)</a>
                        <a href="rc_get_server2"      class="alt-btn s2">S2</a>
                        <a href="rc_get_server3"      class="alt-btn s3">S3</a>
                        <a href="rc_get_server4"      class="alt-btn s4">S4</a>
                        <a href="rc_get_nochip"       class="alt-btn s5">S5 (NoChip)</a>
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
                                <span class="pdf-type-badge">Type <?php echo $selected_type; ?></span>
                            </div>
                            <div class="pdf-btns">
                                <form action="rc_s5_pdf_download.php" method="get" target="_blank" rel="noopener noreferrer" style="margin:0;padding:0;display:inline-flex;">
                                    <input type="hidden" name="id" value="<?php echo (int)$last_rcnc_id; ?>">
                                    <button type="submit" class="pdf-btn primary" style="all:unset;display:inline-flex;align-items:center;gap:6px;cursor:pointer;">
                                        <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
                                        Download
                                    </button>
                                </form>
                                <a href="rc_get_s5_list" class="pdf-btn">
                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                    All Records
                                </a>
                            </div>
                        </div>
                        <div class="pdf-frame-box">
                            <iframe src="<?php echo htmlspecialchars($pdf_preview); ?>" type="application/pdf"></iframe>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="preview-empty">
                        <div class="preview-empty-icon">
                            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/><path d="M9 7h6M9 11h6M9 15h4"/></svg>
                        </div>
                        <h3>No Document Loaded</h3>
                        <p>Enter a valid vehicle registration number, select Type, and submit to generate the RC PDF here instantly.</p>
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
