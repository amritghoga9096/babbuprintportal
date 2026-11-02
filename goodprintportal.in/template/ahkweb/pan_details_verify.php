<?php
include('header.php');

date_default_timezone_set("Asia/Kolkata");

global $CENTRAL_API_BASE, $pariprint_api_key, $ahk_conn, $udata;

define('PAN_DETAILS_VERIFY_FEE_DEFAULT', 30);

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
$_PANDV_CANDIDATES = ['pan_details_verify', 'pan_details', 'pan_verify_fee'];
$_gp_pandv_fee = gp_get_service_fee($ahk_conn ?? $GLOBALS['ahk_conn'], $_PANDV_CANDIDATES, PAN_DETAILS_VERIFY_FEE_DEFAULT);

$resdata       = null;
$error_message = '';

if (isset($_POST['verify_pan'])) {
    $pan_no = strtoupper(trim($_POST['pan_no']));

    if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/i', $pan_no)) {
        $error_message = "Please enter a valid 10-digit PAN Number (Example: ABCDE1234F).";
    } else {
        $username     = (string)($udata['phone']   ?? '');
        $wallet_amount= (float) ($udata['balance'] ?? 0);

        $fee          = $_gp_pandv_fee;

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
                'slug'    => 'pan-details',
                'api_key' => $pp_key,
                'pan'     => $pan_no
            ]);

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 45,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPGET        => true,
                CURLOPT_USERAGENT      => 'goodprintportal.in-PanDetailsVerify/1.0',
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
                    $error_message = "PAN server is temporarily unavailable. Please try again after some time.";
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
                    } else {
                        $payload = $json;
                    }

                    $name       = trim((string)($payload['name']       ?? ''));
                    $fathername = trim((string)($payload['fathername'] ?? $payload['Fathername'] ?? $payload['fatherName'] ?? $payload['father_name'] ?? ''));
                    $dob        = trim((string)($payload['dob']        ?? ''));
                    $gender     = trim((string)($payload['gender']     ?? ''));
                    $pan_back   = trim((string)($payload['pan']        ?? ''));

                    if (!$isSuccess && ($name !== '' || $fathername !== '' || $pan_back !== '')) {
                        $isSuccess = true;
                    }

                    if ($isSuccess && ($name !== '' || $pan_back !== '')) {
                        if ($pan_back !== '' && $pan_no === '') {
                            $pan_no = strtoupper($pan_back);
                        }
                        if ($pan_no === '' && $pan_back !== '') {
                            $pan_no = strtoupper($pan_back);
                        }

                        $time_hkb = date('d/m/Y g:i:s A');

                        mysqli_begin_transaction($ahk_conn);

                        try {
                            $safeUser = mysqli_real_escape_string($ahk_conn, $username);
                            $safeFee  = mysqli_real_escape_string($ahk_conn, (string)$fee);
                            mysqli_query($ahk_conn, "UPDATE users SET balance = balance - {$safeFee} WHERE phone = '{$safeUser}' LIMIT 1");

                            $stmt = mysqli_prepare($ahk_conn,
                                "INSERT INTO pan_verify_hkb (name, fathername, gender, dob, pan, username, date)
                                 VALUES (?, ?, ?, ?, ?, ?, ?)");
                            mysqli_stmt_bind_param($stmt, "sssssss", $name, $fathername, $gender, $dob, $pan_no, $username, $time_hkb);
                            mysqli_stmt_execute($stmt);
                            mysqli_stmt_close($stmt);

                            $new_balance = $wallet_amount - $fee;
                            $stmt2 = mysqli_prepare($ahk_conn,
                                "INSERT INTO wallethistory (userid, amount, balance, purpose, status, type)
                                 VALUES (?, ?, ?, 'PAN Details Verify', '1', 'Debit')");
                            mysqli_stmt_bind_param($stmt2, "sdd", $username, $fee, $new_balance);
                            mysqli_stmt_execute($stmt2);
                            mysqli_stmt_close($stmt2);

                            mysqli_commit($ahk_conn);

                            $resdata = [
                                'statusCode'    => 100,
                                'pan_no'        => $pan_no,
                                'name'          => $name,
                                'fathername'    => $fathername,
                                'gender'        => $gender,
                                'dob'           => $dob,
                                'aadhaarLinked' => '',
                            ];

                        } catch (Exception $e) {
                            mysqli_rollback($ahk_conn);
                            $error_message = "Unable to complete request. Please try again.";
                        }
                    } else {
                        $apiMsg = (string)($json['message'] ?? $json['msg'] ?? $json['error'] ?? '');
                        if ($apiMsg !== '' && stripos($apiMsg, 'found') === false
                                          && stripos($apiMsg, 'success') === false
                                          && stripos($apiMsg, 'valid') === false) {
                            $error_message = $apiMsg;
                        } else {
                            $error_message = "PAN details not found. Please check the PAN Number and try again.";
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
                        <li class="breadcrumb-item active text-muted" aria-current="page">Pan Details Find</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
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
                            <h3 class="card-title text-dark text-center"><strong>Pan Number To Pan Details Find Instant</strong></h3>
                        </div>
                        <div class="card-body">
                            <a href="Pan_Advance_flex" class="btn btn-dark w-100 mb-3">Pan Advance Instant Pan e-PAN Pdf Download</a>
                            <form action="" method="post" id="pan_verification" novalidate>
                                <div class="mb-3">
                                    <label for="pan_no" class="form-label">Pan Number</label>
                                    <input type="text" required class="form-control" name="pan_no" id="pan_no" placeholder="Enter Pan Number (Example: ABCDE1234F)" maxlength="10" pattern="[A-Za-z]{5}[0-9]{4}[A-Za-z]{1}" value="<?= isset($_POST['pan_no']) ? htmlspecialchars(strtoupper($_POST['pan_no'])) : '' ?>">
                                </div>
                                <button type="submit" name="verify_pan" class="btn btn-primary w-100"><i class="fa fa-check-circle"></i> Verify PAN</button>

                                <div class="mt-3 text-center fee-container">
                                    <h5 class="text-warning">
                                        Application Fee:
                                        ₹<?= htmlspecialchars(number_format($_gp_pandv_fee, 2)); ?>
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
                                        <h5 class="mb-0 text-success">PAN Details Fetched Successfully</h5>
                                        <small class="text-muted">Transaction completed &amp; receipt generated</small>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped align-middle mb-0">
                                        <tbody>
                                            <?php if (!empty($resdata['pan_no'])): ?>
                                                <tr>
                                                    <th style="width:40%; background:#eef2ff;">PAN Number</th>
                                                    <td><strong class="text-primary"><?= htmlspecialchars($resdata['pan_no']); ?></strong></td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($resdata['name'])): ?>
                                                <tr>
                                                    <th style="background:#eef2ff;">Full Name</th>
                                                    <td><?= htmlspecialchars($resdata['name']); ?></td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($resdata['fathername'])): ?>
                                                <tr>
                                                    <th style="background:#eef2ff;">Father / Husband Name</th>
                                                    <td><?= htmlspecialchars($resdata['fathername']); ?></td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($resdata['dob'])): ?>
                                                <tr>
                                                    <th style="background:#eef2ff;">Date of Birth</th>
                                                    <td><?= htmlspecialchars($resdata['dob']); ?></td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($resdata['gender'])): ?>
                                                <tr>
                                                    <th style="background:#eef2ff;">Gender</th>
                                                    <td><?= htmlspecialchars($resdata['gender']); ?></td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-4">
                                    <div class="text-muted small">
                                        <i class="bi bi-wallet2 me-1"></i>
                                        Service charge <strong>₹<?= htmlspecialchars($fee ?? '0'); ?></strong> debited from wallet
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
                title: 'PAN detail fetched successfully',
                text: '<?= htmlspecialchars($resdata['pan_no']); ?>',
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
    <script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
