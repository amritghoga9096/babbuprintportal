<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

// Fetch fee from database
$price_row = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT price FROM pricing WHERE service_name='elctricbill_pdf_fee'"));
$fee = $price_row['price'] ?? 0;

// Initialize response
$resdata = null;
$application_no = "EB_" . rand(100000, 999999);

if (isset($_POST['caNumber'])) {
    $caNumber   = mysqli_real_escape_string($ahk_conn, $_POST['caNumber']);
    $appliedby  = $udata['phone'];
    $debit_fee  = $udata['balance'] - $fee;

    if ($udata['balance'] >= $fee) {

        // ====================== PARI PRINT URL (Tumhara Preferred Style) ======================
        $url = $auth_url . "elabharthi/bijli_pdf.php?" . http_build_query([
            'api_key'    => $api_key,
            'secret_key' => $secret_key,
            'ca'         => $caNumber
        ]);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => "",
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => "GET",
        ]);

        $response = curl_exec($curl);
        $err      = curl_error($curl);
        curl_close($curl);

        if ($err) {
            echo "<script>
                Swal.fire('cURL Error', '" . addslashes($err) . "', 'error');
            </script>";
        } else {
            $resdata = json_decode($response, true);

            if (isset($resdata['status']) && $resdata['status'] === true) {
                
                // Deduct Balance
                mysqli_query($ahk_conn, "UPDATE users SET balance = balance - $fee WHERE phone = '$appliedby'");

                // Log Wallet History
                $newBalance = $udata['balance'] - $fee;
                mysqli_query($ahk_conn, "INSERT INTO wallethistory 
                    (userid, amount, balance, purpose, status, type) 
                    VALUES ('$appliedby', '$fee', '$newBalance', 'Electric Bill PDF', '1', 'Debit')");

            } else {
                $errorMsg = $resdata['message'] ?? 'Unknown API Error or Invalid CA Number';
                echo "<script>
                    Swal.fire('Failed', '" . addslashes($errorMsg) . "', 'error');
                </script>";
            }
        }
    } else {
        echo "<script>
            Swal.fire('Wallet Balance is Low!', 'Please Recharge Now!', 'error');
            setTimeout(() => { window.location='wallet.php'; }, 1500);
        </script>";
    }
}
?>

<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted">Bihar Electric Bill PDF</li>
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
                <!-- Left Side: Form -->
                <div class="col-lg-5 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <h3 class="card-title text-center"><strong>Electric Bill PDF</strong></h3>
                            <div class="alert alert-danger" role="alert">
                                BIHAR STATE - Electric Bill PDF Download
                            </div>

                            <form action="" method="POST" class="row g-3">
                                <div class="form-group mb-3">
                                    <label for="caNumber">Enter CA Number</label>
                                    <input name="caNumber" type="text" id="caNumber" 
                                           placeholder="Enter 9 or 12 Digit CA Number" 
                                           class="form-control" required>
                                </div>

                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <input class="form-control text-center" 
                                               value="Fee ₹ <?php echo $fee; ?>" readonly>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <button type="submit" class="btn btn-success" name="submit" id="submit">
                                            <i class="fa fa-check-circle"></i> Submit
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Right Side: Result & PDF Preview -->
                <?php if (isset($resdata) && $resdata['status'] === true): ?>
                <div class="col-lg-7">
                    <div class="card" style="background-color: #FCF3CF;">
                        <div class="card-body">
                            <h5 class="mb-3">Electricity Bill Details</h5>
                            <table class="table table-bordered table-striped">
                                <tbody>
                                    <tr>
                                        <th>CA Number</th>
                                        <td><?php echo htmlspecialchars($resdata['ca_number'] ?? 'N/A'); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Message</th>
                                        <td><?php echo htmlspecialchars($resdata['message'] ?? ''); ?></td>
                                    </tr>
                                    <tr>
                                        <th>VIEW / DOWNLOAD</th>
                                        <td>
                                            <a href="data:application/pdf;base64,<?php echo $resdata['pdfbase64pdf'] ?? ''; ?>"
                                               download="Bill_<?php echo $resdata['ca_number'] ?? 'Unknown'; ?>.pdf"
                                               class="btn btn-sm btn-success">
                                                <i class="bi bi-download"></i> Download PDF
                                            </a>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                            <!-- PDF Preview -->
                            <div class="mt-4">
                                <h6>PDF Preview:</h6>
                                <iframe src="data:application/pdf;base64,<?php echo $resdata['pdfbase64pdf'] ?? ''; ?>"
                                        width="100%" height="550px" style="border: 1px solid #ccc;">
                                    Your browser does not support PDF previews.
                                </iframe>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Powered By Note -->
<div class="text-center mt-4 mb-3">
    <small class="text-muted">
        💻 Powered by <strong>PARI PRINT</strong><br>
        🚀 Developed by <strong>LALA WEB DEVELOPER</strong>
    </small>
</div>

<?php include('footer.php'); ?>

<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>

<script>
    // Optional: SweetAlert2 already included via header/footer
</script>
</body>
</html>