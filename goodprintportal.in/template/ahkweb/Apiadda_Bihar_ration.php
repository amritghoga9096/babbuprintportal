<script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>

<?php
include('header.php');
include('../../includes/config.php');

 $pdfData = '';
 $application_no = '';

// --- API CONFIGURATION (Dynamic) ---
// Ensure these variables are defined in your config or header file
// $auth_url   = "https://apiadda.in/"; 
// $api_key    = "...";
// $secret_key = "...";

if (isset($_POST['submit'])) {
    $district_code = $_POST['district_code'];
    $ration_card_number = $_POST['ration_card_number'];
    $application_no = "FLX_" . rand(100000, 999999);

    $price = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT * FROM pricing WHERE service_name='ration_pdf_flx_print_fee' "));
    $fee = $price['price'];
    $username = $udata['phone'];
    $wallet_amount = $udata['balance'];

    if ($wallet_amount > $fee) {
        $debit_fee = $wallet_amount - $fee;
        
        // Construct Dynamic URL
        $url = $auth_url . "api_service/bihar_rationPDF.php?" . http_build_query([
            'district_code' => $district_code,
            'ration_card_number' => $ration_card_number,
            'api_key' => $api_key,
            'secret_key' => $secret_key
        ]);

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
        $resdata = json_decode($response, true);

        // CORRECTED: Check for 'rc_pdf' key as per actual API response
        if (isset($resdata['statush']) && $resdata['statush'] === true && $resdata['statush__code'] == 100 && !empty($resdata['rc_pdf'])) {
            $pdfData = $resdata['rc_pdf']; // Base64 PDF string

            $debit = mysqli_query($ahk_conn, "UPDATE users SET balance='$debit_fee' WHERE phone='$username'");
            if ($debit) {
                $insert = mysqli_query($ahk_conn, "INSERT INTO rasn_print (application_no, username, rasan_no, status, fee, front, back, pdf) VALUES ('$application_no','$username','$ration_card_number','success', '$fee', '$pdfData','$pdfData','$pdfData');");

                if ($insert) {
                    echo "<script>
                        Swal.fire(
                            'Success',
                            'Ration Card No: $ration_card_number PDF generated successfully!',
                            'success'
                        );
                    </script>";
                }
            }
        } else {
            // Handle Error
            $errorMsg = $resdata['message'] ?? 'Unknown Error or Invalid Response';
            echo "<script>
                Swal.fire(
                    'Error',
                    '" . addslashes($errorMsg) . "',
                    'error'
                );
            </script>";
        }
    } else {
        echo "<script>
            Swal.fire(
                'Oops!',
                'Wallet Balance Insufficient! Please Recharge.',
                'error'
            );
            setTimeout(() => {
                window.location.href = 'wallet.php';
            }, 2000);
        </script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bihar Ration PDF Download</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://jeremyfagis.github.io/dropify/dist/css/dropify.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <style>
        iframe {
            border: 1px solid #ccc;
        }
    </style>
</head>
<body>

<!--start page wrapper -->
<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb with Action Buttons -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">Bihar Ration Pdf</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="Ration_Pdf_flx_list" class="btn btn-success">
                    <i class="bi bi-list-ul me-1"></i> Ration Pdf List
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        <!-- Main Layout -->
        <div class="container-fluid pt-4 px-4">
            <div class="row">
                <!-- Left Side: Form -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <h3 class="card-title text-center text-dark"><strong>Bihar Ration PDF Download</strong></h3>
                            
                            <!-- Powered By Section -->
                            <div class="text-center mt-2 mb-3">
                                <div style="background:#f8f9fa;padding:8px;border-radius:6px;">
                                    <small style="font-weight:600;">
                                        ⚡ Powered by <span style="color:#0d6efd;">APIADDA.IN</span>
                                    </small>
                                </div>
                            </div>

                            <form method="POST">
                                <div class="mb-3">
                                    <label>Select District <span class="text-danger">*</span></label>
                                    <select class="form-control" required name="district_code">
                                        <option value="">-- Select District --</option>
                                        <option value="203">Pashchim Champaran</option>
                                        <option value="204">Purba Champaran</option>
                                        <option value="205">Sheohar</option>
                                        <option value="206">Sitamarhi</option>
                                        <option value="207">Madhubani</option>
                                        <option value="208">Supaul</option>
                                        <option value="209">Araria</option>
                                        <option value="210">Kishanganj</option>
                                        <option value="211">Purnia</option>
                                        <option value="212">Katihar</option>
                                        <option value="213">Madhepura</option>
                                        <option value="214">Saharsa</option>
                                        <option value="215">Darbhanga</option>
                                        <option value="216">Muzaffarpur</option>
                                        <option value="217">Gopalganj</option>
                                        <option value="218">Siwan</option>
                                        <option value="219">Saran</option>
                                        <option value="220">Vaishali</option>
                                        <option value="221">Samastipur</option>
                                        <option value="222">Begusarai</option>
                                        <option value="223">Khagaria</option>
                                        <option value="224">Bhagalpur</option>
                                        <option value="225">Banka</option>
                                        <option value="226">Munger</option>
                                        <option value="227">Lakhisarai</option>
                                        <option value="228">Sheikhpura</option>
                                        <option value="229">Nalanda</option>
                                        <option value="230">Patna</option>
                                        <option value="231">Bhojpur</option>
                                        <option value="232">Buxar</option>
                                        <option value="233">Kaimur (Bhabua)</option>
                                        <option value="234">Rohtas</option>
                                        <option value="235">Jehanabad</option>
                                        <option value="236">Arwal</option>
                                        <option value="237">Aurangabad</option>
                                        <option value="238">Gaya</option>
                                        <option value="239">Nawada</option>
                                        <option value="240">Jamui</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label>Ration Card Number <span class="text-danger">*</span></label>
                                    <input class="form-control" placeholder="Enter Ration Card Number" name="ration_card_number" type="text" required>
                                </div>
                                <div class="text-center">
                                    <button type="submit" name="submit" class="btn btn-primary">Submit</button>
                                     <div class="col-12 ml-2 d-flex justify-content-center">
                                        <div class="fee-container text-center">
                                            <h5 class="text-warning">Application Fee: 
                                            <?php  
                                                $price = mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT price FROM pricing WHERE service_name='ration_pdf_flx_print_fee'")); 
                                                echo "₹" . $price['price'];
                                            ?>
                                            </h5>
                                        </div>
                                    </div>

                                    <style>
                                        .fee-container {
                                            background-color: #fff;
                                            border-radius: 8px;
                                            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                                            padding: 10px 15px;
                                            max-width: 300px;
                                            width: 100%;
                                            margin-top: 10px;
                                            margin-bottom: 10px;
                                        }
                                        .fee-container h5 {
                                            color: #ff9800;
                                            font-size: 18px;
                                            font-weight: 600;
                                            margin: 0;
                                        }
                                    </style>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Right Side: PDF Preview -->
                <div class="col-lg-6 mb-4">
                    <?php if ($pdfData): ?>
                        <div class="card">
                            <div class="card-body">
                                <h4 class="text-center">PDF Preview</h4>
                                <iframe src="data:application/pdf;base64,<?php echo $pdfData; ?>" width="100%" height="600px"></iframe>
                                <div class="text-center mt-3">
                                    <a href="data:application/pdf;base64,<?php echo $pdfData; ?>" class="btn btn-success" download="Ration_<?php echo $application_no; ?>.pdf">Download PDF</a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info text-center">
                            Fill the form to view the PDF preview.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>

<!--end page wrapper -->
<?php 
include('footer.php');
?>
<!-- Bootstrap JS -->
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<!--plugins-->
<script src="../template/ahkweb/assets/js/jquery.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/plugins/chartjs/chart.min.js"></script>
<script src="../template/ahkweb/assets/plugins/vectormap/jquery-jvectormap-2.0.2.min.js"></script>
<script src="../template/ahkweb/assets/plugins/vectormap/jquery-jvectormap-world-mill-en.js"></script>
<script src="../template/ahkweb/assets/plugins/jquery.easy-pie-chart/jquery.easypiechart.min.js"></script>
<script src="../template/ahkweb/assets/plugins/sparkline-charts/jquery.sparkline.min.js"></script>
<script src="../template/ahkweb/assets/plugins/jquery-knob/excanvas.js"></script>
<script src="../template/ahkweb/assets/plugins/jquery-knob/jquery.knob.js"></script>
<script>
 $(function() {
    $(".knob").knob();
});
</script>
<script src="../template/ahkweb/assets/js/index.js"></script>
<!--app JS-->
<script src="../template/ahkweb/assets/js/app.js"></script>
<!-- datatable -->
<script src="../template/ahkweb/assets/plugins/datatable/js/jquery.dataTables.min.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/dataTables.bootstrap5.min.js"></script>
<script>
    $(document).ready(function() {
        $('#example').DataTable();
    });
</script>

<script>
    $(document).ready(function() {
        var table = $('#example2').DataTable( {
            lengthChange: false,
            buttons: [ 'copy', 'excel', 'pdf', 'print']
        } );
     
        table.buttons().container()
            .appendTo( '#example2_wrapper .col-md-6:eq(0)' );
    } );
</script>

</body>
</html>