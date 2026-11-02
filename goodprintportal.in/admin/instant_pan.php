<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

date_default_timezone_set('Asia/Kolkata');

$resdata = [];
$aadhaar = "";

// Fetch fee from database
$price_query = mysqli_query($ahk_conn, "SELECT price FROM pricing WHERE service_name='Aadhaar_To_Pan_find'");
$price = mysqli_fetch_assoc($price_query);
$fee = $price['price'] ?? 0;

if (isset($_POST['check']) && $_POST['check'] === "aadhaar") {
    $aadhaar = mysqli_real_escape_string($ahk_conn, $_POST['aadhaar_no']);

    // Validation for 12 digit Aadhaar
    if (strlen($aadhaar) != 12 || !is_numeric($aadhaar)) {
        echo '<script>$(function(){ Swal.fire("Invalid Aadhaar", "Please enter a valid 12-digit Aadhaar Number", "warning") });</script>';
    } else {
        $application_no = "INSTANT" . rand(100000, 999999);
        $username   = $udata['phone'];
        $appliedby  = $udata['phone'];
        $wallet_amount = $udata['balance'];
        $debit_fee  = $wallet_amount - $fee;

        if ($wallet_amount >= $fee) {

            // ====================== PARI PRINT URL (Clean & Recommended) ======================
            $url = $auth_url . "api_service/panfind_api.php?" . http_build_query([
                'api_key'    => $api_key,
                'secret_key' => $secret_key,
                'aadhar'     => $aadhaar
            ]);

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => "",
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => "GET",
            ]);

            $response = curl_exec($curl);
            $curl_error = curl_error($curl);
            curl_close($curl);

            if ($curl_error) {
                echo '<script>$(function(){ Swal.fire("cURL Error", "' . addslashes($curl_error) . '", "error") });</script>';
            } else {
                $resdata = json_decode($response, true);

                if (isset($resdata['status']) && $resdata['status'] == "100") {
                    $pan = mysqli_real_escape_string($ahk_conn, $resdata['pan'] ?? '');
                    $name = mysqli_real_escape_string($ahk_conn, $resdata['name'] ?? 'N/A');
                    $message = $resdata['message'] ?? '';
                    $status_code = $resdata['status'];

                    // Deduct Wallet
                    $debit = mysqli_query($ahk_conn, "UPDATE users SET balance = balance - $fee WHERE phone = '$appliedby'");

                    // Wallet History
                    mysqli_query($ahk_conn, "INSERT INTO wallethistory 
                        (userid, amount, balance, purpose, status, type) 
                        VALUES ('$appliedby', '$fee', '$debit_fee', 'PAN Number Find', '1', 'Debit')");

                    if ($debit) {
                        // Insert Record
                        $sql_insert = "INSERT INTO instantpan_find 
                            (application_no, username, aadhaar_no, pan_no, status, status_code, fee, old_balance, new_balance, message)
                            VALUES 
                            ('$application_no', '$username', '$aadhaar', '$pan', '$status_code', '$response', '$fee', '$wallet_amount', '$debit_fee', '$message')";

                        if (mysqli_query($ahk_conn, $sql_insert)) {
                            echo "<script>
                                $(function(){ 
                                    Swal.fire('Success', 'PAN No: $pan <br> Application ID: $application_no', 'success'); 
                                });
                            </script>";
                        } else {
                            echo '<script>$(function(){ Swal.fire("Database Error", "Balance deducted but data not saved", "warning") });</script>';
                        }
                    }
                } else {
                    $error_msg = $resdata['msg'] ?? $resdata['message'] ?? "Server Down or Invalid Response";
                    echo '<script>$(function(){ Swal.fire("API Error", "' . htmlspecialchars($error_msg, ENT_QUOTES) . '", "error") });</script>';
                }
            }
        } else {
            echo '<script>
                $(function(){ Swal.fire("Low Balance", "Please Recharge Your Wallet", "error") });
                setTimeout(() => { window.location="wallet.php"; }, 1500);
            </script>';
        }
    }
}
?>

<!-- HTML UI -->
<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted">Pan Find Service</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="instant_pan_list" class="btn btn-success">
                    <i class="bi bi-list-ul me-1"></i> Pan Find List
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        <div class="container-fluid pt-4 px-4">
            <div class="row justify-content-center">
                <!-- Form Section -->
                <div class="col-lg-5 mb-4">
                    <div class="card shadow border-0">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0 text-center">
                                <i class="bi bi-credit-card-2-front me-2"></i>Aadhaar to PAN Find
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="form-group mb-3">
                                    <label for="aadhaar_no" class="form-label">Aadhaar Number <span class="text-danger">*</span></label>
                                    <input name="aadhaar_no" type="text" id="aadhaar_no" 
                                           placeholder="Enter 12 Digits Aadhaar Number" 
                                           class="form-control form-control-lg" 
                                           maxlength="12" required pattern="[0-9]{12}">
                                    <input type="hidden" name="check" value="aadhaar">
                                </div>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="bi bi-search me-2"></i>Verify Now
                                    </button>
                                </div>
                            </form>

                            <div class="text-center mt-4">
                                <div class="alert alert-warning py-2 px-3 d-inline-block">
                                    <h6 class="mb-0">
                                        <i class="bi bi-currency-rupee"></i> Application Fee: 
                                        <strong>₹<?php echo $fee; ?></strong>
                                    </h6>
                                </div>
                            </div>

                            <div class="text-center mt-3">
                                <a href="pan_details_verify" class="btn btn-outline-dark btn-sm">
                                    <i class="bi bi-arrow-left-right me-1"></i> PAN Number To PAN Details Verify
                                </a>
                            </div>
                        </div>
                        <div class="card-footer text-center bg-light">
                            <small class="text-muted">Powered by <a href="https://apiadda.in" target="_blank" class="fw-bold text-primary">apiadda.in</a></small>
                        </div>
                    </div>
                </div>

                <!-- Result Section -->
                <div class="col-lg-6 mb-4">
                    <?php if (!empty($resdata) && $resdata['status'] == "100"): ?>
                        <div class="card shadow border-0" id="printSection">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="bi bi-patch-check-fill me-2"></i>PAN Details Found</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-bordered table-hover">
                                    <tbody>
                                        <tr>
                                            <th style="width: 40%">Aadhaar Number</th>
                                            <td class="fw-bold"><?= htmlspecialchars($aadhaar); ?></td>
                                        </tr>
                                        <?php if (!empty($resdata['pan'])): ?>
                                        <tr>
                                            <th>PAN Number</th>
                                            <td class="text-primary fw-bold fs-5"><?= htmlspecialchars($resdata['pan']); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                        <?php if (!empty($resdata['name'])): ?>
                                        <tr>
                                            <th>Name</th>
                                            <td><?= htmlspecialchars($resdata['name']); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                        <?php if (!empty($resdata['dob'])): ?>
                                        <tr>
                                            <th>Date of Birth</th>
                                            <td><?= htmlspecialchars($resdata['dob']); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                                <div class="text-end mt-3">
                                    <button class="btn btn-dark px-4" onclick="printBill()">
                                        <i class="bi bi-printer me-2"></i>Print
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php elseif (isset($_POST['check'])): ?>
                        <div class="card shadow border-0 bg-light">
                            <div class="card-body text-center py-5">
                                <i class="bi bi-exclamation-circle fs-1 text-muted"></i>
                                <h5 class="text-muted mt-3">No Data Found</h5>
                                <p class="text-muted">Please check the Aadhaar number or try again.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card shadow border-0 bg-light">
                            <div class="card-body text-center py-5">
                                <i class="bi bi-person-vcard fs-1 text-primary"></i>
                                <h5 class="text-muted mt-3">Enter Aadhaar Number</h5>
                                <p class="text-muted">PAN details will appear here after verification.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Powered By Note -->
<div class="text-center mt-4 mb-4">
    <small class="text-muted">
        💻 Powered by <strong>PARI PRINT</strong><br>
        🚀 Developed by <strong>LALA WEB DEVELOPER</strong>
    </small>
</div>

<!-- Styles & Scripts -->
<style>
    .card { border-radius: 10px; }
    .form-control:focus {
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
    }
</style>

<script>
    function printBill() {
        var printContents = document.getElementById('printSection').innerHTML;
        var originalContents = document.body.innerHTML;
        document.body.innerHTML = printContents;
        window.print();
        document.body.innerHTML = originalContents;
    }

    document.addEventListener("DOMContentLoaded", function() {
        const aadInput = document.getElementById('aadhaar_no');
        if (aadInput) {
            aadInput.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '');
            });
        }
    });
</script>

<?php include('footer.php'); ?>

<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>
</body>
</html>