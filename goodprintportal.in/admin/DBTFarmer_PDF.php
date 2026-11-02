<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

// fetch fee from databasee 
$price = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT * FROM pricing WHERE service_name='dbt_farmer_fee' "));
$fee = $price['price'];
    
if ($_POST['inputNumber']) {
    $inputNumber = $_POST['inputNumber'];
    $opid = $_POST['opid'];
    $appliedby = $udata['phone'];
    $debit_fee =  $udata['balance'] - $fee;

    if ($udata['balance'] >= $fee) {
        
        $api_key =   urlencode($flexsecure_key); // Replace with actual API key                  // enter Aadhar Api Key here 
        
        $url = "https://" . htmlspecialchars($flexsecureapiurl) . "/api/v1/dbtAgricultureVerification.php?apiKey=$api_key&inputNumber=$inputNumber";
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        $resdata = json_decode($response, true) ?? '';

        $regNumber = $resdata['regNumber'] ?? '';
        $name = $resdata['name'] ?? '';
        $pdf = $resdata['pdf'] ?? '';
        $status = $resdata['status'] ?? '';
        $errore = $resdata['error'] ?? '';

        if ($errore) {
            echo "<script>$(function(){ Swal.fire('$errore', 'Someting Wrong.', 'error') }); setTimeout(() => { window.location=''; }, 5000);</script>";
        } elseif ($resdata['status'] === "100") {
            $debit = mysqli_query($ahk_conn, "UPDATE users SET balance=balance-$fee WHERE phone='$appliedby'");
            $updatehistory = mysqli_query($ahk_conn, "INSERT INTO `wallethistory`(`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES ('$appliedby','$fee','$debit_fee','DBT Farmer PDF','1','Debit')");
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
                        <li class="breadcrumb-item active text-muted" aria-current="page">Bihar Farmer Pdf</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
               
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
        <!-- End Breadcrumb -->

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
        <!-- Left Side: Form Section (40%) -->
        <div class="col-lg-5 mb-4">
            <div class="card">
                <div class="card-body">
                    <h3 class="card-title text-dark text-center">
                        <strong>DBT Agriculture Registration PDF</strong>
                    </h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-danger" role="alert">
                        Bihar
                        <a href="" class="alert-link">DBT Agriculture Registration PDF........</a>
                    </div>
                    <form action="" method="POST" class="row g-3">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="inputNumber">Enter Mobile/ Aaadhar/ Registration Number</label>
                                <input name="inputNumber" type="text" id="inputNumber" maxlength="20" minlength="10" placeholder="Enter Mobile/ Aaadhar/ Registration Number" class="form-control" required>
                            </div>
                            <hr>
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <input class="form-control" value="Fee ₹ <?php echo $price['price']; ?>" readonly>
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

        <!-- Right Side: PDF Preview & Download (60%) -->
        <?php if ($resdata['status'] === "100") { ?>
        <div class="col-lg-7 col-md-7 col-sm-12">
            <div class="card" style="background-color: #FCF3CF;" id="printSection">
                <div class="card-body">
                    <h5 class="mb-3">Bihar Farmer Details</h5>
                    <table class="table table-bordered table-striped">
                        <tbody>
                            <tr>
                                <th>Registration Number</th>
                                <td><?php echo htmlspecialchars($regNumber); ?></td>
                            </tr>
                            <tr>
                                <th>Farmer Name</th>
                                <td><?php echo htmlspecialchars($name); ?></td>
                            </tr>
                            <tr>
                                <th>Download PDF</th>
                                <td>
                                    <a href="<?php echo htmlspecialchars($resdata['pdf']); ?>" 
                                       download="<?php echo htmlspecialchars($regNumber); ?>.pdf" 
                                       class="btn btn-sm btn-success" target="_blank">
                                        Download PDF
                                    </a>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- PDF Preview Section -->
                    <div class="mt-4">
                        <h6>PDF Preview:</h6>
                        <iframe src="<?php echo htmlspecialchars($resdata['pdf']); ?>" 
                                width="100%" 
                                height="500px" 
                                style="border: 1px solid #ccc;">
                        </iframe>
                    </div>
                </div>
            </div>
        </div>
        <?php } ?>
    </div>
</div>


<?php include('footer.php'); ?>
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>
</html>
