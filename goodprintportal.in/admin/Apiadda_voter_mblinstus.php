<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

// --- API CONFIGURATION (Dynamic) ---
// Ensure these variables are defined in your config or header file
// $auth_url   = "https://apiadda.in/"; 
// $api_key    = "...";
// $secret_key = "...";

// Fetch fee from database (Table: pricing, Service Name: voter_status_fee)
// Agar database mein entry nahi hai toh default 5 Rs lega
 $priceQuery = mysqli_query($ahk_conn, "SELECT * FROM pricing WHERE service_name='voter_status_fee' ");
 $priceData = mysqli_fetch_assoc($priceQuery);
 $fee = isset($priceData['price']) ? $priceData['price'] : 5; 

 $resdata = [];
if (isset($_POST['epic'])) {
    $epic       = strtoupper(trim($_POST['epic']));
    $appliedby  = $udata['phone'];
    $debit_fee  = $udata['balance'] - $fee;

    if ($udata['balance'] >= $fee) {
        
        // Construct URL dynamically using http_build_query
        $url = $auth_url . "VOTER_PDF/vm_check-status.php?" . http_build_query([
            'api_key'    => $api_key,
            'secret_key' => $secret_key,
            'epic'       => $epic
        ]);

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        $resdata = json_decode($response, true) ?? [];

        // Check if API response is success
        if (isset($resdata['status']) && $resdata['status'] === "success") {
            // Deduct fee
            mysqli_query($ahk_conn, "UPDATE users SET balance=balance-$fee WHERE phone='$appliedby'");
            
            // Update user session balance
            $udata['balance'] = $udata['balance'] - $fee;
            
            // Insert History
            $purpose = 'Voter Status Check (' . $epic . ')';
            mysqli_query($ahk_conn, "INSERT INTO wallethistory(userid, amount, balance, purpose, status, type) 
                VALUES ('$appliedby','$fee','$debit_fee','$purpose','1','Debit')");
        } else {
            // Error from API
            $error_message = $resdata['message'] ?? 'API Error or Voter Not Found.';
            echo "<script>$(function(){ Swal.fire('Error', '$error_message', 'error') }); </script>";
        }
    } else {
        echo "<script>$(function(){ Swal.fire('Wallet Balance is Low!', 'Please Recharge Now!', 'error') }); setTimeout(() => { window.location='wallet.php'; }, 1200);</script>";
    }
}
?>
<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Voter Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">Voter Mobile Status Check</li>
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
        </head>

        <body>
            <div class="container-fluid pt-4 px-4">
                <div class="row">
                    <!-- Form Section -->
                    <div class="col-lg-5 mb-4">
                        <div class="card border-primary">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0 text-center"><i class="fas fa-id-card me-2"></i>Voter Status Search</h5>
                            </div>
                            <div class="card-body">
                                <form action="" method="POST" class="row g-3">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="epic" class="form-label">Enter EPIC Number</label>
                                            <input
                                                name="epic"
                                                type="text"
                                                id="epic"
                                                placeholder="e.g. ABC1234567"
                                                class="form-control form-control-lg text-uppercase"
                                                required
                                                pattern="[A-Za-z]{3}[0-9]{7}"
                                                maxlength="10"
                                                title="Please enter valid EPIC (3 Letters + 7 Digits)"
                                            >
                                            <small class="text-muted">Format: 3 Letters + 7 Digits</small>
                                        </div>

                                        <!-- NOTICE START -->
                                        <div class="alert alert-info mt-3 mb-2 small">
                                            <strong>नोट:</strong><br>
                                            इस सेवा से आप अपने Voter Number डालकर यह चेक कर सकते हैं कि वोटर आईडी के साथ कौन-सा मोबाइल नंबर लिंक है।<br>
                                            मोबाइल नंबर सुरक्षा कारणों से आधा छुपा हुआ (Masked) दिखाया जाएगा।
                                        </div>
                                        <!-- NOTICE END -->

                                        <hr>
                                        <div class="row mt-3 align-items-center">
                                            <div class="col-md-6">
                                                <div class="input-group">
                                                    <span class="input-group-text">Fee</span>
                                                    <input class="form-control fw-bold text-success" value="₹ <?php echo $fee; ?>" readonly>
                                                </div>
                                            </div>
                                            <div class="col-md-6 text-end">
                                                <button class="btn btn-success btn-lg w-100" name="submit" id="submit">
                                                    <i class="fa fa-search"></i> Search
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <div class="card-footer text-center bg-light">
                                <small class="text-muted">Wallet Balance: ₹ <?php echo $udata['balance']; ?></small>
                            </div>
                        </div>
                    </div>

                    <!-- Result Section -->
                    <?php if (isset($resdata['status']) && $resdata['status'] === "success") { ?>
                        <div class="col-lg-7 col-md-6 col-sm-6">
                            <div class="card" style="background-color: #E8F8F5;" id="printSection">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0 text-center">Voter Verification Result</h5>
                                </div>
                                <div class="card-body">
                                    <div class="text-center mb-3">
                                        <h4 class="text-primary"><?php echo htmlspecialchars($resdata['full_name']); ?></h4>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($resdata['state_name']); ?></span>
                                    </div>
                                    <table class="table table-bordered table-hover table-striped">
                                        <tbody>
                                            <tr>
                                                <th width="40%">EPIC Number</th>
                                                <td><strong><?php echo htmlspecialchars($resdata['epic_number']); ?></strong></td>
                                            </tr>
                                            <tr>
                                                <th>Full Name (Hindi)</th>
                                                <td><?php echo htmlspecialchars($resdata['full_name_hindi']); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Relative Name</th>
                                                <td><?php echo htmlspecialchars($resdata['relation_name']); ?> (<?php echo htmlspecialchars($resdata['relation_type']); ?>)</td>
                                            </tr>
                                            <tr>
                                                <th>Relative Name (Hindi)</th>
                                                <td><?php echo htmlspecialchars($resdata['relation_name_hindi']); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Date of Birth / Age</th>
                                                <td><?php echo htmlspecialchars($resdata['dob']); ?> (<?php echo htmlspecialchars($resdata['age']); ?> Years)</td>
                                            </tr>
                                            <tr>
                                                <th>Gender</th>
                                                <td><?php echo ($resdata['gender'] == 'M') ? 'Male' : 'Female'; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Mobile Number</th>
                                                <td class="text-danger fw-bold"><?php echo htmlspecialchars($resdata['linked_mobile']); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Mobile Status</th>
                                                <td><span class="badge bg-success"><?php echo htmlspecialchars($resdata['mobile_status_msg']); ?></span></td>
                                            </tr>
                                            <tr>
                                                <th>Active Status</th>
                                                <td>
                                                    <?php if($resdata['is_active']) { ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php } else { ?>
                                                        <span class="badge bg-danger">Inactive</span>
                                                    <?php } ?>
                                                </td>
                                            <!--</tr>-->
                                            <!--<tr class="table-info">-->
                                            <!--    <th>Charge Deducted</th>-->
                                            <!--    <td>₹ <?php echo htmlspecialchars($resdata['charge_deducted']); ?></td>-->
                                            <!--</tr>-->
                                        </tbody>
                                    </table>
                                    <div class="text-end mt-3">
                                        <button class="btn btn-primary" onclick="printVerification()">🖨️ Print Result</button>
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
                <title>Voter Status Report</title>
                <style>
                    body { font-family: 'Arial', sans-serif; padding: 20px; }
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    th, td { border: 1px solid #000; padding: 10px; text-align: left; }
                    th { background-color: #f0f0f0; width: 40%; }
                    h5, h4 { text-align: center; }
                    .badge { padding: 5px 10px; border-radius: 5px; }
                    .bg-success { background-color: #28a745; color: #fff; }
                    .text-danger { color: #dc3545; font-weight: bold; }
                </style>
            </head>
            <body>
                <h4>Voter Verification Report</h4>
                ${content}
                <script>
                    window.onload = function() {
                        window.print();
                        window.onfocus = function() { window.close(); }
                    };
                <\/script>
            </body>
        </html>
    `);
    printWindow.document.close();
}
</script>
</html>