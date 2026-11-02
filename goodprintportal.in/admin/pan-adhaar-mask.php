<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

date_default_timezone_set('Asia/Kolkata');

// Fetch fee from database
$price_row = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT price FROM pricing WHERE service_name='pan_adhaar_mask'"));
$fee = $price_row['price'] ?? 0;

$resdata = [];
$panNumber = "";

if (isset($_POST['panNumber'])) {
    $panNumber = strtoupper(trim($_POST['panNumber']));
    $appliedby = $udata['phone'];
    $debit_fee = $udata['balance'] - $fee;

    if ($udata['balance'] >= $fee) {

        // ====================== PARI PRINT URL ======================
        $url = $auth_url . "PANDATA/pan_aadhaar.php?" . http_build_query([
            'api_key'    => $api_key,
            'secret_key' => $secret_key,
            'pan'        => $panNumber
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
        curl_close($curl);

        $resdata = json_decode($response, true) ?? [];

        if (isset($resdata['status']) && $resdata['status'] === "success") {
            // Deduct Balance
            mysqli_query($ahk_conn, "UPDATE users SET balance = balance - $fee WHERE phone = '$appliedby'");

            // Wallet History
            mysqli_query($ahk_conn, "INSERT INTO wallethistory 
                (userid, amount, balance, purpose, status, type) 
                VALUES ('$appliedby', '$fee', '$debit_fee', 'PAN to Mask Aadhar Verification', '1', 'Debit')");
        } else {
            $error_message = $resdata['message'] ?? 'API Error or Invalid PAN.';
            echo "<script>
                Swal.fire('Error', '" . addslashes($error_message) . "', 'error');
            </script>";
        }
    } else {
        echo "<script>
            Swal.fire('Wallet Balance is Low!', 'Please Recharge Now!', 'error');
            setTimeout(() => { window.location='wallet.php'; }, 1500);
        </script>";
    }
}
?>

<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted">PAN Aadhaar Verification</li>
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
                <!-- Form Section -->
                <div class="col-lg-5 mb-4">
                    <div class="card card-custom shadow">
                        <div class="card-header gradient-bg text-center py-3">
                            <h5 class="mb-0">
                                <i class="fas fa-id-card me-2"></i> PAN Aadhaar Mask
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            <form action="" method="POST" class="row g-3">
                                <div class="col-12">
                                    <label for="panNumber" class="form-label fw-bold">Enter PAN Number</label>
                                    <input name="panNumber" type="text" id="panNumber" 
                                           placeholder="Ex: ABCDE1234F" 
                                           class="form-control form-control-lg text-uppercase" 
                                           maxlength="10" required>
                                </div>

                                <div class="col-12 mt-4">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="text-muted">Service Fee</span><br>
                                            <h4 class="mb-0 text-success fw-bold">₹ <?php echo $fee; ?></h4>
                                        </div>
                                        <button type="submit" class="btn btn-custom btn-lg px-5 text-white">
                                            <i class="fa fa-check-circle me-2"></i> Verify Now
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <!-- Powered By -->
                            <div class="text-center mt-5 pt-3 border-top">
                                <small class="text-muted">
                                    💻 Powered by <strong>goodprintportal.in</strong><br>
                                    🚀 Developed by <strong>LALA WEB DEVELOPER</strong>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Result Section -->
                <?php if (isset($resdata['status']) && $resdata['status'] === "success"): ?>
                <div class="col-lg-7">
                    <div class="card card-custom result-card shadow" id="printSection">
                        <div class="card-body p-4">
                            <div class="text-center mb-4">
                                <i class="fas fa-check-circle text-success" style="font-size: 60px;"></i>
                                <h4 class="mt-3 text-success">Verification Successful</h4>
                            </div>

                            <table class="table table-borderless">
                                <tbody>
                                    <tr>
                                        <td class="fw-bold text-muted">Status</td>
                                        <td class="text-success fw-bold"><?php echo htmlspecialchars($resdata['result'] ?? 'Linked'); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold text-muted">PAN Number</td>
                                        <td class="fw-bold"><?php echo htmlspecialchars($resdata['pan'] ?? $panNumber); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold text-muted">Masked Aadhaar</td>
                                        <td class="fs-5 fw-bold text-primary"><?php echo htmlspecialchars($resdata['masked_aadhaar'] ?? ''); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold text-muted">Message</td>
                                        <td><?php echo htmlspecialchars($resdata['message'] ?? ''); ?></td>
                                    </tr>
                                </tbody>
                            </table>

                            <div class="text-end mt-4">
                                <button class="btn btn-outline-primary px-5" onclick="printVerification()">
                                    <i class="fas fa-print me-2"></i> Print Result
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>

<!-- Styles -->
<style>
    .gradient-bg {
        background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
        color: white;
    }
    .card-custom {
        border: none;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    .btn-custom {
        background: linear-gradient(to right, #11998e, #38ef7d);
        border: none;
        font-weight: bold;
    }
    .btn-custom:hover {
        background: linear-gradient(to right, #0f8b82, #32d572);
    }
    .result-card {
        background: #f8f9fa;
        border-left: 6px solid #2575fc;
    }
</style>

<!-- Print Function -->
<script>
function printVerification() {
    var content = document.getElementById("printSection").innerHTML;
    var printWindow = window.open('', '_blank', 'width=800,height=700');
    printWindow.document.write(`
        <html>
            <head>
                <title>PAN Aadhaar Mask Verification</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 30px; }
                    table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                    td { padding: 12px; border-bottom: 1px solid #ddd; }
                    .text-success { color: green; font-weight: bold; }
                    .text-primary { color: blue; font-weight: bold; }
                    h4 { text-align: center; color: #2575fc; }
                    .text-center { text-align: center; margin-top: 30px; font-size: 13px; color: #666; }
                </style>
            </head>
            <body>
                <h2 style="text-align:center; color:#2575fc;">PAN Aadhaar Mask Verification Result</h2>
                <hr>
                ${content}
                <div class="text-center">
                    💻 Powered by PARI PRINT<br>
                    🚀 Developed by LALA WEB DEVELOPER
                </div>
                <script>window.onload = function() { window.print(); };</script>
            </body>
        </html>
    `);
    printWindow.document.close();
}
</script>

<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>
</body>
</html>