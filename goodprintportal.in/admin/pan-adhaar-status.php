<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

date_default_timezone_set('Asia/Kolkata');

// Fetch fee from database
$price_row = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT price FROM pricing WHERE service_name='pan_adhaar_status'"));
$fee = $price_row['price'] ?? 0;

$resdata = [];
$panNumber = "";

if (isset($_POST['panNumber'])) {
    $panNumber = strtoupper(trim($_POST['panNumber']));
    $appliedby = $udata['phone'];
    $debit_fee = $udata['balance'] - $fee;

    if ($udata['balance'] >= $fee) {

        // ====================== PARI PRINT URL (Clean & Correct) ======================
        $url = $auth_url . "PANDATA/PAN_Aadhaar_Link_Status.php?" . http_build_query([
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

        if (isset($resdata['status']) && $resdata['status'] === "success" && isset($resdata['code']) && $resdata['code'] == 200) {
            // Deduct Balance
            mysqli_query($ahk_conn, "UPDATE users SET balance = balance - $fee WHERE phone = '$appliedby'");

            // Wallet History
            mysqli_query($ahk_conn, "INSERT INTO wallethistory 
                (userid, amount, balance, purpose, status, type) 
                VALUES ('$appliedby', '$fee', '$debit_fee', 'PAN Aadhaar Link Status Check', '1', 'Debit')");
        } else {
            $error_message = $resdata['message'] ?? 'Something went wrong or invalid PAN.';
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
                        <li class="breadcrumb-item active text-muted">PAN Aadhaar Status</li>
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
                    <div class="card shadow border-0">
                        <div class="card-body p-4">
                            <h3 class="card-title text-center mb-4">
                                <strong>PAN Aadhaar Status Check</strong>
                            </h3>

                            <form action="" method="POST" class="row g-3">
                                <div class="col-12">
                                    <label for="panNumber" class="form-label fw-bold">Enter PAN Number</label>
                                    <input name="panNumber" type="text" id="panNumber" 
                                           placeholder="Ex: ABCDE1234F" 
                                           class="form-control form-control-lg text-uppercase" 
                                           maxlength="10" required 
                                           pattern="[A-Z]{5}[0-9]{4}[A-Z]{1}">
                                </div>

                                <div class="col-12 mt-4">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="text-muted">Service Fee</span><br>
                                            <h4 class="mb-0 text-success fw-bold">₹ <?php echo $fee; ?></h4>
                                        </div>
                                        <button type="submit" class="btn btn-success btn-lg px-5">
                                            <i class="fa fa-check-circle me-2"></i> Check Status
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <!-- Powered By Note -->
                            <div class="text-center mt-5 pt-4 border-top">
                                <small class="text-muted">
                                    💻 Powered by <strong>goodprintportal.in</strong><br>
                                    🚀 Developed by <strong>LALA WEB DEVELOPER</strong>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Result Section -->
                <?php if (isset($resdata['status']) && $resdata['status'] === "success" && isset($resdata['code']) && $resdata['code'] == 200): ?>
                <div class="col-lg-7">
                    <div class="card shadow" style="background-color: #FCF3CF;" id="printSection">
                        <div class="card-body p-4">
                            <h5 class="mb-3 text-center">PAN Aadhaar Link Result</h5>
                            <table class="table table-bordered table-striped">
                                <tbody>
                                    <tr>
                                        <th>PAN Number</th>
                                        <td><?php echo htmlspecialchars($resdata['pan'] ?? $panNumber); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Link Status</th>
                                        <td>
                                            <?php
                                                $status = $resdata['aadhaar_link_status'] ?? 'N/A';
                                                $badgeClass = ($status == 'Linked') ? 'success' : 'danger';
                                                echo "<span class='badge bg-{$badgeClass} fs-6'>{$status}</span>";
                                            ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Masked Aadhaar</th>
                                        <td class="fw-bold"><?php echo htmlspecialchars($resdata['masked_aadhaar'] ?? 'N/A'); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Message</th>
                                        <td><?php echo htmlspecialchars($resdata['message'] ?? ''); ?></td>
                                    </tr>
                                </tbody>
                            </table>

                            <div class="text-end mt-4">
                                <button class="btn btn-primary px-4" onclick="printVerification()">
                                    🖨️ Print Result
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

<!-- Print Script -->
<script>
function printVerification() {
    var content = document.getElementById("printSection").innerHTML;
    var printWindow = window.open('', '_blank', 'width=800,height=600');
    printWindow.document.write(`
        <html>
            <head>
                <title>PAN Aadhaar Link Status</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 30px; }
                    table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                    th, td { border: 1px solid #333; padding: 10px; text-align: left; }
                    th { background-color: #f0f0f0; }
                    .badge { padding: 6px 12px; border-radius: 20px; }
                </style>
            </head>
            <body>
                <h3 style="text-align:center;">PAN Aadhaar Link Status Result</h3>
                <hr>
                ${content}
                <div style="text-align:center; margin-top:30px; font-size:13px; color:#666;">
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