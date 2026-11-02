<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

// Fetch fee from database
$price = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT * FROM pricing WHERE service_name='ifsc_Verification_fee' "));
$fee = $price['price'];

$resdata = [];
if (isset($_POST['ifsc'])) {
    $ifsc = trim($_POST['ifsc']);
    $appliedby = $udata['phone'];
    $debit_fee = $udata['balance'] - $fee;

    if ($udata['balance'] >= $fee) {
        $api_key = urlencode($flexsecure_key);
        $url = "https://" . $flexsecureapiurl . "/api/v1/ifscVerification?apiKey=$api_key&ifsc=$ifsc";

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
            mysqli_query($ahk_conn, "UPDATE users SET balance=balance-$fee WHERE phone='$appliedby'");
            mysqli_query($ahk_conn, "INSERT INTO `wallethistory`(`userid`, `amount`, `balance`, `purpose`, `status`, `type`) 
                VALUES ('$appliedby','$fee','$debit_fee','IFSC Verification','1','Debit')");
        } else {
            $error_message = $resdata['error'] ?? 'Something went wrong.';
            echo "<script>$(function(){ Swal.fire('Error', '$error_message', 'error') }); setTimeout(() => { window.location=''; }, 5000);</script>";
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
                        <li class="breadcrumb-item active text-muted" aria-current="page">Ifsc Code Verify</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
               
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        <head>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
            <style>.hidden { display: none; }</style>
        </head>

        <body>
            <div class="container-fluid pt-4 px-4">
                <div class="row">

                    <!-- Form Section -->
                    <div class="col-lg-5 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h3 class="card-title text-dark text-center"><strong>IFSC Code Verification</strong></h3>
                                <form action="" method="POST" class="row g-3">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="ifsc">Enter IFSC Code</label>
                                            <input name="ifsc" type="text" id="ifsc" placeholder="Enter IFSC Code" class="form-control" required>
                                            <input type="hidden" name="check" value="ifsc">
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
                            <div class="card" style="background-color: #FCF3CF;" id="printSection">
                                <div class="card-body">
                                    <h5 class="mb-3">IFSC Verification Result</h5>
                                    <table class="table table-bordered table-striped">
                                        <tbody>
                                            <?php
                                            $fields = [
                                                'status' => 'Status',
                                                'bank' => 'Bank',
                                                'branch' => 'Branch',
                                                'ifsc' => 'IFSC',
                                                'address' => 'Address',
                                                'city' => 'City',
                                                'district' => 'District',
                                                'state' => 'State',
                                                'country' => 'Country',
                                                'micr' => 'MICR',
                                                'swift' => 'SWIFT',
                                                'centre' => 'Centre',
                                                'bankcode' => 'Bank Code',
                                                'contact' => 'Contact',
                                                'upi' => 'UPI Enabled',
                                                'imps' => 'IMPS Enabled',
                                                'neft' => 'NEFT Enabled',
                                                'rtgs' => 'RTGS Enabled'
                                            ];

                                            foreach ($fields as $key => $label) {
                                                if (isset($resdata[$key]) && $resdata[$key] !== '' && $resdata[$key] !== null) {
                                                    $value = is_bool($resdata[$key]) ? ($resdata[$key] ? 'Yes' : 'No') : htmlspecialchars($resdata[$key]);
                                                    echo "<tr><th>$label</th><td>$value</td></tr>";
                                                }
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                    <div class="text-end mt-3">
                                        <button class="btn btn-primary" onclick="printVerification()">🖨️ Print</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } ?>

                </div><br><br><br><br>
            </div>

            <script>
                function printVerification() {
                    var content = document.getElementById("printSection").innerHTML;
                    var printWindow = window.open('', '_blank', 'width=800,height=600');
                    printWindow.document.write(`
                        <html>
                            <head>
                                <title>IFSC Verification Result</title>
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
