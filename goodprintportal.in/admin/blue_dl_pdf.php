<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

date_default_timezone_set("Asia/Kolkata");

global $CENTRAL_API_BASE, $pariprint_api_key, $ahk_conn, $udata, $clint;

define('BLUE_DL_PDF_FEE_DEFAULT', 60);

if (!function_exists('gp_get_service_fee')) {
    function gp_get_service_fee($ahk_conn, $candidates, $fallback) {
        if (!$ahk_conn) return (float)$fallback;
        $priceFound = null;
        foreach ($candidates as $svc) {
            $safe = mysqli_real_escape_string($ahk_conn, (string)$svc);
            $r = @mysqli_query($ahk_conn, "SELECT price FROM pricing WHERE service_name='{$safe}' LIMIT 1");
            if ($r && ($row = mysqli_fetch_assoc($r)) && isset($row['price']) && is_numeric($row['price'])) {
                $priceFound = (float)$row['price'];
                break;
            }
        }
        if ($priceFound === null) {
            $anyExists = false;
            foreach ($candidates as $svc) {
                $safeC = mysqli_real_escape_string($ahk_conn, (string)$svc);
                $rC = @mysqli_query($ahk_conn, "SELECT id FROM pricing WHERE service_name='{$safeC}' LIMIT 1");
                if ($rC && mysqli_num_rows($rC) > 0) { $anyExists = true; break; }
            }
            if (!$anyExists) {
                $primary = (string)reset($candidates);
                $safeP = mysqli_real_escape_string($ahk_conn, $primary);
                $safeF = mysqli_real_escape_string($ahk_conn, (string)$fallback);
                @mysqli_query($ahk_conn, "INSERT INTO pricing (service_name, price) VALUES ('{$safeP}','{$safeF}')");
            }
        }
        return ($priceFound !== null) ? $priceFound : (float)$fallback;
    }
}
$_BLUE_DL_CANDIDATES = ['blue_dl_pdf_fee', 'blue_dl_pdf', 'blue_dl', 'Driving_Licence_Pdf_blue', 'Driving_Licence_Pdf'];
$_gp_blue_dl_fee = gp_get_service_fee($ahk_conn ?? $GLOBALS['ahk_conn'], $_BLUE_DL_CANDIDATES, BLUE_DL_PDF_FEE_DEFAULT);

$resdata         = null;
$error_message   = '';
$autoDownloadPdf = '';

$_pdfDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'blue_dl_pdf';
if (!is_dir($_pdfDir)) {
    @mkdir($_pdfDir, 0755, true);
}
@file_put_contents($_pdfDir . DIRECTORY_SEPARATOR . 'index.html', '');

if (!empty($GLOBALS['ahk_conn'] ?? $ahk_conn)) {
    @mysqli_query($GLOBALS['ahk_conn'] ?? $ahk_conn,
        "CREATE TABLE IF NOT EXISTS blue_dl_pdf_requests (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(60) NULL,
            application_no VARCHAR(60) NULL,
            username VARCHAR(60) NULL,
            dlno VARCHAR(40) NULL,
            dob VARCHAR(40) NULL,
            dob_format_used VARCHAR(30) NULL,
            holder_name VARCHAR(160) NULL,
            father_name VARCHAR(160) NULL,
            mobile_number VARCHAR(40) NULL,
            issue_date VARCHAR(40) NULL,
            expiry_date VARCHAR(40) NULL,
            pdf_link VARCHAR(500) NULL,
            pdf_path VARCHAR(500) NULL,
            pdf_size VARCHAR(20) NULL,
            clint_id VARCHAR(60) NULL,
            status VARCHAR(20) DEFAULT '100',
            status_code VARCHAR(20) DEFAULT '100',
            fee VARCHAR(10) NULL,
            old_balance VARCHAR(15) NULL,
            new_balance VARCHAR(15) NULL,
            message TEXT NULL,
            raw_json MEDIUMTEXT NULL,
            date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY (username), KEY (dlno), KEY (date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

function normalizeDobToFormats($dobRaw) {
    $dobRaw = trim((string)$dobRaw);
    if ($dobRaw === '') return [];
    $out = [];
    if (preg_match('#^(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{4})$#', $dobRaw, $m)) {
        $d = str_pad($m[1], 2, '0', STR_PAD_LEFT);
        $mo = str_pad($m[2], 2, '0', STR_PAD_LEFT);
        $y = $m[3];
        $out[] = "$y-$mo-$d";
        $out[] = "$d-$mo-$y";
    } elseif (preg_match('#^(\d{4})[/\-.](\d{1,2})[/\-.](\d{1,2})$#', $dobRaw, $m)) {
        $y = $m[1];
        $mo = str_pad($m[2], 2, '0', STR_PAD_LEFT);
        $d = str_pad($m[3], 2, '0', STR_PAD_LEFT);
        $out[] = "$y-$mo-$d";
        $out[] = "$d-$mo-$y";
    } else {
        $out[] = $dobRaw;
    }
    $out = array_values(array_unique($out));
    return $out;
}

if (isset($_POST['get_blue_dl_pdf']) && !empty($_POST['dlno']) && !empty($_POST['dob'])) {
    $dlnoRaw = trim((string)$_POST['dlno']);
    $dobRaw  = trim((string)$_POST['dob']);
    $dlno    = strtoupper(preg_replace('/\s+/', '', $dlnoRaw));
    $dobList = normalizeDobToFormats($dobRaw);
    if (empty($dobList)) {
        $error_message = "Please enter a valid Date of Birth (YYYY-MM-DD or DD-MM-YYYY).";
    } else {
        $username      = (string)($udata['phone']   ?? '');
        $wallet_amount = (float) ($udata['balance'] ?? 0);
        $clint_id      = (string)($clint ?? '');
        $fee           = $_gp_blue_dl_fee;

        if ($username === '') {
            $error_message = "Session expired. Please login again.";
        } elseif ($wallet_amount < $fee) {
            echo "<script>
                document.addEventListener('DOMContentLoaded', function () {
                    Swal.fire({
                        icon: 'error',
                        title: 'Insufficient Balance',
                        text: 'Your wallet balance is too low to process this request',
                    }).then(() => { window.location.href = 'wallet.php'; });
                });
            </script>";
            exit;
        } else {
            // ============================================================
            // 🔥 PARIPRINT API KEY — DIRECT DB READ (100% Admin Settings Sync)
            // Priority: 1) settings.pariprint_api_key id=1  2) scan settings phk_  3) Global $pariprint_api_key
            // ============================================================
            $pp_send_api_key = '';
            if (is_object($GLOBALS['ahk_conn'] ?? $ahk_conn)) {
                $_cx = $GLOBALS['ahk_conn'] ?? $ahk_conn;
                $_r  = @mysqli_fetch_assoc(@mysqli_query($_cx, "SELECT pariprint_api_key FROM settings WHERE id=1 LIMIT 1"));
                if (is_array($_r) && !empty($_r['pariprint_api_key']) && is_string($_r['pariprint_api_key'])) {
                    $pp_send_api_key = trim((string)$_r['pariprint_api_key']);
                }
                if (strlen($pp_send_api_key) < 40 || substr($pp_send_api_key, 0, 4) !== 'phk_') {
                    $_r2 = @mysqli_fetch_assoc(@mysqli_query($_cx, "SELECT * FROM settings WHERE id=1 LIMIT 1"));
                    if (is_array($_r2)) {
                        foreach ($_r2 as $_cc => $_vv) {
                            if (!is_string($_vv) || strlen($_vv) < 40) continue;
                            if (substr($_vv, 0, 4) === 'phk_' || (strpos(strtolower($_cc), 'pari') !== false && strpos(strtolower($_cc), 'key') !== false)) {
                                $pp_send_api_key = trim($_vv);
                                break;
                            }
                        }
                    }
                }
            }
            if (strlen($pp_send_api_key) < 40) {
                $_ex = (string)($GLOBALS['pariprint_api_key'] ?? $pariprint_api_key ?? '');
                if (strlen($_ex) >= 40) { $pp_send_api_key = $_ex; }
            }
            if (strlen($pp_send_api_key) < 40) { $pp_send_api_key = 'phk_55e219135b215ac76104c0b0e9eacb7ef7dd14fd'; }
            $GLOBALS['pariprint_api_key'] = $pp_send_api_key;
            $pariprint_api_key            = $pp_send_api_key;

            $apiBase = (function_exists('get_central_api_domain') ? rtrim(get_central_api_domain(), '/') : ($CENTRAL_API_BASE ?? 'https://pariprint.in'));
            $pp_key  = $pp_send_api_key;

            $pdfExtract = function($node) use (&$pdfExtract) {
                if (!is_array($node)) return null;
                $keys = ['pdf_link','pdf_url','download_link','download_url','pdf','url','link','pdf_base64','base64','file','base64_pdf','file_content','data_url','pdf_data','pdfcontent'];
                foreach ($keys as $k) {
                    if (isset($node[$k]) && is_string($node[$k]) && trim($node[$k]) !== '') {
                        return trim($node[$k]);
                    }
                }
                foreach ($node as $k => $v) {
                    if (is_array($v)) {
                        $r = $pdfExtract($v);
                        if ($r !== null) return $r;
                    }
                }
                return null;
            };

            $fieldExtract = function($node, $keys) use (&$fieldExtract) {
                if (!is_array($node)) return '';
                foreach ($keys as $k) {
                    if (isset($node[$k]) && is_string($node[$k]) && trim($node[$k]) !== '') {
                        return trim($node[$k]);
                    }
                }
                foreach ($node as $k => $v) {
                    if (is_array($v)) {
                        $r = $fieldExtract($v, $keys);
                        if ($r !== '') return $r;
                    }
                }
                return '';
            };

            $json          = null;
            $dobUsed       = '';
            $lastErrorMsg  = '';
            $successFormat = -1;
            $lastErrNetwork = false;

            for ($i = 0; $i < count($dobList); $i++) {
                $dob = $dobList[$i];
                $url = $apiBase . "/api-proxy.php?" . http_build_query([
                    'slug'   => 'blue-dl-pdf',
                    'apikey' => $pp_key,
                    'dlno'   => $dlno,
                    'dob'    => $dob,
                ]);
                $curl = curl_init();
                curl_setopt_array($curl, [
                    CURLOPT_URL            => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 90,
                    CURLOPT_CONNECTTIMEOUT => 20,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTPGET        => true,
                    CURLOPT_USERAGENT      => 'goodprintportal.in-BlueDLPdf/1.0',
                    CURLOPT_HTTPHEADER     => [
                        "cache-control: no-cache",
                        "Accept: application/json"
                    ],
                ]);
                $response = curl_exec($curl);
                $err      = curl_error($curl);
                curl_close($curl);

                if ($err) {
                    $lastErrNetwork = true;
                    $lastErrorMsg   = "Network error. Please try again after some time.";
                    continue;
                }
                $j = json_decode($response, true);
                if (!is_array($j)) {
                    $lastErrorMsg = "Server temporarily unavailable. Please try again after some time.";
                    continue;
                }

                $statusVal    = $j['status'] ?? null;
                $statusMsg    = (string)($j['statusMessage'] ?? $j['msg'] ?? $j['message'] ?? '');
                $isSuccessFmt = false;
                if ($statusVal !== null) {
                    $s = (string)$statusVal;
                    if ($s === '1' || $s === '100' || $s === '200' || $s === 'true' || $s === 'success' || $s === 'ok' || $s === 'Completed') {
                        $isSuccessFmt = true;
                    }
                }
                if ($statusVal === true || $statusVal === 1 || $statusVal === 100 || $statusVal === 200) {
                    $isSuccessFmt = true;
                }
                if (isset($j['status_code']) && (string)$j['status_code'] === '100') { $isSuccessFmt = true; }
                if (isset($j['response_code']) && (string)$j['response_code'] === '200') { $isSuccessFmt = true; }
                if (isset($j['code']) && (string)$j['code'] === '200') { $isSuccessFmt = true; }
                if (strcasecmp($statusMsg, 'success') === 0) { $isSuccessFmt = true; }

                $pdfRaw = $pdfExtract($j);
                if (!$isSuccessFmt && is_string($pdfRaw) && $pdfRaw !== '') {
                    $trimmed = trim($pdfRaw);
                    if (preg_match('%^https?://%i', $trimmed) || (base64_decode($trimmed, true) !== false && strpos(base64_decode(preg_replace('/^data:[^;]+;base64,/i','',$trimmed), true) ?: '', '%PDF') === 0)) {
                        $isSuccessFmt = true;
                    }
                }
                if ($isSuccessFmt) {
                    $json          = $j;
                    $dobUsed       = $dob;
                    $successFormat = $i;
                    break;
                } else {
                    $lastErrorMsg = (string)($j['message'] ?? $j['msg'] ?? $j['statusMessage'] ?? $j['error'] ?? 'Blue DL PDF not found. Please check DL No and DOB.');
                }
            }

            if ($lastErrNetwork && !is_array($json)) {
                $error_message = $lastErrorMsg;
            } elseif (!is_array($json)) {
                if ($lastErrorMsg !== '' && stripos($lastErrorMsg, 'found') === false
                                       && stripos($lastErrorMsg, 'success') === false
                                       && stripos($lastErrorMsg, 'valid') === false
                                       && stripos($lastErrorMsg, 'available') === false) {
                    $error_message = $lastErrorMsg;
                } else {
                    $error_message = "Blue DL PDF not found. Please check DL No and Date of Birth (both YYYY-MM-DD and DD-MM-YYYY formats tried).";
                }
            } else {
                $statusVal = $json['status'] ?? null;
                $isSuccess = false;
                if ($statusVal !== null) {
                    $s = (string)$statusVal;
                    if ($s === '1' || $s === '100' || $s === '200' || $s === 'true' || $s === 'success' || $s === 'ok' || $s === 'Completed') {
                        $isSuccess = true;
                    }
                }
                if ($statusVal === true || $statusVal === 1 || $statusVal === 100 || $statusVal === 200) { $isSuccess = true; }
                if (isset($json['status_code']) && (string)$json['status_code'] === '100') { $isSuccess = true; }
                if (isset($json['response_code']) && (string)$json['response_code'] === '200') { $isSuccess = true; }
                if (isset($json['code']) && (string)$json['code'] === '200') { $isSuccess = true; }
                if (isset($json['statusMessage']) && strcasecmp((string)$json['statusMessage'], 'success') === 0) { $isSuccess = true; }

                $payload = [];
                if (isset($json['data']) && is_array($json['data']) && !empty($json['data'])) {
                    $payload = $json['data'];
                } elseif (isset($json['result']) && is_array($json['result']) && !empty($json['result'])) {
                    $payload = $json['result'];
                } elseif (isset($json['response']) && is_array($json['response']) && !empty($json['response'])) {
                    $payload = $json['response'];
                } else {
                    $payload = $json;
                }

                $pdfRaw     = $pdfExtract($payload);
                $holderName = $fieldExtract($payload, ['holder_name','name','applicant_name','owner_name','dl_holder','owner','full_name','customer_name']);
                $fatherName = $fieldExtract($payload, ['father_name','fathername','guardian_name','guardian','father','s/o','d/o','h/o','w/o','parent_name']);
                $mobileNum  = $fieldExtract($payload, ['mobile_number','mobile','mobileNo','phone','phone_number','contact','contact_number']);
                $issueDate  = $fieldExtract($payload, ['issue_date','date_of_issue','issued_on','issuedate','doi']);
                $expiryDate = $fieldExtract($payload, ['expiry_date','date_of_expiry','expires_on','expirydate','valid_till','valid_upto','doe']);
                $pdfLink    = '';
                $pdfLocal   = '';
                $pdfSize    = '';

                if (is_string($pdfRaw) && $pdfRaw !== '') {
                    $trimmed = trim($pdfRaw);
                    if (preg_match('%^https?://%i', $trimmed)) {
                        $pdfLink = $trimmed;
                    } else {
                        $clean = $trimmed;
                        if (preg_match('/^data:application\/pdf;base64,(.*)$/is', $trimmed, $m)) {
                            $clean = $m[1];
                        } elseif (preg_match('/^data:[^;]+;base64,(.*)$/is', $trimmed, $m)) {
                            $clean = $m[1];
                        }
                        $decoded = base64_decode($clean, true);
                        if ($decoded !== false && $decoded !== '' && strpos($decoded, '%PDF') === 0) {
                            $fname = 'blue_dl_' . $dlno . '_' . date('Ymd_His') . '_' . rand(100,999) . '.pdf';
                            $fpath = $_pdfDir . DIRECTORY_SEPARATOR . $fname;
                            $ok = @file_put_contents($fpath, $decoded);
                            if ($ok) {
                                $pdfSize = number_format($ok / 1024, 1) . ' KB';
                                $pdfLocal = 'uploads/blue_dl_pdf/' . $fname;
                            }
                        }
                    }
                }

                $apiMsg = (string)($json['message'] ?? $json['msg'] ?? $json['statusMessage'] ?? $json['error'] ?? 'Request successful.');

                if (!$isSuccess && ($pdfLink !== '' || $pdfLocal !== '')) {
                    $isSuccess = true;
                }

                if ($isSuccess && ($pdfLink !== '' || $pdfLocal !== '')) {
                    $new_balance    = $wallet_amount - $fee;
                    $application_no = 'BLDLPDF' . rand(100000, 999999);
                    $clientIp       = (string)($_SERVER['REMOTE_ADDR'] ?? '');
                    $rawJsonSave    = function_exists('json_encode') ? json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';

                    $finalPdfUrl = '';
                    if ($pdfLocal !== '') {
                        $finalPdfUrl = $pdfLocal;
                    } else {
                        $finalPdfUrl = $pdfLink;
                    }
                    $autoDownloadPdf = $finalPdfUrl;

                    mysqli_begin_transaction($ahk_conn);

                    try {
                        $safeUser = mysqli_real_escape_string($ahk_conn, $username);
                        $safeFee  = mysqli_real_escape_string($ahk_conn, (string)$fee);
                        mysqli_query($ahk_conn, "UPDATE users SET balance = balance - {$safeFee} WHERE phone = '{$safeUser}' LIMIT 1");

                        mysqli_query($ahk_conn,
                            "INSERT INTO wallethistory (userid, amount, balance, purpose, status, type)
                             VALUES ('" . mysqli_real_escape_string($ahk_conn, $username) . "',
                                     '" . mysqli_real_escape_string($ahk_conn, (string)$fee) . "',
                                     '" . mysqli_real_escape_string($ahk_conn, (string)$new_balance) . "',
                                     'Blue DL PDF Download', '1', 'Debit')");

                        $stmt = mysqli_prepare($ahk_conn,
                            "INSERT INTO blue_dl_pdf_requests (
                                ip, application_no, username, dlno, dob, dob_format_used, holder_name, father_name,
                                mobile_number, issue_date, expiry_date,
                                pdf_link, pdf_path, pdf_size, clint_id,
                                status, status_code, fee, old_balance, new_balance, message, raw_json
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '100', '100', ?, ?, ?, ?, ?)");

                        if ($stmt) {
                            $stmtFee    = (string)$fee;
                            $stmtOldBal = (string)$wallet_amount;
                            $stmtNewBal = (string)$new_balance;
                            $stmtMsg    = $apiMsg;
                            if ($stmtMsg === '') { $stmtMsg = 'Blue DL PDF downloaded successfully.'; }
                            mysqli_stmt_bind_param($stmt, "sssssssssssssss",
                                $clientIp, $application_no, $username, $dlno, $dobRaw, $dobUsed, $holderName, $fatherName,
                                $mobileNum, $issueDate, $expiryDate,
                                $pdfLink, $pdfLocal, $pdfSize, $clint_id,
                                $stmtFee, $stmtOldBal, $stmtNewBal, $stmtMsg, $rawJsonSave);
                            mysqli_stmt_execute($stmt);
                            mysqli_stmt_close($stmt);
                        } else {
                            $ipE  = mysqli_real_escape_string($ahk_conn, $clientIp);
                            $apE  = mysqli_real_escape_string($ahk_conn, $application_no);
                            $usE  = mysqli_real_escape_string($ahk_conn, $username);
                            $dlE  = mysqli_real_escape_string($ahk_conn, $dlno);
                            $dbE  = mysqli_real_escape_string($ahk_conn, $dobRaw);
                            $dfE  = mysqli_real_escape_string($ahk_conn, $dobUsed);
                            $hnE  = mysqli_real_escape_string($ahk_conn, $holderName);
                            $fnE  = mysqli_real_escape_string($ahk_conn, $fatherName);
                            $mnE  = mysqli_real_escape_string($ahk_conn, $mobileNum);
                            $isE  = mysqli_real_escape_string($ahk_conn, $issueDate);
                            $exE  = mysqli_real_escape_string($ahk_conn, $expiryDate);
                            $plE  = mysqli_real_escape_string($ahk_conn, $pdfLink);
                            $ppE  = mysqli_real_escape_string($ahk_conn, $pdfLocal);
                            $szE  = mysqli_real_escape_string($ahk_conn, $pdfSize);
                            $clE  = mysqli_real_escape_string($ahk_conn, $clint_id);
                            $feE  = mysqli_real_escape_string($ahk_conn, (string)$fee);
                            $obE  = mysqli_real_escape_string($ahk_conn, (string)$wallet_amount);
                            $nbE  = mysqli_real_escape_string($ahk_conn, (string)$new_balance);
                            $msE  = mysqli_real_escape_string($ahk_conn, $apiMsg === '' ? 'Blue DL PDF downloaded successfully.' : $apiMsg);
                            $rjE  = mysqli_real_escape_string($ahk_conn, $rawJsonSave);
                            mysqli_query($ahk_conn,
                                "INSERT INTO blue_dl_pdf_requests (ip, application_no, username, dlno, dob, dob_format_used, holder_name, father_name, mobile_number, issue_date, expiry_date, pdf_link, pdf_path, pdf_size, clint_id, status, status_code, fee, old_balance, new_balance, message, raw_json)
                                 VALUES ('{$ipE}','{$apE}','{$usE}','{$dlE}','{$dbE}','{$dfE}','{$hnE}','{$fnE}','{$mnE}','{$isE}','{$exE}','{$plE}','{$ppE}','{$szE}','{$clE}','100','100','{$feE}','{$obE}','{$nbE}','{$msE}','{$rjE}')");
                        }

                        mysqli_commit($ahk_conn);

                        $resdata = [
                            'statusCode'     => 100,
                            'dlno'           => $dlno,
                            'dob'            => $dobRaw,
                            'dob_used'       => $dobUsed,
                            'holder_name'    => $holderName,
                            'father_name'    => $fatherName,
                            'mobile_number'  => $mobileNum,
                            'issue_date'     => $issueDate,
                            'expiry_date'    => $expiryDate,
                            'pdf_link'       => $pdfLink,
                            'pdf_path'       => $pdfLocal,
                            'pdf_size'       => $pdfSize,
                            'final_pdf_url'  => $finalPdfUrl,
                            'application_no' => $application_no,
                        ];

                    } catch (Exception $e) {
                        mysqli_rollback($ahk_conn);
                        $error_message = "Unable to complete request. Please try again.";
                        $autoDownloadPdf = '';
                    }
                } else {
                    if ($apiMsg !== '' && stripos($apiMsg, 'found') === false
                                      && stripos($apiMsg, 'success') === false
                                      && stripos($apiMsg, 'valid') === false
                                      && stripos($apiMsg, 'available') === false) {
                        $error_message = $apiMsg;
                    } else {
                        $error_message = "Blue DL PDF not found. Please check DL No and Date of Birth (both YYYY-MM-DD and DD-MM-YYYY formats tried).";
                    }
                }
            }
        }
    }
}
?>

<div class="page-wrapper">
    <div class="page-content">
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Vehicle Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">Blue DL PDF</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="vahicle_card_service" class="btn btn-outline-primary">
                    <i class="bi bi-grid-3x3-gap me-1"></i> All Vehicle Services
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        <div class="container-fluid pt-4 px-4">
            <div class="row">
                <div class="col-lg-5 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <h3 class="card-title text-dark text-center"><strong>Blue DL PDF (Light Blue Format)</strong></h3>
                            <p class="text-center text-muted small mb-3">DL Number + DOB (YYYY-MM-DD ya DD-MM-YYYY — dono auto try) → Blue Driving Licence PDF</p>
                        </div>
                        <div class="card-body">
                            <form action="" method="post" id="blue_dl_form" novalidate>
                                <div class="mb-3">
                                    <label for="dlno" class="form-label">Driving Licence Number (DL No) <span class="text-danger">*</span></label>
                                    <input type="text" required class="form-control" name="dlno" id="dlno"
                                           placeholder="EX: DL0420110123456"
                                           maxlength="20" style="text-transform: uppercase; letter-spacing: 1px;"
                                           value="<?= isset($_POST['dlno']) ? htmlspecialchars(strtoupper(trim((string)$_POST['dlno']))) : '' ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="dob" class="form-label">Date of Birth (DOB) <span class="text-danger">*</span></label>
                                    <div class="row g-2">
                                        <div class="col-md-7">
                                            <input type="text" required class="form-control" name="dob" id="dob"
                                                   placeholder="1990-08-15 or 15-08-1990"
                                                   value="<?= isset($_POST['dob']) ? htmlspecialchars(trim((string)$_POST['dob'])) : '' ?>">
                                        </div>
                                        <div class="col-md-5">
                                            <input type="date" class="form-control" id="dob_date"
                                                   onchange="document.getElementById('dob').value = this.value;">
                                        </div>
                                    </div>
                                    <div class="form-text">Dono formats accepted: <strong>YYYY-MM-DD</strong> (Docs) ya <strong>DD-MM-YYYY</strong>. System backend dono try karega automatically.</div>
                                </div>
                                <button type="submit" name="get_blue_dl_pdf" class="btn btn-primary w-100">
                                    <i class="bi bi-filetype-pdf me-1"></i> Generate &amp; Download Blue DL PDF
                                </button>

                                <div class="mt-3 text-center fee-container">
                                    <h5 class="text-warning">
                                        Application Fee: ₹<?= htmlspecialchars(number_format($_gp_blue_dl_fee, 2)); ?>
                                    </h5>
                                </div>

                                <?php if ($error_message): ?>
                                    <div class="alert alert-danger mt-3" role="alert">
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($error_message); ?>
                                    </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <?php if (!empty($resdata) && $resdata['statusCode'] == 100): ?>
                        <div class="card shadow-sm border-0" style="background-color: #f8f9fa;" id="printSection">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="me-2 text-info"><i class="bi bi-file-earmark-pdf-fill fs-3"></i></div>
                                    <div>
                                        <h5 class="mb-0 text-info">Blue DL PDF Ready!</h5>
                                        <small class="text-muted">Txn ID: <?= htmlspecialchars($resdata['application_no']); ?> · DOB used: <?= htmlspecialchars($resdata['dob_used']); ?></small>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped align-middle mb-0">
                                        <tbody>
                                            <?php if (!empty($resdata['dlno'])): ?>
                                                <tr>
                                                    <th style="width:40%; background:#eef2ff;">DL Number</th>
                                                    <td><strong class="text-primary" style="letter-spacing:1px;"><?= htmlspecialchars($resdata['dlno']); ?></strong></td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($resdata['dob'])): ?>
                                                <tr>
                                                    <th style="background:#eef2ff;">DOB (Input)</th>
                                                    <td><?= htmlspecialchars($resdata['dob']); ?></td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($resdata['holder_name'])): ?>
                                                <tr>
                                                    <th style="background:#eef2ff;">Holder Name</th>
                                                    <td><strong class="text-success"><?= htmlspecialchars($resdata['holder_name']); ?></strong></td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($resdata['father_name'])): ?>
                                                <tr>
                                                    <th style="background:#eef2ff;">Father / Guardian Name</th>
                                                    <td><?= htmlspecialchars($resdata['father_name']); ?></td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($resdata['mobile_number'])): ?>
                                                <tr>
                                                    <th style="background:#eef2ff;">Mobile Number</th>
                                                    <td><strong style="letter-spacing:1px;"><?= htmlspecialchars($resdata['mobile_number']); ?></strong></td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($resdata['issue_date'])): ?>
                                                <tr>
                                                    <th style="background:#eef2ff;">Issue Date</th>
                                                    <td><?= htmlspecialchars($resdata['issue_date']); ?></td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($resdata['expiry_date'])): ?>
                                                <tr>
                                                    <th style="background:#eef2ff;">Expiry / Valid Till</th>
                                                    <td><span class="badge rounded-pill bg-success px-3 py-2 text-white"><?= htmlspecialchars($resdata['expiry_date']); ?></span></td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($resdata['pdf_size'])): ?>
                                                <tr>
                                                    <th style="background:#eef2ff;">PDF Size</th>
                                                    <td><?= htmlspecialchars($resdata['pdf_size']); ?></td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($resdata['final_pdf_url'])): ?>
                                                <tr>
                                                    <th style="background:#eef2ff;">Blue DL Certificate PDF</th>
                                                    <td>
                                                        <a href="<?= htmlspecialchars($resdata['final_pdf_url']); ?>"
                                                           target="_blank" rel="noopener noreferrer"
                                                           class="btn btn-outline-danger btn-sm d-inline-flex align-items-center gap-1">
                                                            <i class="bi bi-download"></i>
                                                            Download PDF
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-4">
                                    <div class="text-muted small">
                                        <i class="bi bi-wallet2 me-1"></i>
                                        Service charge <strong>₹<?= htmlspecialchars(number_format($_gp_blue_dl_fee, 2)); ?></strong> debited from wallet
                                    </div>
                                    <button class="btn btn-primary px-4" onclick="window.location.reload();">
                                        <i class="bi bi-arrow-clockwise me-1"></i> New Request
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($resdata) && $resdata['statusCode'] == 100): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        Swal.fire({
            icon: 'success',
            title: 'Blue DL PDF Ready!',
            text: 'PDF is being downloaded automatically. If not, click Download PDF button.',
            confirmButtonText: 'OK'
        });
        <?php if (!empty($autoDownloadPdf)): ?>
        setTimeout(function () {
            var a = document.createElement('a');
            a.href = <?= json_encode($autoDownloadPdf); ?>;
            a.target = '_blank';
            a.rel = 'noopener noreferrer';
            a.download = 'Blue_DL_<?= isset($resdata['dlno']) ? htmlspecialchars($resdata['dlno']) : 'DL'; ?>_<?= date('dMY'); ?>.pdf';
            document.body.appendChild(a);
            a.click();
            setTimeout(() => document.body.removeChild(a), 500);
        }, 800);
        <?php endif; ?>
    });
    </script>
<?php endif; ?>

    <script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
    <script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
    <script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
    <script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
    <script src="../template/ahkweb/assets/js/app.js"></script>
</body>
</html>
