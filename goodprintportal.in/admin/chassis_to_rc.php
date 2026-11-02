<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

date_default_timezone_set('Asia/Kolkata');

// ============================================================
// 🔗 SETTINGS API SYNC (NUCLEAR FIX): settings.php → PariPrint API Key
// ============================================================
$ctrcApiKey = isset($pariprint_api_key) ? (string)$pariprint_api_key : '';
$ctrcKeySource = 'global fallback $pariprint_api_key from config.php';
if (is_object($GLOBALS['ahk_conn'] ?? $ahk_conn)) {
    $_cx = $GLOBALS['ahk_conn'] ?? $ahk_conn;
    $_r1 = @mysqli_fetch_assoc(@mysqli_query($_cx, "SELECT pariprint_api_key FROM settings WHERE id=1 LIMIT 1"));
    if (is_array($_r1) && isset($_r1['pariprint_api_key']) && is_string($_r1['pariprint_api_key'])) {
        $_v1 = trim((string)$_r1['pariprint_api_key']);
        if (strlen($_v1) >= 40) {
            $ctrcApiKey = $_v1;
            $ctrcKeySource = 'DIRECT DB settings.pariprint_api_key';
            $GLOBALS['pariprint_api_key'] = $_v1;
            $pariprint_api_key = $_v1;
        }
    }
    if (strlen($ctrcApiKey) < 40 || substr($ctrcApiKey, 0, 4) !== 'phk_') {
        $_r2 = @mysqli_fetch_assoc(@mysqli_query($_cx, "SELECT * FROM settings WHERE id=1 LIMIT 1"));
        if (is_array($_r2)) {
            foreach ($_r2 as $_kk => $_vv) {
                if (!is_string($_vv) || strlen($_vv) < 40) continue;
                if (substr($_vv, 0, 4) === 'phk_' || (strpos(strtolower($_kk), 'pari') !== false && strpos(strtolower($_kk), 'key') !== false)) {
                    $ctrcApiKey = trim($_vv);
                    $ctrcKeySource = 'DB FALLBACK col ' . $_kk;
                    $GLOBALS['pariprint_api_key'] = $ctrcApiKey;
                    $pariprint_api_key = $ctrcApiKey;
                    break;
                }
            }
        }
    }
}

function ctrc_ensure_schema($conn) {
    if (!$conn) return;
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `chassis_to_rc_requests` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `application_no` VARCHAR(80) NOT NULL,
        `username` VARCHAR(50) NOT NULL,
        `chassis_number` VARCHAR(80) DEFAULT NULL,
        `order_id` VARCHAR(80) DEFAULT NULL,
        `rc_number` VARCHAR(80) DEFAULT NULL,
        `owner_name` VARCHAR(255) DEFAULT NULL,
        `download_url` TEXT DEFAULT NULL,
        `result_json` LONGTEXT DEFAULT NULL,
        `status_text` VARCHAR(50) DEFAULT NULL,
        `fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `old_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `new_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `message` TEXT DEFAULT NULL,
        `date` VARCHAR(50) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_ctrc_application_no` (`application_no`),
        KEY `idx_ctrc_username` (`username`),
        KEY `idx_ctrc_chassis_number` (`chassis_number`),
        KEY `idx_ctrc_rc_number` (`rc_number`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function ctrc_fee($conn) {
    $queries = [
        "SELECT price FROM pricing WHERE service_name='chassis_to_rc_fee' LIMIT 1",
        "SELECT price FROM pricing WHERE service_name='Chassic_rc_find' LIMIT 1",
    ];
    foreach ($queries as $sql) {
        $res = mysqli_query($conn, $sql);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        if ($row && isset($row['price'])) {
            $_pv = trim((string)$row['price']);
            if (is_numeric($_pv) && (float)$_pv > 0) return (float)$_pv;
        }
    }
    return 25.0;
}

ctrc_ensure_schema($ahk_conn);

$resdata = [];
$chassisNumber = '';
$pageMessage = '';
$alertScript = '';
$balance = (float)($udata['balance'] ?? 0);
$userPhone = (string)($udata['phone'] ?? '');
$fee = ctrc_fee($ahk_conn);
$resultData = [];
$displayHeader = [];

if (isset($_POST['submit_ctrc'])) {
    $chassisNumber = strtoupper(trim((string)($_POST['chassisNumber'] ?? '')));
    // Chassis can be 17-char alphanumeric VIN, sometimes shorter partial; accept 6..25
    if (!preg_match('/^[A-Z0-9]{6,25}$/', $chassisNumber)) {
        $alertScript = "<script>Swal.fire({title:'Invalid Chassis Number',text:'Please enter a valid Chassis / VIN Number (alphanumeric, 6-25 chars). Eg: MA1XL2WJXK5B30344',icon:'warning',confirmButtonText:'OK'});</script>";
    } elseif ($balance < $fee) {
        $alertScript = "<script>Swal.fire({title:'Balance Low! Recharge Now',text:'Insufficient Balance',icon:'warning',confirmButtonText:'OK'}).then((result)=>{ if(result.isConfirmed) window.location='wallet.php'; });</script>";
    } elseif (strlen($ctrcApiKey) < 40 || substr($ctrcApiKey, 0, 4) !== 'phk_') {
        $alertScript = "<script>Swal.fire({title:'PariPrint API Key Required',text:'Admin Settings me PariPrint API Key save nahi hai. Settings > PariPrint API Key me 44-char ki phk_... key save kar ke fir try karo.',icon:'error',confirmButtonText:'OK'});</script>";
    } else {
        // Doc Exact: slug=chassis-to-rc, params: chassis_number, api_key (underscore!)
        $apiUrl = 'https://pariprint.in/api-proxy.php?' . http_build_query([
            'slug'           => 'chassis-to-rc',
            'chassis_number' => $chassisNumber,
            'api_key'        => $ctrcApiKey,
        ]);

        // ===== BULLETPROOF 2-TIER API CALL + CLOUDFLARE FINGERPRINT BYPASS =====
        $_logDir = __DIR__ . '/../logs';
        if (!is_dir($_logDir)) { @mkdir($_logDir, 0755, true); }
        $_debugFile = $_logDir . '/ctrc_debug_' . $chassisNumber . '_' . date('Ymd_His') . '.txt';
        $_tierLog   = "CHASSIS: {$chassisNumber}\nSLUG: chassis-to-rc\nKEY_SOURCE: {$ctrcKeySource}\nKEY_LEN: " . strlen($ctrcApiKey) . "\nKEY_PREFIX: " . substr($ctrcApiKey, 0, 5) . "***\nTIME: " . date('Y-m-d H:i:s') . "\n";
        @file_put_contents($_debugFile, $_tierLog);

        $currentHost  = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'goodprintportal.in';
        $currentProto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'https';
        $keyQ = 'api_key=' . urlencode($ctrcApiKey) . '&chassis_number=' . urlencode($chassisNumber);

        $cookieJar = $_logDir . '/ctrc_cookiejar_' . md5($currentHost) . '.txt';
        if (!is_file($cookieJar)) { @touch($cookieJar); }

        $browserHeaders = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Accept-Language: en-IN,en-GB;q=0.9,en;q=0.8,hi-IN;q=0.7,hi;q=0.6',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
            'Sec-CH-UA: "Google Chrome";v="129", "Not=A?Brand";v="8", "Chromium";v="129"',
            'Sec-CH-UA-Mobile: ?0',
            'Sec-CH-UA-Platform: "Windows"',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Sec-Fetch-User: ?1',
            'Upgrade-Insecure-Requests: 1',
        ];
        $chromeUA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36';

        $tierList = [];
        // TIER A: Same-domain local /pariprint/ (fastest, CF lenient)
        $tierList[] = [
            'id' => 'A', 'name' => 'Same-Domain /pariprint/',
            'url'  => "{$currentProto}://{$currentHost}/pariprint/api-proxy.php?slug=chassis-to-rc&{$keyQ}",
            'headers' => array_merge($browserHeaders, [
                "Referer: {$currentProto}://{$currentHost}/admin/chassis_to_rc",
                "Origin: {$currentProto}://{$currentHost}",
                "Host: {$currentHost}",
            ]),
            'isPublic' => false,
        ];
        // TIER B: Public pariprint.in (fallback)
        $tierList[] = [
            'id' => 'B', 'name' => 'Public pariprint.in',
            'url'  => "https://pariprint.in/api-proxy.php?slug=chassis-to-rc&{$keyQ}",
            'headers' => array_merge($browserHeaders, [
                'Referer: https://pariprint.in/api-docs.php?slug=chassis-to-rc',
                'Origin: https://pariprint.in',
                'Host: pariprint.in',
            ]),
            'isPublic' => true,
        ];

        $_tierOk = false;
        $curlError = '';
        $response  = '';
        $httpCode  = 0;

        foreach ($tierList as $_tierIdx => $_t) {
            $_tierLog .= "\n====== TIER {$_t['id']} : {$_t['name']} ======\nURL: " . preg_replace('/(apikey|api_key)=[^&]+/i', '$1=***HIDDEN***', $_t['url']) . "\n";
            $tErrNo = 0; $tErrStr = ''; $tHTTP = 0; $tCT = ''; $tTime = 0; $tLen = 0; $tBody = '';

            $curlOpts = [
                CURLOPT_URL            => $_t['url'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => '',
                CURLOPT_MAXREDIRS      => 12,
                CURLOPT_TIMEOUT        => 45,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_2_0,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_CUSTOMREQUEST  => 'GET',
                CURLOPT_USERAGENT      => $chromeUA,
                CURLOPT_HTTPHEADER     => $_t['headers'],
                CURLOPT_VERBOSE        => false,
                CURLOPT_HEADER         => false,
                CURLOPT_CONNECTTIMEOUT => 20,
            ];
            if ($_t['isPublic']) {
                $curlOpts[CURLOPT_COOKIEJAR]  = $cookieJar;
                $curlOpts[CURLOPT_COOKIEFILE] = $cookieJar;
            }

            $ch = curl_init();
            curl_setopt_array($ch, $curlOpts);
            $tStart  = microtime(true);
            $tBody   = curl_exec($ch);
            $tTime   = microtime(true) - $tStart;
            $tErrNo  = curl_errno($ch);
            $tErrStr = curl_error($ch);
            $tHTTP   = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $tCT     = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $tLen    = ($tBody !== false) ? strlen($tBody) : 0;
            curl_close($ch);

            $_looksJson = false;
            if ($tBody !== false && $tBody !== '') {
                $_trim = trim($tBody);
                if (($_trim[0] === '{' || $_trim[0] === '[')) {
                    json_decode($_trim, true);
                    $_looksJson = (json_last_error() === JSON_ERROR_NONE);
                }
            }
            $_tierLog .= "HTTP: {$tHTTP} | cURL: #{$tErrNo} {$tErrStr} | CT: {$tCT} | Time: ".number_format($tTime,3)."s | Bytes: {$tLen}\n";
            $_tierLog .= "Looks-JSON: " . ($_looksJson ? 'YES' : 'NO') . " | cURL-OK: " . ($tErrNo === 0 ? 'YES' : 'NO') . " | HTTP<400: " . ($tHTTP > 0 && $tHTTP < 400 ? 'YES' : 'NO') . "\n";

            if ($tBody !== false && $_looksJson && $tErrNo === 0 && $tHTTP > 0 && $tHTTP < 500) {
                $response   = $tBody;
                $httpCode   = $tHTTP;
                $curlError  = '';
                $_tierOk    = true;
                $_tierLog   .= "=> TIER {$_t['id']} SUCCESS.\n";
                break;
            }

            // Cloudflare 403 interstitial 2-pass retry (Tier B only)
            $_cfRetry = false;
            if ($_t['isPublic'] && $tBody !== false && $tHTTP === 403 && stripos($tBody, 'Checking your browser before accessing') !== false) {
                $_cfRetry = true;
                $_tierLog .= "=> CLOUDFLARE CHALLENGE DETECTED on TIER B. 2-pass retry after 1.5s...\n";
                usleep(1500000);
                $ch2 = curl_init();
                $curlOpts2 = $curlOpts;
                $curlOpts2[CURLOPT_COOKIEJAR]  = $cookieJar;
                $curlOpts2[CURLOPT_COOKIEFILE] = $cookieJar;
                curl_setopt_array($ch2, $curlOpts2);
                $tStart2  = microtime(true);
                $tBody2   = curl_exec($ch2);
                $tTime2   = microtime(true) - $tStart2;
                $tErrNo2  = curl_errno($ch2);
                $tErrStr2 = curl_error($ch2);
                $tHTTP2   = (int)curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                $tCT2     = (string)curl_getinfo($ch2, CURLINFO_CONTENT_TYPE);
                $tLen2    = ($tBody2 !== false) ? strlen($tBody2) : 0;
                curl_close($ch2);

                $_looksJson2 = false;
                if ($tBody2 !== false && $tBody2 !== '') {
                    $_trim2 = trim($tBody2);
                    if (($_trim2[0] === '{' || $_trim2[0] === '[')) {
                        json_decode($_trim2, true);
                        $_looksJson2 = (json_last_error() === JSON_ERROR_NONE);
                    }
                }
                $_tierLog .= "   [PASS 2] HTTP: {$tHTTP2} | cURL: #{$tErrNo2} {$tErrStr2} | CT: {$tCT2} | Time: ".number_format($tTime2,3)."s | Bytes: {$tLen2}\n";
                $_tierLog .= "   [PASS 2] Looks-JSON: " . ($_looksJson2 ? 'YES' : 'NO') . "\n";

                if ($tBody2 !== false && $_looksJson2 && $tErrNo2 === 0 && $tHTTP2 > 0 && $tHTTP2 < 500) {
                    $response  = $tBody2;
                    $httpCode  = $tHTTP2;
                    $curlError = '';
                    $_tierOk   = true;
                    $_tierLog  .= "=> TIER B PASS-2 SUCCESS (Cloudflare challenge bypassed).\n";
                    break;
                }
            }

            if (!$_tierOk && $_tierIdx === count($tierList)) {
                $response  = ($tBody !== false && $tBody !== '') ? $tBody : '';
                $httpCode  = $tHTTP;
                $curlError = $tErrStr !== '' ? $tErrStr : '';
            }
            $_tierLog .= "=> TIER {$_t['id']} FAILED" . ($_cfRetry ? " (after Cloudflare 2-pass)." : ".") . "\n";
        }
        @file_put_contents($_debugFile, $_tierLog . "\n\nFINAL: TIER-OK = " . ($_tierOk ? 'YES' : 'NO') . "\n", FILE_APPEND);

        if (!$_tierOk && $curlError === '') {
            $curlError = 'Both Tier A (local /pariprint/) & Tier B (public pariprint.in) failed. Debug log dekho.';
        }

        if (!$_tierOk || $curlError !== '') {
            $pageMessage = 'Request processing failed. Please try again after a few minutes.';
            $alertScript = '<script>Swal.fire({title:"Request Error",text:' . json_encode($pageMessage) . ',icon:"error",confirmButtonText:"OK"});</script>';
        } else {
            $data = json_decode($response, true);
            if (!is_array($data)) {
                $pageMessage = 'Unable to process the service response. Please try again after a few minutes.';
                $alertScript = '<script>Swal.fire({title:"Processing Error",text:' . json_encode($pageMessage) . ',icon:"error",confirmButtonText:"OK"});</script>';
            } else {
                $rawStatus  = isset($data['status'])  ? $data['status']  : ($data['success'] ?? false);
                $rawCode    = isset($data['code'])    ? $data['code']    : ($httpCode ? (string)$httpCode : '');
                $message    = (string)($data['message'] ?? 'No response message');
                $normStatus = strtolower(trim((string)$rawStatus));
                $displayHeader = $data;
                unset($displayHeader['wallet_balance']);

                // ===== BOTH FORMATS SUPPORT: Nested data[] AND Root-level keys =====
                // Format A: {status:"success", code:"200", data:{rc_number, owner_name, chassis_number, ...extra}}
                // Format B: {status:true, message, rc_number, owner_name, chassis_number, ...extra}
                $nested = (isset($data['data']) && is_array($data['data'])) ? $data['data'] : [];
                $resultData = $nested; // start with nested (Format A)
                foreach ($data as $_rk => $_rv) {
                    if ($_rk === 'data' || $_rk === 'wallet_balance' || $_rk === 'status' || $_rk === 'code' || $_rk === 'message' || $_rk === 'success') continue;
                    if (is_array($_rv) || is_object($_rv)) continue;
                    if (!array_key_exists($_rk, $resultData) || trim((string)$resultData[$_rk]) === '') {
                        $resultData[$_rk] = $_rv; // fill from root level (Format B)
                    }
                }
                // Extra fields inside nested.data also merge if not already present
                foreach ($nested as $_nk => $_nv) {
                    if (!array_key_exists($_nk, $resultData) || trim((string)$resultData[$_nk]) === '') {
                        $resultData[$_nk] = $_nv;
                    }
                }

                $rcNumber    = trim((string)($resultData['rc_number'] ?? ''));
                $ownerName   = trim((string)($resultData['owner_name'] ?? ''));
                $chassisOut  = trim((string)($resultData['chassis_number'] ?? $chassisNumber));
                $downloadUrl = trim((string)($resultData['download_url'] ?? $data['download_url'] ?? $data['pdf_url'] ?? ''));

                $hasRc = ($rcNumber !== '');
                $isSuccess = false;
                if ($hasRc) {
                    if ($rawStatus === true || $rawStatus === 1 || $rawStatus === '200' || in_array($normStatus, ['true','success','ok','completed'], true)) {
                        $isSuccess = true;
                    } elseif ($rawCode === '200' || $rawCode === 200) {
                        $isSuccess = true;
                    } elseif ($httpCode >= 200 && $httpCode < 300) {
                        $isSuccess = true;
                    }
                }

                if ($isSuccess) {
                    $newBalance = $balance - $fee;
                    $applicationNo = 'CTRC' . rand(100000, 999999);
                    $safeApp    = mysqli_real_escape_string($ahk_conn, $applicationNo);
                    $safeChassis= mysqli_real_escape_string($ahk_conn, $chassisOut);
                    $safeRc     = mysqli_real_escape_string($ahk_conn, $rcNumber);
                    $safeOwner  = mysqli_real_escape_string($ahk_conn, $ownerName);
                    $safeOrder  = mysqli_real_escape_string($ahk_conn, (string)($data['order_id'] ?? $data['request_id'] ?? ''));
                    $safeDl     = mysqli_real_escape_string($ahk_conn, $downloadUrl);
                    $safeResp   = mysqli_real_escape_string($ahk_conn, $response);
                    $safeStatus = mysqli_real_escape_string($ahk_conn, $normStatus);
                    $safeMsg    = mysqli_real_escape_string($ahk_conn, $message);
                    $safeUser   = mysqli_real_escape_string($ahk_conn, $userPhone);

                    $walletUpdated = mysqli_query($ahk_conn, "UPDATE users SET balance = balance - {$fee} WHERE phone='{$safeUser}'");
                    if ($walletUpdated) {
                        $ts = date('d/m/Y g:i:s');
                        mysqli_query($ahk_conn, "INSERT INTO chassis_to_rc_requests(application_no, username, chassis_number, order_id, rc_number, owner_name, download_url, result_json, status_text, fee, old_balance, new_balance, message, date) VALUES ('{$safeApp}','{$safeUser}','{$safeChassis}','{$safeOrder}','{$safeRc}','{$safeOwner}','{$safeDl}','{$safeResp}','{$safeStatus}',{$fee},{$balance},{$newBalance},'{$safeMsg}','{$ts}')");
                        mysqli_query($ahk_conn, "INSERT INTO wallethistory(userid, amount, balance, purpose, status, type) VALUES ('{$safeUser}','{$fee}','{$newBalance}','Chassis To RC Find - {$safeRc} ({$safeChassis})','1','Debit')");

                        $balance = $newBalance;
                        $pageMessage = $message;
                        $alertScript = "<script>Swal.fire({title:'Success!',text:'Chassis → RC details found. RC No: {$safeRc}',icon:'success',confirmButtonText:'OK'});</script>";
                    } else {
                        $pageMessage = 'API se data mil gaya, lekin wallet balance deduct nahi ho saka.';
                        $alertScript = '<script>Swal.fire({title:"Error",text:' . json_encode($pageMessage) . ',icon:"error",confirmButtonText:"OK"});</script>';
                    }
                } else {
                    $pageMessage = $message !== '' && $normStatus !== 'error' && !in_array($normStatus,['fail','failed','false','0'], true) ? $message : 'Chassis number ke liye koi RC details nahi mili.';
                    $alertScript = '<script>Swal.fire({title:"No Records Found",text:' . json_encode($pageMessage) . ',icon:"error",confirmButtonText:"OK"});</script>';
                }
            }
        }
    }
}

// If $displayHeader not populated during submit, rebuild from $resdata
if (empty($displayHeader) && !empty($resdata)) {
    $displayHeader = $resdata;
    unset($displayHeader['wallet_balance']);
}
if (empty($resultData) && !empty($resdata) && isset($resdata['data']) && is_array($resdata['data'])) {
    $resultData = $resdata['data'];
}
?>
<style>
    :root { --primary: #16a34a; --primary-dark: #15803d; --accent: #15803d; }
    body { background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 55%, #bbf7d0 100%); }
    .service-card { background: rgba(255,255,255,0.98); border:none; border-radius: 2rem; box-shadow: 0 25px 45px -12px rgba(22,163,74,0.18); }
    .fee-chip { background: linear-gradient(135deg, #16a34a, #15803d); color:#fff; font-weight:700; border-radius:60px; padding:0.5rem 1.8rem; font-size:1.3rem; box-shadow:0 8px 18px rgba(22,163,74,0.3); }
    .form-input { border-radius: 60px; padding:0.9rem 1.5rem; border:2px solid #bbf7d0; transition:all .2s; font-size:1rem; text-transform:uppercase; letter-spacing:0.5px; }
    .form-input:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(22,163,74,0.15); outline:none; }
    .submit-btn { background: linear-gradient(90deg, var(--primary), var(--primary-dark)); border:none; padding:0.9rem; font-weight:700; font-size:1.1rem; border-radius:60px; transition:all .3s; color:#fff; }
    .submit-btn:hover { transform:scale(1.02); box-shadow: 0 10px 25px rgba(22,163,74,0.45); color:#fff; }
    .balance-card { background:#fff; border-radius:1.5rem; border-left:5px solid #16a34a; }
    .credit-footer { background: linear-gradient(135deg, #14532d, #166534); color:#bbf7d0; border-radius:1rem; padding:.8rem 1.5rem; margin-top:2rem; }
    .details-card { background:#fff; border-radius:1.5rem; box-shadow: 0 12px 28px rgba(21,128,61,0.1); }
    .kv-grid { display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 12px; }
    .kv-item { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:14px; padding:12px 14px; }
    .kv-item .k { font-size:11px; text-transform:uppercase; letter-spacing:.5px; color:#15803d; font-weight:800; margin-bottom:4px; }
    .kv-item .v { font-size:15px; color:#052e16; word-break:break-word; font-weight:700; }
    .hero-tile { background: linear-gradient(135deg, #16a34a, #15803d); color: #fff; border-radius: 1.25rem; padding: 18px 20px; box-shadow: 0 10px 20px rgba(22,163,74,0.25); }
    .hero-tile .lbl { opacity: 0.85; font-size: 12px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; }
    .hero-tile .val { font-size: 22px; font-weight: 800; word-break: break-word; margin-top: 4px; }
    @media (max-width: 640px) { .kv-grid { grid-template-columns: 1fr; } }
</style>
<div class="page-wrapper">
    <div class="page-content">
        <div class="container-fluid py-4">
            <div class="row justify-content-center">
                <div class="col-lg-10 col-12">
                    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                        <div>
                            <h3 class="fw-bold mb-0 d-flex align-items-center gap-2">
                                <i class="bi bi-search fs-2" style="color:#16a34a;"></i>
                                <span>Chassis To RC Find</span>
                                <span class="badge rounded-pill" style="background:#16a34a;">Goodprintportal v1</span>
                            </h3>
                            <p class="text-muted mt-1 mb-0">17-digit Chassis / VIN daaliye → RC Number + Owner Name + full details instant, wallet success par debit.</p>
                        </div>
                        <div class="btn-group shadow-sm">
                            <a href="chassis_to_rc_list" class="btn btn-success rounded-pill px-3">
                                <i class="bi bi-list-ul"></i> My Searches
                            </a>
                            <a href="javascript:history.back()" class="btn btn-outline-secondary rounded-pill px-3">
                                <i class="bi bi-arrow-left"></i> Back
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-5 col-12 mb-4">
                    <div class="card service-card mb-4">
                        <div class="card-body p-4 p-lg-5">
                            <div class="text-center mb-4">
                                <div class="fee-chip">
                                    <i class="bi bi-tag-fill"></i> Rs. <?php echo number_format($fee, 2); ?> / Search
                                </div>
                                <div class="mt-3 d-flex justify-content-center gap-3 flex-wrap">
                                    <span class="badge bg-warning text-dark px-3 py-2 rounded-pill"><i class="bi bi-lightning-charge-fill"></i> Instant</span>
                                </div>
                            </div>
                            <div class="balance-card p-3 mb-4 d-flex justify-content-between align-items-center shadow-sm">
                                <div>
                                    <i class="bi bi-wallet2 fs-4 text-success"></i>
                                    <span class="fw-semibold ms-2">Available Balance</span>
                                </div>
                                <div class="fs-4 fw-bold">Rs. <?php echo number_format($balance, 2); ?></div>
                            </div>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold mb-2"><i class="bi bi-upc-scan"></i> Chassis / VIN Number</label>
                                    <input type="text" name="chassisNumber" class="form-control form-input" placeholder="Example: MA1XL2WJXK5B30344" maxlength="25" required value="<?php echo htmlspecialchars($chassisNumber); ?>">
                                    <div class="form-text text-muted mt-1">17 characters standard; partial (6+) bhi accept karta hai. Alphanumeric only.</div>
                                </div>
                                <button type="submit" name="submit_ctrc" class="btn submit-btn w-100" <?php echo ($balance < $fee) ? 'disabled' : ''; ?>>
                                    <i class="bi bi-search me-2"></i>
                                    <?php echo ($balance >= $fee) ? 'Find RC & Pay Rs. ' . number_format($fee, 2) : 'Insufficient Balance'; ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8 col-md-7 col-12 mb-4">
                    <div class="details-card p-4 p-lg-5 h-100">
                        <h4 class="fw-bold mb-3">Chassis → RC Result</h4>
                        <?php if (!empty($resultData)): ?>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <div class="hero-tile">
                                        <div class="lbl">RC Registration No</div>
                                        <div class="val"><?php echo htmlspecialchars((string)($resultData['rc_number'] ?? '—')); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="hero-tile" style="background: linear-gradient(135deg,#15803d,#166534);">
                                        <div class="lbl">Owner Name</div>
                                        <div class="val"><?php echo htmlspecialchars((string)($resultData['owner_name'] ?? '—')); ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-4">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                                        <div class="fw-bold text-success"><i class="bi bi-database-fill-gear me-2"></i>Full Details (All Fields)</div>
                                        <div>
                                            <?php if (!empty($resultData['rc_number'])): ?><span class="badge bg-primary me-1"><?php echo htmlspecialchars($resultData['rc_number']); ?></span><?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="kv-grid">
                                            <?php
                                            $labels = [
                                                'chassis_number' => 'Chassis / VIN',
                                                'rc_number'      => 'RC Registration No',
                                                'owner_name'     => 'Owner Full Name',
                                            ];
                                            foreach ($labels as $fk => $lbl):
                                                $v = isset($resultData[$fk]) ? trim((string)$resultData[$fk]) : '';
                                                if ($v === '' || $v === null) continue;
                                            ?>
                                                <div class="kv-item">
                                                    <div class="k"><?php echo $lbl; ?></div>
                                                    <div class="v"><?php echo htmlspecialchars($v); ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                            <?php foreach ($resultData as $ek => $ev):
                                                if (array_key_exists($ek, $labels)) continue;
                                                if ($ev === '' || $ev === null) continue;
                                                if (is_array($ev) || is_object($ev)) $ev = json_encode($ev, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                            ?>
                                                <div class="kv-item">
                                                    <div class="k"><?php echo htmlspecialchars(ucwords(str_replace(['_','-'],' ',(string)$ek))); ?></div>
                                                    <div class="v"><?php echo htmlspecialchars((string)$ev); ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php elseif ($pageMessage !== ''): ?>
                            <div class="alert alert-danger mb-0"><?php echo htmlspecialchars($pageMessage); ?></div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-search-heart fs-1" style="color:#16a34a;"></i>
                                <h5 class="text-muted mt-3">Enter Chassis / VIN Number</h5>
                                <p class="text-muted mb-0">RC Number, Owner Name aur saari extra fields — sab yahaan dikhega.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="container pb-4">
    <div class="credit-footer text-center">
        <div class="d-flex flex-wrap justify-content-center gap-4 align-items-center">
            <span><i class="bi bi-cpu"></i> Powered by <strong>goodprintportal.in</strong></span>
            <span class="opacity-75">|</span>
            <span><i class="bi bi-wallet2"></i> Wallet success par hi debit</span>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var inp = document.querySelector('input[name="chassisNumber"]');
    if (inp) {
        inp.addEventListener('input', function () {
            this.value = this.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase().slice(0, 25);
        });
    }
});
</script>
<?php echo $alertScript; ?>
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php include('userFooter.php'); ?>
