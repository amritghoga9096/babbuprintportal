<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

// fetch fee from database
$price = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT * FROM pricing WHERE service_name='janadhar_find_fee'"));
$fee = $price['price'];

$resdata = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['uidNumber'])) {
    $uidNumber = mysqli_real_escape_string($ahk_conn, $_POST['uidNumber']);
    $opid = mysqli_real_escape_string($ahk_conn, $_POST['opid'] ?? '');
    $appliedby = $udata['phone'];
    $debit_fee = $udata['balance'] - $fee;

    if ($udata['balance'] >= $fee) {

        $apikey = urlencode($flexsecure_key);
        $url = "https://" . htmlspecialchars($flexsecureapiurl) . "/serviceApi/FlexSecureV1/janadhar/janadharFind.php?apiKey=$apikey&uidNumber=$uidNumber";

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
        $errore = $resdata['statusMessage'] ?? 'Something went wrong.';

        if ($status === "100") {
            $janaadhaar = $resdata['janaadhaar'] ?? '';
            $uid = $resdata['uid'] ?? '';

            $debit = mysqli_query($ahk_conn, "UPDATE users SET balance = balance - $fee WHERE phone = '$appliedby'");
            $updatehistory = mysqli_query($ahk_conn, "INSERT INTO `wallethistory`(`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES ('$appliedby','$fee','$debit_fee','Janaadhaar Number Find','1','Debit')");

        } else {
            echo "<script>$(function(){ Swal.fire('Error', '$errore', 'error'); }); setTimeout(() => { window.location=''; }, 3000);</script>";
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
                        <li class="breadcrumb-item active text-muted" aria-current="page">Jan Aadhaar Find</li>
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
                <!-- Form Section -->
                <div class="col-lg-5 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <h3 class="card-title text-dark text-center"><strong>Aadhaar To JanAadhaar Find</strong></h3>
                        </div>
                        <div class="card-body">
                            <a href="janAadhar_PDF" class="btn btn-dark w-100 mb-3">Rajasthan JanAadhaar Pdf Download</a>
                            <form action="" method="POST" class="row g-3">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="uidNumber">Enter Aadhaar Number</label>
                                        <input name="uidNumber" type="text" id="uidNumber" maxlength="12" minlength="12" placeholder="Enter Aadhaar Number" class="form-control" required>
                                    </div>
                                    <hr>
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <input class="form-control" value="Fee ₹ <?php echo htmlspecialchars($fee); ?>" readonly>
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

                <!-- Response Section -->
                <?php if (!empty($resdata) && $resdata['status'] === "100") { ?>
                    <div class="col-lg-7">
                        <div class="card" style="background-color: #FCF3CF;" id="printSection">
                            <div class="card-body">
                                <h5 class="mb-3">JanAadhaar Details</h5>
                                <table class="table table-bordered table-striped">
                                    <tbody>
                                        <tr>
                                            <th>Aadhaar Number</th>
                                            <td><?php echo htmlspecialchars($resdata['uid']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>JanAadhaar Number</th>
                                            <td><?php echo htmlspecialchars($resdata['janaadhaar']); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>
</html>
