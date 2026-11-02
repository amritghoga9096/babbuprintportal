<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

date_default_timezone_set("Asia/Kolkata");

global $CENTRAL_API_BASE, $pariprint_api_key, $ahk_conn, $udata, $clint;

define('RATION_PDF_FEE_DEFAULT', 50);

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
$_RATION_CANDIDATES = ['adhar_to_ration_pdf_fee', 'aadhaar_to_ration_pdf', 'adhar_to_ration_pdf', 'ration_pdf_flx_print_fee', 'aadhar-to-ration-pdf'];
$_gp_ration_fee = gp_get_service_fee($ahk_conn ?? $GLOBALS['ahk_conn'], $_RATION_CANDIDATES, RATION_PDF_FEE_DEFAULT);

$resdata         = null;
$error_message   = '';
$autoDownloadPdf = '';

$_pdfDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'ration_pdf';
if (!is_dir($_pdfDir)) {
    @mkdir($_pdfDir, 0755, true);
}
@file_put_contents($_pdfDir . DIRECTORY_SEPARATOR . 'index.html', '');

if (!empty($GLOBALS['ahk_conn'] ?? $ahk_conn)) {
    @mysqli_query($GLOBALS['ahk_conn'] ?? $ahk_conn,
        "CREATE TABLE IF NOT EXISTS ration_pdf_requests (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(60) NULL,
            application_no VARCHAR(60) NULL,
            username VARCHAR(60) NULL,
            uidNumber VARCHAR(20) NULL,
            aadhar_number VARCHAR(20) NULL,
            pdf_type VARCHAR(20) NULL,
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
            KEY (username), KEY (uidNumber), KEY (date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

if (isset($_POST['get_ration_pdf']) && !empty($_POST['uidNumber'])) {
    $uidNumber = trim($_POST['uidNumber']);
    $pdfType   = trim((string)($_POST['type'] ?? 'Default'));
    if ($pdfType === '') { $pdfType = 'Default'; }
    if (!in_array($pdfType, ['Default','New','Old'], true)) { $pdfType = 'Default'; }

    if (!preg_match('/^\d{12}$/', $uidNumber)) {
        $error_message = "Please enter a valid 12-digit Aadhaar Number.";
    } else {
        $username      = (string)($udata['phone']   ?? '');
        $wallet_amount = (float) ($udata['balance'] ?? 0);
        $appliedby     = $username;
        $mobile        = $username;
        $clint_id      = (string)($clint ?? '');
        $fee           = $_gp_ration_fee;

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
            // Priority: 1) Exact settings.pariprint_api_key column  2) Scan settings for phk_  3) Global $pariprint_api_key
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

            $url = $apiBase . "/api-proxy.php?" . http_build_query([
                'slug'      => 'aadhar-to-ration-pdf',
                'api_key'   => $pp_key,
                'uidNumber' => $uidNumber,
                'type'      => $pdfType
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
                CURLOPT_USERAGENT      => 'goodprintportal.in-RationPdf/1.0',
                CURLOPT_HTTPHEADER     => [
                    "cache-control: no-cache",
                    "Accept: application/json"
                ],
            ]);

            $response = curl_exec($curl);
            $err      = curl_error($curl);
            curl_close($curl);

            if ($err) {
                $error_message = "Network error. Please try again after some time.";
            } else {
                $json = json_decode($response, true);

                if (!is_array($json)) {
                    $error_message = "Server temporarily unavailable. Please try again after some time.";
                } else {
                    $statusVal = $json['status'] ?? null;
                    $isSuccess = false;

                    if ($statusVal !== null) {
                        $s = (string)$statusVal;
                        if ($s === '1' || $s === '200' || $s === 'true' || $s === 'success' || $s === 'ok' || $s === '100' || $s === 'Completed') {
                            $isSuccess = true;
                        }
                    }
                    if ($statusVal === true || $statusVal === 1 || $statusVal === 100 || $statusVal === 200) {
                        $isSuccess = true;
                    }
                    if (isset($json['status_code']) && (string)$json['status_code'] === '100') { $isSuccess = true; }
                    if (isset($json['response_code']) && (string)$json['response_code'] === '200') { $isSuccess = true; }
                    if (isset($json['code']) && (string)$json['code'] === '200') { $isSuccess = true; }

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

                    $pdfRaw    = $pdfExtract($payload);
                    $hasBase64 = false;
                    $pdfLink   = '';
                    $pdfLocal  = '';
                    $pdfSize   = '';

                    if (is_string($pdfRaw) && $pdfRaw !== '') {
                        $trimmed = trim($pdfRaw);
                        if (preg_match('%^https?://%i', $trimmed)) {
                            $pdfLink = $trimmed;
                        } else {
                            $clean = $trimmed;
                            if (preg_match('/^data:application\/pdf;base64,(.*)$/is', $trimmed, $m)) {
                                $clean = $m[1];
                                $hasBase64 = true;
                            } elseif (preg_match('/^data:[^;]+;base64,(.*)$/is', $trimmed, $m)) {
                                $clean = $m[1];
                            } elseif (base64_decode($trimmed, true) !== false && preg_match('%^[A-Za-z0-9+/=\r\n]+$%', $trimmed)) {
                                $hasBase64 = true;
                            }
                            $decoded = base64_decode($clean, true);
                            if ($decoded !== false && $decoded !== '' && strpos($decoded, '%PDF') === 0) {
                                $hasBase64 = true;
                                $fname = 'ration_' . $uidNumber . '_' . date('Ymd_His') . '_' . rand(100,999) . '.pdf';
                                $fpath = $_pdfDir . DIRECTORY_SEPARATOR . $fname;
                                $ok = @file_put_contents($fpath, $decoded);
                                if ($ok) {
                                    $pdfSize = number_format($ok / 1024, 1) . ' KB';
                                    $pdfLocal = 'uploads/ration_pdf/' . $fname;
                                }
                            }
                        }
                    }

                    $apiMsg = (string)($json['message'] ?? $json['msg'] ?? $json['error'] ?? 'Request successful.');

                    if (!$isSuccess && ($pdfLink !== '' || $pdfLocal !== '')) {
                        $isSuccess = true;
                    }

                    if ($isSuccess && ($pdfLink !== '' || $pdfLocal !== '')) {
                        $new_balance    = $wallet_amount - $fee;
                        $application_no = 'RATPDEF' . rand(100000, 999999);
                        $clientIp       = (string)($_SERVER['REMOTE_ADDR'] ?? '');
                        $rawJsonSave    = function_exists('json_encode') ? json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';

                        $finalPdfUrl = '';
                        if ($pdfLocal !== '') {
                            $finalPdfUrl = $pdfLocal;
                        } else {
                            $finalPdfUrl = $pdfLink;
                        }

                        if ($pdfLocal !== '') {
                            $autoDownloadPdf = $pdfLocal;
                        } else {
                            $autoDownloadPdf = $pdfLink;
                        }

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
                                         'Adhar to Ration Pdf Download', '1', 'Debit')");

                            $stmt = mysqli_prepare($ahk_conn,
                                "INSERT INTO ration_pdf_requests (
                                    ip, application_no, username, uidNumber, aadhar_number, pdf_type, pdf_link, pdf_path, pdf_size, clint_id,
                                    status, status_code, fee, old_balance, new_balance, message, raw_json
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '100', '100', ?, ?, ?, ?, ?)");

                            if ($stmt) {
                                $stmtFee    = (string)$fee;
                                $stmtOldBal = (string)$wallet_amount;
                                $stmtNewBal = (string)$new_balance;
                                $stmtMsg    = $apiMsg;
                                if ($stmtMsg === '') { $stmtMsg = 'Ration card PDF downloaded successfully.'; }

                                mysqli_stmt_bind_param($stmt, "ssssssssssss",
                                    $clientIp, $application_no, $username, $uidNumber, $uidNumber, $pdfType, $pdfLink, $pdfLocal, $pdfSize, $clint_id,
                                    $stmtFee, $stmtOldBal, $stmtNewBal, $stmtMsg, $rawJsonSave);
                                mysqli_stmt_execute($stmt);
                                mysqli_stmt_close($stmt);
                            } else {
                                $ipE  = mysqli_real_escape_string($ahk_conn, $clientIp);
                                $apE  = mysqli_real_escape_string($ahk_conn, $application_no);
                                $usE  = mysqli_real_escape_string($ahk_conn, $username);
                                $uiE  = mysqli_real_escape_string($ahk_conn, $uidNumber);
                                $tyE  = mysqli_real_escape_string($ahk_conn, $pdfType);
                                $plE  = mysqli_real_escape_string($ahk_conn, $pdfLink);
                                $ppE  = mysqli_real_escape_string($ahk_conn, $pdfLocal);
                                $szE  = mysqli_real_escape_string($ahk_conn, $pdfSize);
                                $clE  = mysqli_real_escape_string($ahk_conn, $clint_id);
                                $feE  = mysqli_real_escape_string($ahk_conn, (string)$fee);
                                $obE  = mysqli_real_escape_string($ahk_conn, (string)$wallet_amount);
                                $nbE  = mysqli_real_escape_string($ahk_conn, (string)$new_balance);
                                $msE  = mysqli_real_escape_string($ahk_conn, $apiMsg === '' ? 'Ration card PDF downloaded successfully.' : $apiMsg);
                                $rjE  = mysqli_real_escape_string($ahk_conn, $rawJsonSave);
                                mysqli_query($ahk_conn,
                                    "INSERT INTO ration_pdf_requests (ip, application_no, username, uidNumber, aadhar_number, pdf_type, pdf_link, pdf_path, pdf_size, clint_id, status, status_code, fee, old_balance, new_balance, message, raw_json)
                                     VALUES ('{$ipE}','{$apE}','{$usE}','{$uiE}','{$uiE}','{$tyE}','{$plE}','{$ppE}','{$szE}','{$clE}','100','100','{$feE}','{$obE}','{$nbE}','{$msE}','{$rjE}')");
                            }

                            mysqli_commit($ahk_conn);

                            $resdata = [
                                'statusCode'     => 100,
                                'uidNumber'      => $uidNumber,
                                'aadhar_number'  => $uidNumber,
                                'pdf_type'       => $pdfType,
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
                            $error_message = "Ration card PDF not found for this Aadhaar. Please check and try again.";
                        }
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
                <div class="breadcrumb-title pe-3">Ration Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">Adhar to Ration Pdf</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="Ration_card_service" class="btn btn-outline-primary">
                    <i class="bi bi-grid-3x3-gap me-1"></i> All Ration Services
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
                            <h3 class="card-title text-dark text-center"><strong>Aadhaar to Ration Card PDF</strong></h3>
                            <p class="text-center text-muted small mb-3">12-digit Aadhaar (UID) se instant Ration PDF download — New / Old / Default types</p>
                        </div>
                        <div class="card-body">
                            <form action="" method="post" id="ration_pdf_form" novalidate>
                                <div class="mb-3">
                                    <label for="uidNumber" class="form-label">Aadhaar Number (UID) <span class="text-danger">*</span></label>
                                    <input type="text" required class="form-control" name="uidNumber" id="uidNumber"
                                           placeholder="Enter 12-digit Aadhaar Number"
                                           maxlength="12" pattern="\d{12}" inputmode="numeric"
                                           value="<?= isset($_POST['uidNumber']) ? htmlspecialchars($_POST['uidNumber']) : '' ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="type" class="form-label">PDF Type</label>
                                    <select class="form-select" name="type" id="type">
                                        <option value="Default"  <?= (isset($_POST['type']) && $_POST['type'] === 'Default')  ? 'selected' : ''; ?>>Default (Recommended)</option>
                                        <option value="New"      <?= (isset($_POST['type']) && $_POST['type'] === 'New')      ? 'selected' : ''; ?>>New</option>
                                        <option value="Old"      <?= (isset($_POST['type']) && $_POST['type'] === 'Old')      ? 'selected' : ''; ?>>Old</option>
                                    </select>
                                </div>
                                <button type="submit" name="get_ration_pdf" class="btn btn-primary w-100">
                                    <i class="bi bi-filetype-pdf me-1"></i> Generate & Download Ration PDF
                                </button>

                                <div class="mt-3 text-center fee-container">
                                    <h5 class="text-warning">
                                        Application Fee: ₹<?= htmlspecialchars(number_format($_gp_ration_fee, 2)); ?>
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
                                    <div class="me-2 text-success"><i class="bi bi-file-earmark-pdf-fill fs-3"></i></div>
                                    <div>
                                        <h5 class="mb-0 text-success">Ration PDF Generated Successfully</h5>
                                        <small class="text-muted">Txn ID: <?= htmlspecialchars($resdata['application_no']); ?></small>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped align-middle mb-0">
                                        <tbody>
                                            <?php if (!empty($resdata['aadhar_number'])): ?>
                                                <tr>
                                                    <th style="width:40%; background:#eef2ff;">Aadhaar Number</th>
                                                    <td><strong class="text-primary" style="letter-spacing:1px;"><?= htmlspecialchars($resdata['aadhar_number']); ?></strong></td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($resdata['pdf_type'])): ?>
                                                <tr>
                                                    <th style="background:#eef2ff;">PDF Type</th>
                                                    <td><span class="badge rounded-pill bg-info text-white px-3 py-2"><?= htmlspecialchars($resdata['pdf_type']); ?></span></td>
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
                                                    <th style="background:#eef2ff;">Ration PDF</th>
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
                                        Service charge <strong>₹<?= htmlspecialchars(number_format($_gp_ration_fee, 2)); ?></strong> debited from wallet
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
            title: 'Ration PDF Ready!',
            text: 'PDF is being downloaded automatically. If not, click Download PDF button.',
            confirmButtonText: 'OK'
        });
        <?php if (!empty($autoDownloadPdf)): ?>
        setTimeout(function () {
            var a = document.createElement('a');
            a.href = <?= json_encode($autoDownloadPdf); ?>;
            a.target = '_blank';
            a.rel = 'noopener noreferrer';
            a.download = 'Ration_Card_<?= isset($resdata['aadhar_number']) ? htmlspecialchars($resdata['aadhar_number']) : 'PDF'; ?>_<?= date('dMY'); ?>.pdf';
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
