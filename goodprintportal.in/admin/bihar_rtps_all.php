<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

// fetch fee from database
$price = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT * FROM pricing WHERE service_name='bihar_rtps_all' "));
$fee = $price['price'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $applicationNumber = $_POST['applicationNumber'] ?? '';
    $name = $_POST['name'] ?? '';
    $appliedby = $udata['phone'];
    $debit_fee =  $udata['balance'] - $fee;

    if ($udata['balance'] >= $fee) {
        
        $api_key = urlencode($flexsecure_key); // Your API key
        $api_url = htmlspecialchars($flexsecureapiurl);

        // Build URL with parameters, urlencode name and applicationNumber
        $url = "https://$api_url/api/v1/RtpsCertificate-Br.php?apiKey=$api_key&applicationNumber=" . urlencode($applicationNumber) . "&name=" . urlencode($name);

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
        $resName = $resdata['name'] ?? '';
        $resApplicationNumber = $resdata['applicationNumber'] ?? '';
        $pdf_base64 = $resdata['pdf'] ?? '';
        $error = $resdata['error'] ?? '';

        if ($error) {
            echo "<script>
                    $(function(){ Swal.fire('Error', '".htmlspecialchars($error)."', 'error') });
                    setTimeout(() => { window.location=''; }, 5000);
                  </script>";
        } elseif ($status === "100") {
            // Debit user wallet
            mysqli_query($ahk_conn, "UPDATE users SET balance=balance-$fee WHERE phone='$appliedby'");
            mysqli_query($ahk_conn, "INSERT INTO `wallethistory`(`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES ('$appliedby','$fee','$debit_fee','Bihar RTPS PDF','1','Debit')");
        } else {
            echo "<script>
                    $(function(){ Swal.fire('Error', '".htmlspecialchars($statusMessage)."', 'error') });
                    setTimeout(() => { window.location=''; }, 5000);
                  </script>";
        }
    } else {
        echo "<script>
                $(function(){ Swal.fire('Wallet Balance is Low!', 'Please Recharge Now!', 'error') });
                setTimeout(() => { window.location='wallet.php'; }, 1200);
              </script>";
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
                        <li class="breadcrumb-item active text-muted" aria-current="page">Bihar Rtps Verify</li>
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
                .hidden { display: none; }
                iframe {
                    border: 1px solid #ccc;
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
                                    <strong>Bihar RTPS PDF</strong>
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-danger" role="alert">
                                    Bihar
                                    <a href="" class="alert-link">Bihar RTPS PDF........</a>
                                </div>
                                <form action="" method="POST" class="row g-3">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="applicationNumber">Application Number</label>
                                            <input name="applicationNumber" type="text" id="applicationNumber" maxlength="50" placeholder="Enter Application Number" class="form-control" required>
                                        </div>
                                        <div class="form-group mt-3">
                                            <label for="name">Name</label>
                                            <input name="name" type="text" id="name" maxlength="100" placeholder="Enter Name" class="form-control" required>
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
                    <?php if (!empty($resdata) && $status === "100") { 
                        // Remove "data:application/pdf;base64," prefix if present
                        $pdf_data = preg_replace('/^data:application\/pdf;base64,/', '', $pdf_base64);
                        // Create a temp PDF file for preview or embed base64 directly
                    ?>
                    <div class="col-lg-7 col-md-7 col-sm-12">
                        <div class="card" style="background-color: #FCF3CF;" id="printSection">
                            <div class="card-body">
                                <h5 class="mb-3">Bihar Farmer Details</h5>
                                <table class="table table-bordered table-striped">
                                    <tbody>
                                        <tr>
                                            <th>Application Number</th>
                                            <td><?php echo htmlspecialchars($resApplicationNumber); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Farmer Name</th>
                                            <td><?php echo htmlspecialchars($resName); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Download PDF</th>
                                            <td>
                                                <a href="data:application/pdf;base64,<?php echo $pdf_data; ?>" 
                                                   download="<?php echo preg_replace('/[^A-Za-z0-9_\-]/', '_', $resApplicationNumber); ?>.pdf" 
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
                                    <iframe src="data:application/pdf;base64,<?php echo $pdf_data; ?>" 
                                            width="100%" 
                                            height="500px">
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
</body>
</html>
