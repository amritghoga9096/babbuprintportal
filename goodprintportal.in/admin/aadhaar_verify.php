<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

// fetch fee from database
$price = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT * FROM pricing WHERE service_name='aadhaar_verify_fee' "));
$fee = $price['price'];

$resdata = [];
$error_message = '';
$captchaData = [];
$uidNumber = ''; // Initialize UID variable

// Step 1: Get captcha image and txnId from API
$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => "https://" . $flexsecureapiurl . "/api/v1/eid/captcha",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "GET",
));
$details = curl_exec($curl);
curl_close($curl);

$captchaData = json_decode($details, true);

if (!$captchaData || !isset($captchaData['captchaTxnId']) || !isset($captchaData['captchaBase64String'])) {
    $error_message = "Failed to load captcha. Please refresh the page.";
}

// Step 2: Handle form submission
if (isset($_POST['uidNumber'], $_POST['imgcaptcha'], $_POST['captchaTxnId'])) {
    $uidNumber = trim($_POST['uidNumber']);
    $imgcaptcha = trim($_POST['imgcaptcha']);
    $captchaTxnId = trim($_POST['captchaTxnId']);
    $appliedby = $udata['phone'];
    $debit_fee = $udata['balance'] - $fee;

    if ($udata['balance'] >= $fee) {
        $api_key = urlencode($flexsecure_key); // Secure the key

        $url = "https://" . $flexsecureapiurl . "/api/v1/eid/verifyUid?apiKey=$api_key&uidNumber=$uidNumber&imgcaptcha=$imgcaptcha&captchaTxnId=$captchaTxnId";

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array("cache-control: no-cache"),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        $resdata = json_decode($response, true) ?? [];

        if (isset($resdata['status']) && $resdata['status'] === "100") {
            mysqli_query($ahk_conn, "UPDATE users SET balance=balance-$fee WHERE phone='$appliedby'");
            mysqli_query($ahk_conn, "INSERT INTO `wallethistory`(`userid`, `amount`, `balance`, `purpose`, `status`, `type`) 
                VALUES ('$appliedby','$fee','$debit_fee','Aadhaar Mobile Mask Verification','1','Debit')");
        } else {
            $error_message = $resdata['uidMessage'] ?? $resdata['error'] ?? 'Something went wrong.';
            echo "<script>$(function(){ Swal.fire('Error', '".addslashes($error_message)."', 'error') }); setTimeout(() => { window.location=''; }, 5000);</script>";
        }
    } else {
        echo "<script>$(function(){ Swal.fire('Wallet Balance is Low!', 'Please Recharge Now!', 'error') }); setTimeout(() => { window.location='wallet.php'; }, 1200);</script>";
    }
}
?>

<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb with Action Buttons -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">Aadhaar Verify</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
               
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        <!-- Breadcrumb -->
       

        <head>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
            <style>
                .hidden { display: none; }
                .captcha-img { margin: 10px 0; }
            </style>
        </head>

        <body>
            <div class="container-fluid pt-4 px-4">
                <div class="row">

                    <!-- Form Section -->
                    <div class="col-lg-5 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h3 class="card-title text-dark text-center"><strong>Aadhaar Mobile Mask Verification</strong></h3>
                                
                                <?php if ($error_message): ?>
                                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                                <?php endif; ?>
                                
                                <form action="" method="POST" class="row g-3">
                                    <div class="col-md-12">
                                        <label for="uidNumber">Enter Aadhaar Number</label>
                                        <input name="uidNumber" type="text" id="uidNumber" placeholder="Enter Aadhaar Number" class="form-control" required 
                                            pattern="\d{12}" minlength="12" maxlength="12"
                                            title="Please enter a valid 12-digit Aadhaar number"
                                            value="<?php echo htmlspecialchars($uidNumber); ?>">

                                        <label class="mt-3">Captcha</label><br>
                                        <?php if (!empty($captchaData['captchaBase64String'])): ?>
                                            <img src="<?php echo htmlspecialchars($captchaData['captchaBase64String']); ?>" alt="Captcha Image" class="captcha-img" />
                                        <?php endif; ?>

                                        <input type="text" name="imgcaptcha" placeholder="Enter Captcha Text" class="form-control" required>

                                        <input type="hidden" name="captchaTxnId" value="<?php echo htmlspecialchars($captchaData['captchaTxnId'] ?? ''); ?>">

                                        <hr>
                                        <div class="row mt-3">
                                            <div class="col-md-6">
                                                <input class="form-control" value="Fee ₹ <?php echo htmlspecialchars($fee); ?>" readonly>
                                            </div>
                                            <div class="col-md-6 text-end">
                                                <button class="btn btn-success" name="submit" id="submit">
                                                    <i class="fa fa-check-circle"></i> Submit
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Result Section -->
                    <?php if (isset($resdata['status']) && $resdata['status'] === "100") { ?>
                        <div class="col-lg-7 col-md-6 col-sm-6">
                            <div class="card" style="background-color: #FCF3CF;" id="printSection">
                                <div class="card-body">
                                    <h5 class="mb-3">Aadhaar Mobile Mask Verification Result</h5>
                                    <table class="table table-bordered table-striped">
                                        <tbody>
                                            <tr><th>Status</th><td><?php echo htmlspecialchars($resdata['statusMessage']); ?></td></tr>
                                            <tr><th>UID</th><td><?php echo htmlspecialchars($uidNumber); ?></td></tr>
                                            <tr><th>Masked Mobile</th><td><?php echo htmlspecialchars($resdata['maskedMobileNumber'] ?? ''); ?></td></tr>
                                            <tr><th>Gender</th><td><?php echo htmlspecialchars($resdata['gender'] ?? ''); ?></td></tr>
                                            <tr><th>Age Band</th><td><?php echo htmlspecialchars($resdata['ageBand'] ?? ''); ?></td></tr>
                                            <tr><th>Address</th><td><?php echo htmlspecialchars($resdata['address'] ?? ''); ?></td></tr>
                                            <?php if (
                                                isset($resdata['uidMessage']) &&
                                                strpos($resdata['uidMessage'], 'requires Document') !== false
                                            ): ?>
                                                <tr>
                                                    <th>Document Update Required</th>
                                                    <td><?php echo htmlspecialchars($resdata['uidMessage']); ?></td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                    <div class="text-end mt-3">
                                        <button class="btn btn-primary" onclick="printVerification()">🖨️ Print</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } ?>

                </div>
            </div>

            <script>
                function printVerification() {
                    var content = document.getElementById("printSection").innerHTML;
                    var printWindow = window.open('', '_blank', 'width=800,height=600');
                    printWindow.document.write(`
                        <html>
                            <head>
                                <title>Aadhaar Mobile Mask Verification Result</title>
                                <style>
                                    body { font-family: Arial, sans-serif; padding: 20px; }
                                    table { width: 100%; border-collapse: collapse; }
                                    th, td { border: 1px solid #000; padding: 8px; text-align: left; }
                                    th { background-color: #f2f2f2; }
                                    h5 { margin-bottom: 20px; }
                                </style>
                            </head>
                            <body>
                                ${content}
                                <script>window.onload = function() { window.print(); };<\/script>
                            </body>
                        </html>
                    `);
                    printWindow.document.close();
                }
            </script>
        </body>
    </div>
</div>

<?php include('footer.php'); ?>

<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>
