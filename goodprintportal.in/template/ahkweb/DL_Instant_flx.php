<?php
include('header.php');

// Variables
$showPdf = false;
$pdfUrl = '';
$pdfDownloadName = '';

if (isset($_POST['b2']) && $_POST['b2'] === "Bittu") {
    // Inputs
    $dl  = mysqli_real_escape_string($ahk_conn, $_POST['dl']);
    $dob = mysqli_real_escape_string($ahk_conn, $_POST['dob']); // Must be DD-MM-YYYY

    // Generate Application Number
    $application_no = "FLX_" . rand(100000, 999999);

    // Pricing & Wallet Logic
    $price_row = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT price FROM pricing WHERE service_name='Driving_Licence_Pdf'"));
    $fee = $price_row['price'] ?? 0;

    $username = $udata['phone'];
    $wallet   = $udata['balance'];

    if ($wallet >= $fee) {
        $debit_fee = $wallet - $fee;
        if (!preg_match('/^\d{2}-\d{2}-\d{4}$/', $dob)) {
            echo "<script>Swal.fire('Invalid DOB', 'Please enter DOB in DD-MM-YYYY format.', 'warning');</script>";
        } else {
            $url = $pariprint_instant_dl_endpoint . "?" . http_build_query([
                'slug' => 'instant-dl-pdf',
                'dl' => $dl,
                'dob' => $dob,
                'api_key' => $pariprint_api_key
            ]);

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => "",
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => "GET",
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            curl_close($curl);

            $resdata = json_decode($response, true);
            $json_error = json_last_error();

            if ($response === false || !empty($curlError) || $httpCode !== 200) {
                echo "<script>Swal.fire('Connection Error', 'Failed to connect to API Server.', 'error');</script>";
            }
            elseif ($json_error !== JSON_ERROR_NONE) {
                echo "<script>Swal.fire('Response Error', 'Invalid JSON response from API.', 'error');</script>";
            }
            else {
                $status = $resdata['status'] ?? false;
                $message = $resdata['message'] ?? 'Unknown Error or Invalid Details';
                $pdf_link = trim($resdata['pdf_link'] ?? ($resdata['data']['pdf_link'] ?? ''));
                $pdf_base64 = trim($resdata['data']['a4_base64'] ?? ($resdata['data']['pdf_base64'] ?? ($resdata['pdf_base64'] ?? ($resdata['dl_pdf'] ?? ($resdata['pdf'] ?? '')))));
                if ($pdf_link === '' && $pdf_base64 === '') {
                    $pdf_link = trim($resdata['data']['saved_file'] ?? ($resdata['download_url'] ?? ''));
                }
                $isSuccess = (
                    $status === true ||
                    $status === 1 ||
                    $status === 'true' ||
                    $status === 'SUCCESS' ||
                    $status === 'success' ||
                    (isset($resdata['status_code']) && (string)$resdata['status_code'] === '100')
                );

                if ($isSuccess && ($pdf_link !== '' || $pdf_base64 !== '')) {
                    $debit = mysqli_query($ahk_conn, "UPDATE users SET balance = '$debit_fee' WHERE phone = '$username'");

                    if ($debit) {
                        mysqli_query($ahk_conn, "INSERT INTO wallethistory
                            (userid, amount, balance, purpose, status, type)
                            VALUES ('$username', '$fee', '$debit_fee', 'Instant DL Pdf', '1', 'Debit')");
                        $safe_response = mysqli_real_escape_string($ahk_conn, $response);
                        if ($pdf_link !== '') {
                            $pdfUrl = $pdf_link;
                            $pdfStorage = $pdf_link;
                        } else {
                            $cleanBase64 = preg_replace('#^data:application/pdf;base64,#i', '', $pdf_base64);
                            $pdfUrl = 'data:application/pdf;base64,' . $cleanBase64;
                            $pdfStorage = $pdfUrl;
                        }
                        $safe_pdf_link = mysqli_real_escape_string($ahk_conn, $pdfStorage);
                        $safe_message = mysqli_real_escape_string($ahk_conn, $message);
                        $insert = mysqli_query($ahk_conn, "INSERT INTO dlprint
                            (application_no, username, dl_no, dob, response, status, message, fee, front, back, pdf)
                            VALUES
                            ('$application_no', '$username', '$dl', '$dob', '$safe_response',
                             'success', '$safe_message', '$fee', '', '', '$safe_pdf_link')");

                        if ($insert) {
                            $showPdf = true;
                            $pdfDownloadName = 'DL_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $dl) . '.pdf';
                            $jsMessage = json_encode($message);
                            $jsDl = json_encode($dl);
                            $jsApp = json_encode($application_no);

                            echo "<script>
                                const successMessage = $jsMessage;
                                const dlNumber = $jsDl;
                                const appNo = $jsApp;
                                Swal.fire('Success', 'DL: ' + dlNumber + ' downloaded successfully. App No: ' + appNo + '\\n' + successMessage, 'success');
                            </script>";
                        } else {
                            echo "<script>Swal.fire('DB Error', 'Failed to save record.', 'error');</script>";
                        }
                    } else {
                        echo "<script>Swal.fire('Wallet Error', 'Balance deduct nahi ho paya.', 'error');</script>";
                    }
                } else {
                    $jsError = json_encode($message);
                    echo "<script>
                        const apiErrorMessage = $jsError;
                        Swal.fire('API Error', apiErrorMessage, 'warning');
                        setTimeout(() => { window.location.href = 'DL_Instant_flx'; }, 3000);
                    </script>";
                }
            }
        }
    } else {
        echo "<script>
            Swal.fire('Insufficient Balance', 'Your wallet balance is low. Please recharge.', 'error');
            setTimeout(() => { window.location.href = 'wallet'; }, 2000);
        </script>";
    }
}
?>


<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">Instant DL Pdf</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="DL_Instant_flx_list" class="btn btn-success">
                    <i class="bi bi-list-ul me-1"></i> All Instant DL Pdf List
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        <div class="container-fluid pt-4 px-4">
            <div class="row">
                <!-- Left Side Form -->
                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <div class="p-3 mb-4 rounded text-white" style="background: linear-gradient(135deg, #0062E6, #33AEFF);">
                                <h3 class="text-center m-0 text-white">
                                    <i class="bi bi-file-earmark-pdf-fill me-2"></i>
                                    <strong>Instant DL Pdf</strong>
                                </h3>
                            </div>
                            <form method="post" id="dlprint">
                                <div class="form-group mb-3">
                                    <label for="dl">Driving Licence Number</label>
                                    <input type="text" class="form-control" name="dl" required placeholder="e.g. MH01234567890">
                                </div>
                                <div class="form-group mb-3">
                                    <label for="dob">D.O.B</label>
                                    <input type="text" class="form-control" name="dob" placeholder="DD-MM-YYYY (e.g. 01-01-1990)" required>
                                </div>
                                
                                <!-- Background and Card Type removed as not supported by new API -->
                                <!-- If you want to keep them for UI, uncomment below -->
                                <!--
                                <div class="form-group mb-3">
                                    <label for="background">Card Background</label>
                                    <select name="background" class="form-control">
                                        <option value="W">White</option>
                                    </select>
                                </div>
                                <div class="form-group mb-3">
                                    <label for="card_type">Card Type</label>
                                    <select name="card_type" class="form-control">
                                        <option value="nochip">Without Chip</option>
                                    </select>
                                </div>
                                -->
                                
                                <input type="hidden" name="b2" value="Bittu">
                                
                                <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center" id="submitBtn">
                                    <span id="btnText">Submit</span>
                                    <span id="btnSpinner" class="spinner-border spinner-border-sm d-none ms-2" role="status" aria-hidden="true"></span>
                                </button>
                                
                                <div class="text-center mt-3">
                                    <div class="fee-container">
                                        <h5 class="text-warning">Application Fee:
                                             <?php  
                                                $price = mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT price FROM pricing WHERE service_name='Driving_Licence_Pdf'")); 
                                                echo "₹" . $price['price'];
                                            ?>
                                        </h5>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Right Side PDF Preview -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-body">
                            <h4 class="card-title text-center">PDF Preview</h4>
                            <?php if ($showPdf && $pdfUrl): ?>
                                <div class="text-end mb-2">
                                    <a href="<?php echo htmlspecialchars($pdfUrl); ?>" download="<?php echo htmlspecialchars($pdfDownloadName ?: ('DL_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $dl) . '.pdf')); ?>" class="btn btn-success btn-sm" target="_blank" rel="noopener noreferrer">
                                        <i class="bi bi-download"></i> Download PDF
                                    </a>
                                </div>
                                <iframe src="<?php echo htmlspecialchars($pdfUrl); ?>" style="width:100%; height:600px;" frameborder="0"></iframe>
                            <?php else: ?>
                                <div class="alert alert-info text-center">
                                    <i class="bi bi-info-circle"></i> No PDF Preview Available. Please submit the form.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .fee-container {
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        padding: 10px 15px;
        max-width: 300px;
        margin: auto;
    }
    .fee-container h5 { color: #ff9800; font-size: 18px; font-weight: 600; margin: 0; }
    #submitBtn .spinner-border { vertical-align: middle; }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('dlprint');
        const submitBtn = document.getElementById('submitBtn');
        const btnText = document.getElementById('btnText');
        const btnSpinner = document.getElementById('btnSpinner');

        form.addEventListener('submit', function () {
            submitBtn.disabled = true;
            btnText.textContent = 'Processing...';
            btnSpinner.classList.remove('d-none');
        });
    });
</script>

<!-- Footer & JS Files -->
<?php 
include('footer.php');
?>
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
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
 $(function() { $(".knob").knob(); });
</script>
<script src="../template/ahkweb/assets/js/index.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/jquery.dataTables.min.js"></script>
<script src="../template/ahkweb/assets/plugins/datatable/js/dataTables.bootstrap5.min.js"></script>
<script>
    $(document).ready(function() { $('#example').DataTable(); });
    $(document).ready(function() {
        var table = $('#example2').DataTable( { lengthChange: false, buttons: [ 'copy', 'excel', 'pdf', 'print'] });
        table.buttons().container().appendTo( '#example2_wrapper .col-md-6:eq(0)' );
    });
</script>
</body>
</html>
