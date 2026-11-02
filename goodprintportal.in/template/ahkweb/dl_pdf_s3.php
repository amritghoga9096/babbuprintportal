<?php
include('header.php');

/* ==========================================================================
   Instant DL PDF Server 3  (pariprint.in slug: instant-dl-pdf-server-3)
   Upstream status: 100 = success, 200 = error (validated on pariprint too)
   ========================================================================== */

$api_error = '';
$dl_input_value = '';
$dob_input_value = '';

@mysqli_report(MYSQLI_REPORT_OFF);
@mysqli_query($ahk_conn, "CREATE TABLE IF NOT EXISTS `dl_pdf_s3` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) DEFAULT NULL,
  `dl_no` varchar(100) DEFAULT NULL,
  `dob` varchar(20) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `pdf` longtext DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `date` varchar(100) DEFAULT NULL,
  `request_id` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_dl_s3_user` (`username`),
  KEY `idx_dl_s3_dl` (`dl_no`),
  KEY `idx_dl_s3_reqid` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$_dlS3PrExists = @mysqli_fetch_assoc(@mysqli_query($ahk_conn, "SELECT id FROM pricing WHERE service_name='dl_pdf_s3_fee' LIMIT 1"));
if (!$_dlS3PrExists) {
    @mysqli_query($ahk_conn, "INSERT INTO pricing (service_name, price) VALUES ('dl_pdf_s3_fee', '50')");
}

$_dlS3PriceRow = @mysqli_fetch_assoc(@mysqli_query($ahk_conn, "SELECT price FROM pricing WHERE service_name='dl_pdf_s3_fee' LIMIT 1"));
$_dlS3PriceRaw = isset($_dlS3PriceRow['price']) ? trim((string)$_dlS3PriceRow['price']) : '';
$fee = (is_numeric($_dlS3PriceRaw) && (float)$_dlS3PriceRaw > 0) ? (float)$_dlS3PriceRaw : 50.0;
$fee_display = (string)preg_replace('/\.0+$/', '', number_format($fee, 2, '.', ''));

if (isset($_POST['find'])) {
    $dl_input_value = strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', (string)($_POST['dl_no'] ?? '')));
    $dob_input_value = trim((string)($_POST['dob'] ?? ''));

    // HTML date input gives YYYY-MM-DD; upstream needs DD-MM-YYYY
    $dob_api = '';
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dob_input_value, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
        $dob_api = $m[3] . '-' . $m[2] . '-' . $m[1];
    } elseif (preg_match('/^(\d{2})[-\/](\d{2})[-\/](\d{4})$/', $dob_input_value, $m) && checkdate((int)$m[2], (int)$m[1], (int)$m[3])) {
        $dob_api = $m[1] . '-' . $m[2] . '-' . $m[3];
        $dob_input_value = $m[3] . '-' . $m[2] . '-' . $m[1];
    }

    $username = mysqli_real_escape_string($ahk_conn, (string)$udata['phone']);
    $dl_no = mysqli_real_escape_string($ahk_conn, $dl_input_value);
    $dob_sql = mysqli_real_escape_string($ahk_conn, $dob_api);

    if (!preg_match('/^[A-Z]{2}[A-Z0-9\-]{8,}$/', $dl_input_value)) {
        $api_error = 'Valid Driving Licence Number daaliye (EX: MH0220070049048). DL Number ki jagah DOB mat daaliye.';
    } elseif ($dob_api === '') {
        $api_error = 'Valid Date of Birth select kijiye.';
    } else {
        $dupCheck = @mysqli_fetch_assoc(mysqli_query($ahk_conn,
            "SELECT id FROM dl_pdf_s3 WHERE username = '$username' AND dl_no = '$dl_no' AND dob = '$dob_sql' AND status = 'Success' LIMIT 1"
        ));
        if ($dupCheck) {
            $listUrl = 'dl_pdf_s3_list?dl=' . rawurlencode($dl_input_value) . '&dup=1&_=' . time();
            while (@ob_get_level()) { @ob_end_clean(); }
            @header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true);
            @header('Location: ' . $listUrl, true, 303);
            ?>
<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="refresh" content="0; url=<?php echo htmlspecialchars($listUrl); ?>"></head>
<body><script>try{ window.location.replace(<?php echo json_encode($listUrl); ?>); }catch(e){}</script></body></html>
<?php
            exit;
        }

        $wallet_amount = (float)$udata['balance'];

        if ($wallet_amount < $fee) {
            ?>
            <script>
                $(function () {
                    Swal.fire({
                        title: 'Insufficient Balance',
                        html: 'Required: <b>₹<?php echo htmlspecialchars($fee_display); ?></b><br>Your Balance: <b>₹<?php echo htmlspecialchars((string)$udata['balance']); ?></b><br><small class="text-warning">Please recharge to continue.</small>',
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
        } else {
            // Same PariPrint key source as other services (Admin -> Settings -> PariPrint API Key)
            $pp_send_api_key = '';
            $_r = @mysqli_fetch_assoc(@mysqli_query($ahk_conn, "SELECT * FROM settings WHERE id=1 LIMIT 1"));
            if (is_array($_r)) {
                if (!empty($_r['pariprint_api_key']) && is_string($_r['pariprint_api_key'])) {
                    $pp_send_api_key = trim((string)$_r['pariprint_api_key']);
                }
                if (strlen($pp_send_api_key) < 40 || substr($pp_send_api_key, 0, 4) !== 'phk_') {
                    foreach ($_r as $_cc => $_vv) {
                        if (!is_string($_vv) || strlen($_vv) < 40) continue;
                        if (substr($_vv, 0, 4) === 'phk_' || (stripos($_cc, 'pari') !== false && stripos($_cc, 'key') !== false)) {
                            $pp_send_api_key = trim($_vv);
                            break;
                        }
                    }
                }
            }
            if (strlen($pp_send_api_key) < 40) {
                $_ex = (string)($GLOBALS['pariprint_api_key'] ?? '');
                if (strlen($_ex) >= 40) { $pp_send_api_key = $_ex; }
            }

            if (strlen($pp_send_api_key) < 40 || substr($pp_send_api_key, 0, 4) !== 'phk_') {
                $api_error = 'PariPrint API Key missing / invalid. Admin -> Settings -> PariPrint API Key me phk_... key save kijiye.';
            } else {
                $apiBase = (function_exists('get_central_api_domain') ? rtrim(get_central_api_domain(), '/') : 'https://pariprint.in');
                $url = $apiBase . '/api-proxy.php?' . http_build_query([
                    'slug'   => 'instant-dl-pdf-server-3',
                    'dlno'   => $dl_input_value,
                    'dob'    => $dob_api,
                    'apiKey' => $pp_send_api_key,
                ]);

                $curl = curl_init();
                curl_setopt_array($curl, [
                    CURLOPT_URL            => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING       => '',
                    CURLOPT_MAXREDIRS      => 10,
                    CURLOPT_CONNECTTIMEOUT => 20,
                    CURLOPT_TIMEOUT        => 150,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST  => 'GET',
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
                ]);
                $response = curl_exec($curl);
                $http_code = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
                $curl_error = curl_error($curl);
                curl_close($curl);

                $result = is_string($response) ? json_decode($response, true) : null;

                $_logDir = __DIR__ . '/../../logs';
                if (!is_dir($_logDir)) { @mkdir($_logDir, 0755, true); }
                @file_put_contents($_logDir . '/dl_s3_debug_' . preg_replace('/[^A-Z0-9]/', '', $dl_input_value) . '_' . date('Ymd_His') . '.txt',
                    "DL_NO: {$dl_input_value}\nDOB: {$dob_api}\nHTTP: {$http_code}\nCURL_ERR: {$curl_error}\nKEYS: " . (is_array($result) ? implode(', ', array_keys($result)) : 'N/A') .
                    "\nSTATUS: " . (is_array($result) ? json_encode($result['status'] ?? null) : '') .
                    "\nMESSAGE: " . (is_array($result) ? (string)($result['statusMessage'] ?? $result['message'] ?? '') : substr((string)$response, 0, 500)) . "\n"
                );

                // status 100 = success, status 200 = error
                $statusStr = '';
                if (is_array($result)) {
                    $_st = $result['status_code'] ?? ($result['statusCode'] ?? ($result['status'] ?? null));
                    if (is_scalar($_st)) $statusStr = strtolower(trim((string)$_st));
                    if (is_bool($_st)) $statusStr = $_st ? 'true' : 'false';
                }
                $api_message = is_array($result) ? trim((string)($result['statusMessage'] ?? $result['message'] ?? $result['msg'] ?? $result['error'] ?? '')) : '';

                // Extract and verify real PDF bytes
                $pdf_bytes = '';
                if (is_array($result)) {
                    $pdfRaw = $result['pdf'] ?? $result['pdf_base64'] ?? $result['dlpdf'] ?? (is_array($result['data'] ?? null) ? ($result['data']['pdf'] ?? '') : '');
                    $pdfRaw = is_string($pdfRaw) ? trim($pdfRaw) : '';
                    if ($pdfRaw !== '') {
                        $b64 = $pdfRaw;
                        if (preg_match('#^data:[a-z0-9.+-]+/[a-z0-9.+-]+;base64,(.*)$#is', $pdfRaw, $pm)) {
                            $b64 = (string)$pm[1];
                        }
                        $b64 = preg_replace('/\s+/', '', $b64);
                        $bin = @base64_decode($b64, true);
                        if (is_string($bin) && strncmp($bin, '%PDF', 4) === 0) {
                            $pdf_bytes = $bin;
                        }
                    }
                }

                $is_success = ($http_code === 200 && $statusStr === '100' && $pdf_bytes !== '');

                if ($is_success) {
                    $requestId = 0;
                    if (isset($result['request_id']) && ctype_digit((string)$result['request_id'])) $requestId = (int)$result['request_id'];
                    if ($requestId <= 0 && isset($result['order_id']) && ctype_digit((string)$result['order_id'])) $requestId = (int)$result['order_id'];

                    $fn = preg_replace('/[^A-Za-z0-9]/', '', $dl_input_value);
                    $final_file_name = 'DL_' . ($fn !== '' ? $fn : 'DOC') . '_S3.pdf';

                    // Deduct wallet only if balance is still sufficient (no negative balance)
                    mysqli_query($ahk_conn, "UPDATE users SET balance = balance - $fee WHERE phone = '$username' AND balance >= $fee");
                    $debited = mysqli_affected_rows($ahk_conn) > 0;

                    $balRow = @mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT balance FROM users WHERE phone = '$username' LIMIT 1"));
                    $debit_fee = isset($balRow['balance']) ? $balRow['balance'] : ($wallet_amount - $fee);

                    date_default_timezone_set("Asia/Kolkata");
                    $time_hkb = date('d/m/Y g:i:s');

                    $file_for_db = mysqli_real_escape_string($ahk_conn, 'data:application/pdf;base64,' . base64_encode($pdf_bytes));
                    $safe_fn = mysqli_real_escape_string($ahk_conn, $final_file_name);
                    $safe_req = $requestId > 0 ? (string)$requestId : 'NULL';
                    $insert = mysqli_query($ahk_conn, "INSERT INTO `dl_pdf_s3`
                        (`username`, `dl_no`, `dob`, `status`, `pdf`, `file_name`, `date`, `request_id`)
                        VALUES ('$username', '$dl_no', '$dob_sql', 'Success', '$file_for_db', '$safe_fn', '$time_hkb', $safe_req)");
                    $last_id = $insert ? (int)mysqli_insert_id($ahk_conn) : 0;

                    if ($debited) {
                        mysqli_query($ahk_conn, "INSERT INTO `wallethistory`
                            (`userid`, `amount`, `balance`, `purpose`, `status`, `type`)
                            VALUES ('$username', '$fee', '$debit_fee', 'Instant DL Pdf Server 3 - $dl_no', '1', 'Debit')");
                    }

                    while (@ob_get_level()) { @ob_end_clean(); }
                    $listUrl = 'dl_pdf_s3_list?dl=' . rawurlencode($dl_input_value) . '&newid=' . $last_id . '&_=' . time();
                    @header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true);
                    @header('Location: ' . $listUrl, true, 303);
                    ?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>Redirecting…</title><meta http-equiv="refresh" content="0; url=<?php echo htmlspecialchars($listUrl); ?>"></head>
<body style="font-family:Arial,sans-serif;text-align:center;padding:40px;color:#065f46;background:#ecfdf5;">
  <h2 style="margin:0 0 12px;font-size:18px;">DL PDF Server 3 Generated. Redirecting to list…</h2>
  <p style="margin:0 0 18px;">DL: <b style="background:#fff;padding:4px 10px;border-radius:6px;"><?php echo htmlspecialchars($dl_input_value); ?></b> &nbsp; Amount debited: <b>₹<?php echo htmlspecialchars($fee_display); ?></b></p>
  <a href="<?php echo htmlspecialchars($listUrl); ?>" style="display:inline-block;padding:10px 18px;background:#059669;color:#fff;text-decoration:none;border-radius:8px;font-weight:700;">If not redirected click here →</a>
  <script> try{ window.location.replace(<?php echo json_encode($listUrl); ?>); }catch(e){} </script>
</body>
</html>
<?php
                    exit;
                }

                if ($response === false || $curl_error !== '' || $http_code === 0) {
                    $api_error = 'Failed to connect to API server.';
                } elseif (!is_array($result)) {
                    $api_error = 'Invalid response from API.';
                } elseif ($statusStr === '200') {
                    $api_error = ($api_message !== '' ? $api_message : 'DL record not found.') . ' (Please check DL Number & Date of Birth)';
                } elseif ($statusStr === '100' && $pdf_bytes === '') {
                    $api_error = 'PDF not received from server. Please try again.';
                } else {
                    $api_error = $api_message !== '' ? $api_message : 'DL record not found. Please verify DL Number & Date of Birth.';
                }
            }
        }
    }

    if ($api_error !== '') {
        ?>
        <script>
            $(function () {
                Swal.fire({
                    title: 'Request Failed',
                    text: <?php echo json_encode($api_error); ?>,
                    icon: 'error',
                    confirmButtonColor: '#e74c3c'
                });
            });
        </script>
        <?php
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
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><circle cx="8" cy="11" r="2.5"/><path d="M4.5 17c.8-1.8 2-2.6 3.5-2.6s2.7.8 3.5 2.6M14 9h5M14 13h5"/></svg>
            </div>
            <div>
                <div class="top-bar-title">Instant DL Pdf Server 3</div>
                <div class="top-bar-subtitle">Server 3 &mdash; pariprint.in</div>
            </div>
        </div>
        <div class="top-bar-right">
            <span class="status-tag live">
                <span class="status-dot"></span>
                Live
            </span>
            <a href="dl_pdf_s3_list" class="btn-ghost">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                History
            </a>
        </div>
    </div>

    <div class="main-grid">

        <div class="white-card fade-up">
            <div class="card-head">
                <div class="card-head-title">
                    <svg width="16" height="16" fill="none" stroke="var(--accent)" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                    Fetch Instant DL PDF (Server 3)
                </div>
                <span class="card-head-badge">DL / DOB</span>
            </div>
            <div class="card-content">
                <form method="post" id="dlForm">
                    <label class="form-label">Driving Licence Number<span class="req">*</span></label>
                    <div class="input-wrap">
                        <input
                            type="text"
                            required
                            class="rc-input"
                            name="dl_no"
                            id="dl_no"
                            placeholder="MH0220070049048"
                            value="<?php echo htmlspecialchars($dl_input_value); ?>"
                            oninput="removeSpaces(this)"
                            autocomplete="off"
                            spellcheck="false"
                        >
                        <span class="input-icon">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><circle cx="8" cy="11" r="2.5"/><path d="M14 9h5M14 13h5"/></svg>
                        </span>
                    </div>

                    <label class="form-label">Date of Birth<span class="req">*</span></label>
                    <div class="input-wrap">
                        <input
                            type="date"
                            required
                            class="rc-input"
                            style="letter-spacing:1px;text-transform:none;font-size:16px;"
                            name="dob"
                            id="dob"
                            max="<?php echo date('Y-m-d'); ?>"
                            value="<?php echo htmlspecialchars($dob_input_value); ?>"
                        >
                        <span class="input-icon" style="pointer-events:none;">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        </span>
                    </div>

                    <div class="price-row">
                        <span class="price-row-label">Service Charge</span>
                        <span class="price-row-value">₹<?php echo htmlspecialchars($fee_display); ?></span>
                    </div>

                    <div class="balance-row">
                        <span class="balance-row-label">Available Balance</span>
                        <span class="balance-row-value">₹<?php echo htmlspecialchars((string)$udata['balance']); ?></span>
                    </div>

                    <button type="submit" name="find" class="btn-submit" id="submitBtn">
                        <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
                        <span>Get DL PDF (Server 3)</span>
                    </button>
                </form>
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
                <span class="card-head-badge">Waiting</span>
            </div>

            <div class="preview-area">
                <div class="preview-empty">
                    <div class="preview-empty-icon">
                        <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/><path d="M9 7h6M9 11h6M9 15h4"/></svg>
                    </div>
                    <h3>No Document Loaded</h3>
                    <p>Enter Driving Licence Number and Date of Birth, then submit. Generated PDF will be available in History for download.</p>
                    <div class="preview-features">
                        <span class="preview-feat">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 15V3m0 12l-4-4m4 4l4-4M2 17l.621 2.485A2 2 0 004.561 21h14.878a2 2 0 001.94-1.515L22 17"/></svg>
                            PDF Download
                        </span>
                        <span class="preview-feat">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            Charge only on Success
                        </span>
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>

<script>
function removeSpaces(input) {
    input.value = input.value.replace(/\s/g, "").toUpperCase();
}

document.getElementById('dlForm').addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.innerHTML = '<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="animation:spin 1s linear infinite"><path d="M12 2v4m0 12v4m-7.07-15.07l2.83 2.83m8.48 8.48l2.83 2.83M2 12h4m12 0h4M4.93 19.07l2.83-2.83m8.48-8.48l2.83-2.83"/></svg><span>Processing...</span>';
    btn.style.opacity = '0.75';
    btn.style.pointerEvents = 'none';
});
</script>

<?php include("footer.php"); ?>
</body>
</html>
