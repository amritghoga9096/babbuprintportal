<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

date_default_timezone_set("Asia/Kolkata");

global $CENTRAL_API_BASE, $pariprint_api_key, $ahk_conn, $udata, $clint;

define('PAN_FIND_S2_FEE_DEFAULT', 40);

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
$_PANS2_CANDIDATES = ['pan_find_server2_fee', 'instant_pan_find_server_2_fee', 'pan_find_server2', 'instant-pan-find-server-2', 'Pan_To_Aadhaar_find_server2'];
$_gp_pan_s2_fee = gp_get_service_fee($ahk_conn ?? $GLOBALS['ahk_conn'], $_PANS2_CANDIDATES, PAN_FIND_S2_FEE_DEFAULT);

$resdata       = null;
$error_message = '';

if (isset($_POST['find_pan_s2']) && !empty($_POST['uidNumber'])) {
    $uidNumber = trim($_POST['uidNumber']);

    if (!preg_match('/^\d{12}$/', $uidNumber)) {
        $error_message = "Please enter a valid 12-digit Aadhaar Number.";
    } else {
        $username      = (string)($udata['phone']   ?? '');
        $wallet_amount = (float) ($udata['balance'] ?? 0);
        $appliedby     = $username;
        $mobile        = $username;
        $clint_id      = (string)($clint ?? '');
        $fee           = $_gp_pan_s2_fee;

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
                'slug'      => 'instant-pan-find-server-2',
                'apikey'    => $pp_key,
                'uidNumber' => $uidNumber
            ]);

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 60,
                CURLOPT_CONNECTTIMEOUT => 20,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPGET        => true,
                CURLOPT_USERAGENT      => 'goodprintportal.in-PanFindServer2/1.0',
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
                        if ($s === '1' || $s === '200' || $s === 'true' || $s === 'success' || $s === 'ok' || $s === '100') {
                            $isSuccess = true;
                        }
                    }
                    if ($statusVal === true || $statusVal === 1 || $statusVal === 100) {
                        $isSuccess = true;
                    }
                    if (isset($json['status_code']) && (string)$json['status_code'] === '100') { $isSuccess = true; }
                    if (isset($json['response_code']) && (string)$json['response_code'] === '200') { $isSuccess = true; }

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

                    $panNo     = strtoupper(trim((string)($payload['pan_number']      ?? $payload['full_pan_number'] ?? $payload['pan']    ?? '')));
                    $name      = trim((string)($payload['name']                  ?? $payload['full_name']       ?? ''));
                    $dob       = trim((string)($payload['dob']                   ?? $payload['date_of_birth']   ?? ''));
                    $applNo    = trim((string)($payload['application_no']        ?? $payload['application_number'] ?? $payload['acknowledgement_no'] ?? ''));
                    $maskPan   = trim((string)($payload['mask_pan']              ?? ''));
                    $uidBack   = trim((string)($payload['uidNumber']             ?? $payload['aadhaar_number'] ?? $payload['aadharno'] ?? ''));
                    $apiMsg    = (string)($json['message'] ?? $json['msg'] ?? $json['error'] ?? 'Request successful.');

                    if (!$isSuccess && $panNo !== '') {
                        $isSuccess = true;
                    }

                    if ($isSuccess && $panNo !== '') {
                        $new_balance    = $wallet_amount - $fee;
                        $application_no = 'INSPAN2' . rand(100000, 999999);
                        $clientIp       = (string)($_SERVER['REMOTE_ADDR'] ?? '');

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
                                         'Pan Find Server 2 (Aadhaar → PAN)', '1', 'Debit')");

                            $stmt = mysqli_prepare($ahk_conn,
                                "INSERT INTO instantpan_find (
                                    ip, application_no, username, aadhaar_no, pan_no, clint_id,
                                    status, status_code, fee, old_balance, new_balance, message
                                ) VALUES (?, ?, ?, ?, ?, ?, '100', ?, ?, ?, ?, ?)");

                            $statusCode = '100';
                            $stmtFee    = (string)$fee;
                            $stmtOldBal = (string)$wallet_amount;
                            $stmtNewBal = (string)$new_balance;
                            $stmtMsg    = $apiMsg;
                            if ($stmtMsg === '') { $stmtMsg = 'Aadhaar to PAN find successful.'; }

                            mysqli_stmt_bind_param($stmt, "sssssssssss",
                                $clientIp, $application_no, $username, $uidNumber, $panNo, $clint_id,
                                $statusCode, $stmtFee, $stmtOldBal, $stmtNewBal, $stmtMsg);
                            mysqli_stmt_execute($stmt);
                            mysqli_stmt_close($stmt);

                            mysqli_commit($ahk_conn);

                            $resdata = [
                                'statusCode'     => 100,
                                'uidNumber'      => $uidNumber,
                                'pan_number'     => $panNo,
                                'mask_pan'       => $maskPan,
                                'name'           => $name,
                                'dob'            => $dob,
                                'application_no' => $applNo,
                                'instantpan_id'  => $application_no,
                            ];

                        } catch (Exception $e) {
                            mysqli_rollback($ahk_conn);
                            $error_message = "Unable to complete request. Please try again.";
                        }
                    } else {
                        if ($apiMsg !== '' && stripos($apiMsg, 'found') === false
                                          && stripos($apiMsg, 'success') === false
                                          && stripos($apiMsg, 'valid') === false
                                          && stripos($apiMsg, 'available') === false) {
                            $error_message = $apiMsg;
                        } else {
                            $error_message = "PAN number not found for this Aadhaar. Please check and try again.";
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
                <div class="breadcrumb-title pe-3">Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">Pan Find Server 2</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="pan_find_admin_list" class="btn btn-success">
                    <i class="bi bi-list-ul me-1"></i> Pan Find Records
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
                            <h3 class="card-title text-dark text-center"><strong>Aadhaar To Pan Find Instant (Server 2)</strong></h3>
                            <p class="text-center text-muted small mb-3">12-digit Aadhaar (UID) se linked PAN number + details (Server 2 Provider)</p>
                        </div>
                        <div class="card-body">
                            <form action="" method="post" id="pan_find_s2_form" novalidate>
                                <div class="mb-3">
                                    <label for="uidNumber" class="form-label">Aadhaar Number (UID) <span class="text-danger">*</span></label>
                                    <input type="text" required class="form-control" name="uidNumber" id="uidNumber"
                                           placeholder="Enter 12-digit Aadhaar Number"
                                           maxlength="12" pattern="\d{12}" inputmode="numeric"
                                           value="<?= isset($_POST['uidNumber']) ? htmlspecialchars($_POST['uidNumber']) : '' ?>">
                                </div>
                                <button type="submit" name="find_pan_s2" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> Find PAN Number
                                </button>

                                <div class="mt-3 text-center fee-container">
                                    <h5 class="text-warning">
                                        Application Fee: ₹<?= htmlspecialchars(number_format($_gp_pan_s2_fee, 2)); ?>
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
                                    <div class="me-2 text-success"><i class="bi bi-check-circle-fill fs-4"></i></div>
                                    <div>
                                        <h5 class="mb-0 text-success">PAN Details Found (Server 2)</h5>
                                        <small class="text-muted">Txn ID: <?= htmlspecialchars($resdata['instantpan_id']); ?></small>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped align-middle mb-0">
                                        <tbody>
                                            <?php if (!empty($resdata['uidNumber'])): ?>
                                                <tr>
                                                    <th style="width:40%; background:#eef2ff;">Aadhaar Number</th>
                                                    <td><strong class="text-primary" style="letter-spacing:1px;"><?= htmlspecialchars($resdata['uidNumber']); ?></strong></td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($resdata['pan_number'])): ?>
                                                <tr>
                                                    <th style="background:#eef2ff;">PAN Number</th>
                                                    <td><strong class="text-success" style="font-size:1.08rem; letter-spacing:1px;"><?= htmlspecialchars($resdata['pan_number']); ?></strong></td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($resdata['mask_pan'])): ?>
                                                <tr>
                                                    <th style="background:#eef2ff;">Mask PAN</th>
                                                    <td><?= htmlspecialchars($resdata['mask_pan']); ?></td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($resdata['name'])): ?>
                                                <tr>
                                                    <th style="background:#eef2ff;">Full Name</th>
                                                    <td><?= htmlspecialchars($resdata['name']); ?></td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($resdata['dob'])): ?>
                                                <tr>
                                                    <th style="background:#eef2ff;">Date of Birth</th>
                                                    <td><?= htmlspecialchars($resdata['dob']); ?></td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($resdata['application_no'])): ?>
                                                <tr>
                                                    <th style="background:#eef2ff;">Application No</th>
                                                    <td><span class="text-muted small"><?= htmlspecialchars($resdata['application_no']); ?></span></td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-4">
                                    <div class="text-muted small">
                                        <i class="bi bi-wallet2 me-1"></i>
                                        Service charge <strong>₹<?= htmlspecialchars(number_format($_gp_pan_s2_fee, 2)); ?></strong> debited from wallet
                                    </div>
                                    <button class="btn btn-primary px-4" onclick="printBill()">
                                        <i class="bi bi-printer me-1"></i> Print Receipt
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
                title: 'PAN found (Server 2)',
                text: 'PAN: <?= htmlspecialchars($resdata['pan_number']); ?>',
                confirmButtonText: 'OK'
            });
        });
    </script>
<?php endif; ?>

<script>
function printBill() {
    var printContents = document.getElementById('printSection').innerHTML;
    var originalContents = document.body.innerHTML;
    document.body.innerHTML = printContents;
    window.print();
    document.body.innerHTML = originalContents;
}
</script>

    <script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
    <script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
    <script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
    <script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
    <script src="../template/ahkweb/assets/js/app.js"></script>
</body>
</html>
