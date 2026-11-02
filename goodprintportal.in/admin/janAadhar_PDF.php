<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

// Fetch fee from database
$price = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT * FROM pricing WHERE service_name='janAaadhar_PDF_fee'"));
$fee = $price['price'];

$resdata = []; // initialize response array

if ($_POST['janaadhar']) {
    $janaadhar = $_POST['janaadhar'];
    $opid = $_POST['opid'] ?? '';
    $appliedby = $udata['phone'];
    $debit_fee = $udata['balance'] - $fee;

    if ($udata['balance'] >= $fee) {
        $apikey = urlencode($flexsecure_key);
        $url = "https://" . htmlspecialchars($flexsecureapiurl) . "/serviceApi/FlexSecureV1/janadhar/janadharPdf.php?apiKey=$apikey&janaadhar=$janaadhar";
        
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

        $resdata = json_decode($response, true) ?? [];

        $status = $resdata['status'] ?? '';
        $statusMessage = $resdata['statusMessage'] ?? '';
        $uid = $resdata['uid'] ?? '';
        $janaadhaar = $resdata['janaadhaar'] ?? '';
        $pdf = $resdata['pdf'] ?? '';

        if ($status !== "100") {
            echo "<script>$(function(){ Swal.fire('$statusMessage', 'Something went wrong.', 'error') }); setTimeout(() => { window.location=''; }, 5000);</script>";
        } else {
            $debit = mysqli_query($ahk_conn, "UPDATE users SET balance=balance-$fee WHERE phone='$appliedby'");
            $updatehistory = mysqli_query($ahk_conn, "INSERT INTO `wallethistory`(`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES ('$appliedby','$fee','$debit_fee','JanAadhar PDF','1','Debit')");
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
                        <li class="breadcrumb-item active text-muted" aria-current="page">Jan Aadhaar Pdf</li>
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

<div class="container-fluid pt-4 px-4">
    <div class="row">
        <!-- Left Side: Form Section (35%) -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-body">
                    <h3 class="card-title text-dark text-center"><strong>Rajasthan JanAadhaar Pdf</strong></h3>
                </div>
                <div class="card-body">
                    <a href="janAaadhar_Find" class="btn btn-dark w-100 mb-3">Aadhaar To JanAadhaar Find</a>
                    <form action="" method="POST" class="row g-3">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="janaadhar">Enter JanAaadhar Number</label>
                                <input name="janaadhar" type="text" id="janaadhar" maxlength="20" minlength="9" placeholder="Enter JanAaadhar Number" class="form-control" required>
                            </div>
                            <hr>
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <input class="form-control" value="Fee ₹ <?php echo $price['price']; ?>" readonly>
                                </div>
                                <div class="col-md-6 text-end">
                                    <button class="btn btn-success" name="submit" id="submit"><i class="fa fa-check-circle"></i> Submit</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Side: PDF Preview + Details (65%) -->
        <?php if (!empty($resdata) && $resdata['status'] === "100") { ?>
            <div class="col-lg-8 col-md-8">
                <div class="card mb-4" style="background-color: #FCF3CF;" id="printSection">
                    <div class="card-body">
                        <h5 class="mb-3">JanAaadhar Details</h5>
                        <table class="table table-bordered table-striped">
                            <tbody>
                                <tr>
                                    <th>UID</th>
                                    <td><?php echo htmlspecialchars($resdata['uid']); ?></td>
                                </tr>
                                <tr>
                                    <th>JanAadhar Number</th>
                                    <td><?php echo htmlspecialchars($resdata['janaadhaar']); ?></td>
                                </tr>
                                <tr>
                                    <th>View / Download</th>
                                    <td>
                                        <a href="<?php echo htmlspecialchars($resdata['pdf']); ?>" 
                                           download="<?php echo htmlspecialchars($resdata['janaadhaar']); ?>.pdf" 
                                           class="btn btn-sm btn-success">
                                            Download PDF
                                        </a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- PDF Preview -->
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-center mb-3">PDF Preview</h6>
                        <iframe src="<?php echo htmlspecialchars($resdata['pdf']); ?>#toolbar=0" 
                                width="100%" 
                                height="600px" 
                                style="border: 1px solid #ccc;">
                        </iframe>
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
