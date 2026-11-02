<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

// fetch fee from database
$price = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT * FROM pricing WHERE service_name='agri_stack_fee' "));
$fee = $price['price'];

$resdata = [];
if (isset($_POST['uidNumber'])) {
    $uidNumber = trim($_POST['uidNumber']);
    $appliedby = $udata['phone'];
    $debit_fee = $udata['balance'] - $fee;

    if ($udata['balance'] >= $fee) {
        $api_key = urlencode($flexsecure_key);
        $url = "https://" . $flexsecureapiurl . "/api/v1/AgriStak/checkStatus.php?apiKey=$api_key&uidNumber=$uidNumber";

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        $resdata = json_decode($response, true) ?? [];

        if (isset($resdata['status']) && $resdata['status'] === "100") {
            // Deduct fee and record transaction
            mysqli_query($ahk_conn, "UPDATE users SET balance=balance-$fee WHERE phone='$appliedby'");
            mysqli_query($ahk_conn, "INSERT INTO `wallethistory`(`userid`, `amount`, `balance`, `purpose`, `status`, `type`) 
                VALUES ('$appliedby','$fee','$debit_fee','Aadhaar AgriStak Status Check','1','Debit')");
        } else {
            $error_message = $resdata['error'] ?? 'Something went wrong.';
            echo "<script>$(function(){ Swal.fire('Error', '$error_message', 'error') }); setTimeout(() => { window.location=''; }, 500000);</script>";
        }
    } else {
        echo "<script>$(function(){ Swal.fire('Wallet Balance is Low!', 'Please Recharge Now!', 'error') }); setTimeout(() => { window.location='wallet.php'; }, 1200);</script>";
    }
}
?>
<!--Created By FlexSecure (Flex4upoint)-->
<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb with Action Buttons -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">AgriStack Status</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
               
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        <!-- Head Content -->
        <head>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
            <style>
                .hidden {
                    display: none;
                }
            </style>
        </head>

        <body>
            <div class="container-fluid pt-4 px-4">
                <div class="row">

                    <!-- Form Section -->
                    <div class="col-lg-5 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h3 class="card-title text-dark text-center"><strong>All State AgriStak Status Check</strong></h3>
                                <form action="" method="POST" class="row g-3">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="uidNumber">Enter Aadhaar Number</label>
                                            <input
    name="uidNumber"
    type="text"
    id="uidNumber"
    placeholder="Enter Aadhaar Number"
    class="form-control"
    required
    pattern="\d{12}"
    maxlength="12"
    minlength="12"
    title="Please enter a valid 12-digit Aadhaar number"
/>

                                            <input type="hidden" name="check" value="uid_agristak">
                                        </div>
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
                            <div class="card" style="background-color: #E8F8F5;" id="printSection">
                                <div class="card-body">
                                    <h5 class="mb-3">UID AgriStak Verification Result</h5>
                                    <table class="table table-bordered table-striped">
                                        <tbody>
                                            <tr><th>Status</th><td><?php echo htmlspecialchars($resdata['statusMessage'] ?? ''); ?></td></tr>
                                            <tr><th>Aadhaar</th><td><?php echo htmlspecialchars($resdata['uid'] ?? ''); ?></td></tr>
                                            <tr><th>Approval Status</th><td style="color: <?php echo htmlspecialchars($resdata['approvalStatusColor'] ?? '#000'); ?>"><?php echo htmlspecialchars($resdata['approvalStatus'] ?? ''); ?></td></tr>
                                            <tr><th>Farmer Name</th><td><?php echo htmlspecialchars($resdata['farmerName'] ?? ''); ?></td></tr>
                                            <tr><th>Farmer Number</th><td><?php echo htmlspecialchars($resdata['farmerNumber'] ?? ''); ?></td></tr>
                                            <tr><th>Central ID</th><td><?php echo htmlspecialchars($resdata['centralId'] ?? ''); ?></td></tr>
                                            <tr><th>Enrollment Date</th><td><?php echo htmlspecialchars($resdata['enrollmentDate'] ?? ''); ?></td></tr>
                                            <tr><th>Verified On</th><td><?php echo htmlspecialchars($resdata['verifiedOn'] ?? ''); ?></td></tr>
                                            <tr><th>Verification Remarks</th><td><?php echo htmlspecialchars($resdata['verificationRemarks'] ?? ''); ?></td></tr>
                                            <tr><th>Registration Status</th><td><?php echo htmlspecialchars($resdata['registrationStatus'] ?? ''); ?></td></tr>
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
                <br><br><br><br>
            </div>

            <script>
                function printVerification() {
                    var content = document.getElementById("printSection").innerHTML;
                    var original = document.body.innerHTML;
                    document.body.innerHTML = content;
                    window.print();
                    document.body.innerHTML = original;
                }
            </script>
        </body>
    </div>
</div>


<?php include('footer.php'); ?>


<!-- Required JS -->
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>

<!-- Print Script -->
<script>
function printVerification() {
    var content = document.getElementById("printSection").innerHTML;
    var printWindow = window.open('', '_blank', 'width=800,height=600');
    printWindow.document.write(`
        <html>
            <head>
                <title>Aadhaar PAN Verification Result</title>
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
                <script>
                    window.onload = function() {
                        window.print();
                    };
                <\/script>
            </body>
        </html>
    `);
    printWindow.document.close();
}
</script>

</html>
