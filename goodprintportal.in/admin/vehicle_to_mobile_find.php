<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

date_default_timezone_set("Asia/Kolkata");

global $CENTRAL_API_BASE, $pariprint_api_key, $ahk_conn, $udata, $clint;

define('VEHICLE_MOBILE_FEE_DEFAULT', 70);

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
$_VM_CANDIDATES = ['vehicle_to_mobile_find', 'vehical_to_mobile_find', 'instant_vehicle_to_mobile_find', 'rc_to_mobile', 'rc_vehical_to_mobile', 'vehicle_mobile_fee'];
$_gp_vm_fee = gp_get_service_fee($ahk_conn ?? $GLOBALS['ahk_conn'], $_VM_CANDIDATES, VEHICLE_MOBILE_FEE_DEFAULT);

$resdata       = null;
$error_message = '';

if (isset($_POST['find_vehicle_mobile'])) {
    $rcNumber = strtoupper(trim($_POST['rcNumber']));

    if ($rcNumber === '' || strlen($rcNumber) < 6) {
        $error_message = "Please enter a valid Vehicle RC (Registration) Number. Example: UP50BXXXXX";
    } else {
        $username      = (string)($udata['phone']   ?? '');
        $wallet_amount = (float) ($udata['balance'] ?? 0);
        $appliedby     = $username;
        $mobile        = $username;
        $clint_id      = (string)($clint ?? '');

        $fee = $_gp_vm_fee;

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
                'slug'     => 'instant-vehicle-to-mobile-find',
                'apikey'   => $pp_key,
                'rcNumber' => $rcNumber
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
                CURLOPT_USERAGENT      => 'goodprintportal.in-VehicleToMobile/1.0',
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
                    $error_message = "Vehicle server is temporarily unavailable. Please try again after some time.";
                } else {
                    $statusVal = $json['status'] ?? null;
                    $isSuccess = false;

                    if ($statusVal !== null) {
                        $s = (string)$statusVal;
                        if ($s === '1' || $s === '200' || $s === 'true' || $s === 'success' || $s === 'ok') {
                            $isSuccess = true;
                        }
                    }
                    if ($statusVal === true || $statusVal === 1) {
                        $isSuccess = true;
                    }

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

                    $mobileNo   = trim((string)($payload['mobile_number'] ?? $payload['mobileNumber'] ?? $payload['mobile_no'] ?? $payload['mobile'] ?? $payload['phone'] ?? ''));
                    $ownerName  = trim((string)($payload['owner_name']  ?? $payload['ownerName']  ?? $payload['name']        ?? ''));
                    $rcBack     = trim((string)($payload['rcNumber']    ?? $payload['rc_no']      ?? $payload['regn_no']     ?? $payload['regno'] ?? ''));
                    $trackingId = trim((string)($payload['tracking_id'] ?? $payload['trackingId'] ?? $payload['request_id']  ?? $payload['order_id'] ?? ''));

                    if (!$isSuccess && $mobileNo !== '') {
                        $isSuccess = true;
                    }

                    if ($isSuccess && $mobileNo !== '') {
                        if ($rcBack !== '' && $rcNumber === '') {
                            $rcNumber = strtoupper($rcBack);
                        }
                        $application_no = "VEH2MOB" . rand(100000, 999999);
                        $statusMsg      = (string)($json['message'] ?? 'Request successful.');

                        mysqli_begin_transaction($ahk_conn);

                        try {
                            $safeUser = mysqli_real_escape_string($ahk_conn, $username);
                            $safeFee  = mysqli_real_escape_string($ahk_conn, (string)$fee);
                            mysqli_query($ahk_conn, "UPDATE users SET balance = balance - {$safeFee} WHERE phone = '{$safeUser}' LIMIT 1");

                            $new_balance = $wallet_amount - $fee;
                            mysqli_query($ahk_conn,
                                "INSERT INTO wallethistory (userid, amount, balance, purpose, status, type)
                                 VALUES ('" . mysqli_real_escape_string($ahk_conn, $username) . "',
                                         '" . mysqli_real_escape_string($ahk_conn, (string)$fee) . "',
                                         '" . mysqli_real_escape_string($ahk_conn, (string)$new_balance) . "',
                                         'Vehical To Mobile Find', '1', 'Debit')");

                            $timeNow = date('d/m/Y g:i:s A');
                            $pdfData = json_encode([
                                'rcNumber'      => $rcNumber,
                                'mobile_number' => $mobileNo,
                                'owner_name'    => $ownerName,
                                'tracking_id'   => $trackingId,
                                'application'   => $application_no,
                            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                            $stmt = mysqli_prepare($ahk_conn,
                                "INSERT INTO rc_vehical (username, rc_vehical_no, status, pdf, date) VALUES (?, ?, 'Success', ?, ?)");
                            $nullStr = '';
                            mysqli_stmt_bind_param($stmt, "ssss",
                                $username, $rcNumber, $pdfData, $timeNow);
                            mysqli_stmt_execute($stmt);
                            mysqli_stmt_close($stmt);

                            mysqli_commit($ahk_conn);

                            $resdata = [
                                'statusCode'     => 100,
                                'rcNumber'       => $rcNumber,
                                'mobile_number'  => $mobileNo,
                                'owner_name'     => $ownerName,
                                'tracking_id'    => $trackingId,
                                'application_no' => $application_no,
                            ];

                        } catch (Exception $e) {
                            mysqli_rollback($ahk_conn);
                            $error_message = "Unable to complete request. Please try again.";
                        }
                    } else {
                        $apiMsg = (string)($json['message'] ?? $json['msg'] ?? $json['error'] ?? '');
                        if ($apiMsg !== '' && stripos($apiMsg, 'found') === false
                                          && stripos($apiMsg, 'success') === false
                                          && stripos($apiMsg, 'valid') === false
                                          && stripos($apiMsg, 'available') === false) {
                            $error_message = $apiMsg;
                        } else {
                            $error_message = "Mobile number not found for this RC number. Please check and try again.";
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
                        <li class="breadcrumb-item active text-muted" aria-current="page">Vehical To Mobile Find</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="rc_get_list" class="btn btn-success">
                    <i class="bi bi-list-ul me-1"></i> Vehicle Records List
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
                            <h3 class="card-title text-dark text-center"><strong>Vehicle RC To Mobile Number Find Instant</strong></h3>
                            <p class="text-center text-muted small mb-3">RC Number se uske registered mobile number + owner details fetch</p>
                        </div>
                        <div class="card-body">
                            <form action="" method="post" id="vehicle_to_mobile_form" novalidate>
                                <div class="mb-3">
                                    <label for="rcNumber" class="form-label">Vehicle RC Number <span class="text-danger">*</span></label>
                                    <input type="text" required class="form-control" name="rcNumber" id="rcNumber"
                                           placeholder="Enter RC Number (Example: UP50BXXXXX)"
                                           maxlength="20"
                                           value="<?= isset($_POST['rcNumber']) ? htmlspecialchars(strtoupper($_POST['rcNumber'])) : '' ?>">
                                </div>
                                <button type="submit" name="find_vehicle_mobile" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> Find Mobile Number
                                </button>

                                <div class="mt-3 text-center fee-container">
                                    <h5 class="text-warning">
                                        Application Fee:
                                        ₹<?= htmlspecialchars(number_format($_gp_vm_fee, 2)); ?>
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
                                        <h5 class="mb-0 text-success">Vehicle Mobile Details Fetched</h5>
                                        <small class="text-muted">Txn ID: <?= htmlspecialchars($resdata['application_no']); ?></small>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped align-middle mb-0">
                                        <tbody>
                                            <?php if (!empty($resdata['rcNumber'])): ?>
                                                <tr>
                                                    <th style="width:40%; background:#eef2ff;">RC Number</th>
                                                    <td><strong class="text-primary"><?= htmlspecialchars($resdata['rcNumber']); ?></strong></td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($resdata['mobile_number'])): ?>
                                                <tr>
                                                    <th style="background:#eef2ff;">Registered Mobile No</th>
                                                    <td><strong class="text-success" style="font-size:1.08rem; letter-spacing:1px;"><?= htmlspecialchars($resdata['mobile_number']); ?></strong></td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($resdata['owner_name'])): ?>
                                                <tr>
                                                    <th style="background:#eef2ff;">Owner Name</th>
                                                    <td><?= htmlspecialchars($resdata['owner_name']); ?></td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($resdata['tracking_id'])): ?>
                                                <tr>
                                                    <th style="background:#eef2ff;">Tracking ID</th>
                                                    <td><span class="text-muted small"><?= htmlspecialchars($resdata['tracking_id']); ?></span></td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-4">
                                    <div class="text-muted small">
                                        <i class="bi bi-wallet2 me-1"></i>
                                        Service charge <strong>₹<?= htmlspecialchars(number_format((float)($fee ?? $display_price), 2)); ?></strong> debited from wallet
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
                title: 'Mobile number found',
                text: 'RC: <?= htmlspecialchars($resdata['rcNumber']); ?>',
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
