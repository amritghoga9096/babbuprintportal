<?php

ob_start();

include('header.php');

// Variables
 $showPdf = false;
 $pdfUrl = '';
 $dl = ''; // Define outside so it can be used in download button

// Fetch Fee
 $fee = 0;
 $priceQuery = mysqli_query($ahk_conn, "SELECT price FROM pricing WHERE service_name='Driving_Licence_Pdf' LIMIT 1");
if($priceQuery && mysqli_num_rows($priceQuery) > 0){
    $fee = mysqli_fetch_assoc($priceQuery)['price'];
}

if (isset($_POST['b2']) && $_POST['b2'] === "Bittu") {
    
    // Inputs
    $dl  = mysqli_real_escape_string($ahk_conn, trim($_POST['dl']));
    $dob = mysqli_real_escape_string($ahk_conn, trim($_POST['dob'])); // Must be DD-MM-YYYY

    // Generate Application Number
    $application_no = "DLB_" . rand(100000, 999999);

    // Wallet Logic
    $username = $udata['phone'];
    $wallet   = $udata['balance'];

    if ($wallet >= $fee) {
        $new_balance = $wallet - $fee;

        // ====================== API URL (Dynamic from DB) ======================
        $url = $auth_url . "api_service/dlprint_Blue.php?" . http_build_query([
            'dl_number'  => $dl,
            'dob'        => $dob,
            'api_key'    => $api_key,
            'secret_key' => $secret_key
        ]);

        // cURL Request
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => "",
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        // Response Parsing
        if ($httpCode !== 200 || !$response) {
            echo "<script>Swal.fire('Connection Error', 'Failed to connect to API Server.', 'error');</script>";
        } 
        else {
            // Decode JSON (We strictly expect JSON now)
            $resdata = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo "<script>Swal.fire('Response Error', 'API did not return valid JSON.', 'error');</script>";
            } 
            elseif (isset($resdata['status']) && $resdata['status'] == 'success' && !empty($resdata['pdf_base64'])) {
                
                $pdf_base64 = $resdata['pdf_base64'];
                $pdfRaw = base64_decode($pdf_base64);

                // 1. Debit Wallet
                mysqli_query($ahk_conn, "UPDATE users SET balance='$new_balance' WHERE phone='$username'");
                mysqli_query($ahk_conn, "INSERT INTO wallethistory (userid, amount, balance, purpose, status, type) VALUES ('$username','$fee','$new_balance','DL Blue Print','1','Debit')");

                // 2. Save Base64 to Database
                $insert = mysqli_query($ahk_conn, "INSERT INTO dlprint2 (application_no, username, dl_no, dob, status, fee, pdf) VALUES ('$application_no','$username','$dl','$dob','success','$fee','$pdf_base64')");

                if ($insert) {
                    
                    // 3. Create Temporary Physical PDF File for Preview (Fixes Mobile/Lite View Issue)
                    $temp_dir = "uploads/dl_temp/";
                    if (!is_dir($temp_dir)) {
                        mkdir($temp_dir, 0777, true);
                    }
                    $file_name = $temp_dir . "DL_Blue_" . $dl . "_" . time() . ".pdf";
                    
                    // Save PDF to server
                    file_put_contents($file_name, $pdfRaw);

                    $showPdf = true;
                    $pdfUrl = $file_name; // Give physical path to iframe

                    echo "<script>Swal.fire('Success', 'DL fetched! Scroll down to preview.', 'success');</script>";
                } else {
                    echo "<script>Swal.fire('DB Error', 'Record not saved. Error: ".mysqli_error($ahk_conn)."', 'error');</script>";
                }
            } else {
                // Failure Case
                $error_msg = isset($resdata['message']) ? $resdata['message'] : 'Unknown Error or Invalid Details';
                echo "<script>
                    Swal.fire('API Error', '" . addslashes($error_msg) . "', 'warning');
                </script>";
            }
        }
    } else {
        echo "<script>
            Swal.fire('Insufficient Balance', 'Your wallet balance is low. Please recharge.', 'error');
        </script>";
    }
}
?>

<!-- Master CSS for Stunning UI -->
<style>
    .dl-wrapper {
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        min-height: 80vh;
        padding: 2rem 0;
    }
    .glass-card {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.5);
        border-radius: 20px;
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.1);
        overflow: hidden;
    }
    .card-header-gradient {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 1.5rem;
        color: white;
    }
    .form-control.custom-input {
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        padding: 12px 15px 12px 45px;
        font-size: 15px;
        transition: all 0.3s ease;
        background-color: #f8fafc;
    }
    .form-control.custom-input:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15);
        background-color: #fff;
    }
    .input-icon {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 18px;
        z-index: 5;
    }
    .btn-download {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        border-radius: 12px;
        padding: 14px;
        font-size: 16px;
        font-weight: 600;
        color: white;
        transition: transform 0.2s, box-shadow 0.2s;
        width: 100%;
    }
    .btn-download:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        color: white;
    }
    .btn-download:disabled { opacity: 0.8; }
    
    .fee-badge {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        color: white;
        padding: 10px 20px;
        border-radius: 30px;
        font-weight: 700;
        font-size: 18px;
        display: inline-block;
        box-shadow: 0 4px 15px rgba(245, 87, 108, 0.3);
    }
    .pdf-preview-box {
        border: 3px dashed #cbd5e1;
        border-radius: 16px;
        background: #f1f5f9;
        overflow: hidden;
    }
    .pdf-preview-box iframe {
        width: 100%;
        height: 75vh;
        border: none;
    }
    .btn-action-success {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        border: none;
        border-radius: 8px;
        color: white;
        font-weight: 600;
        box-shadow: 0 4px 10px rgba(56, 239, 125, 0.3);
    }
    .btn-action-success:hover { color: white; transform: translateY(-1px); }
</style>

<div class="dl-wrapper">
    <div class="container">
        
        <!-- Breadcrumb -->
        <div class="d-flex justify-content-between align-items-center mb-4 px-2">
            <h4 class="text-dark fw-bold mb-0">DL Blue Print Service</h4>
            <div>
                <a href="DL_Instant_flx_blue_list" class="btn btn-sm btn-light shadow-sm border px-3">
                    <i class="bi bi-list-ul me-1"></i> All Records
                </a>
                <a href="javascript:history.back()" class="btn btn-sm btn-light shadow-sm border px-3 ms-2">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <div class="row g-4">
            
            <!-- LEFT: FORM CARD -->
            <div class="col-lg-4">
                <div class="glass-card">
                    <div class="card-header-gradient text-center">
                        <i class="bi bi-file-earmark-pdf-fill fs-1 mb-2 d-block"></i>
                        <h5 class="mb-0 fw-bold">Get DL Blue Print</h5>
                        <small class="opacity-75">Enter details to generate PDF</small>
                    </div>
                    
                    <div class="p-4">
                        <form method="post" id="dlprint">
                            <!-- DL Number -->
                            <div class="mb-3">
                                <label class="form-label text-dark fw-semibold">DL Number</label>
                                <div class="position-relative">
                                    <i class="bi bi-upc-scan input-icon"></i>
                                    <input type="text" class="form-control custom-input" name="dl" required placeholder="e.g. BR2220250011816" autocomplete="off">
                                </div>
                            </div>

                            <!-- DOB -->
                            <div class="mb-4">
                                <label class="form-label text-dark fw-semibold">Date of Birth</label>
                                <div class="position-relative">
                                    <i class="bi bi-calendar3 input-icon"></i>
                                    <input type="text" class="form-control custom-input" name="dob" placeholder="DD-MM-YYYY (e.g. 01-01-2000)" required>
                                </div>
                            </div>
                            
                            <input type="hidden" name="b2" value="Bittu">
                            
                            <!-- Submit Button -->
                            <button type="submit" class="btn btn-download" id="submitBtn">
                                <span id="btnText"><i class="bi bi-cloud-arrow-down me-2"></i>Submit & Preview</span>
                                <span id="btnSpinner" class="d-none">
                                    <span class="spinner-border spinner-border-sm me-2" role="status"></span> Generating...
                                </span>
                            </button>

                            <!-- Footer Info -->
                            <div class="text-center mt-4">
                                <div class="fee-badge">₹ <?php echo htmlspecialchars($fee); ?></div>
                                <div class="mt-3">
                                    <small class="text-muted d-block">⚡ Powered by <b class="text-primary">APIADDA.IN</b></small>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- RIGHT: PDF PREVIEW (LITE VIEW) -->
            <div class="col-lg-8">
                <div class="glass-card h-100">
                    <div class="p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark fw-bold mb-0"><i class="bi bi-eye me-2 text-primary"></i>PDF Preview</h5>
                            <?php if ($showPdf && $pdfUrl): ?>
                                <a href="<?php echo $pdfUrl; ?>" download="DL_Blue_<?php echo $dl; ?>.pdf" class="btn btn-action-success btn-sm px-3 py-2">
                                    <i class="bi bi-download me-1"></i> Download PDF
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="pdf-preview-box">
                            <?php if ($showPdf && $pdfUrl): ?>
                                <!-- Real PDF File loaded here, works on Mobile & Desktop -->
                                <iframe src="<?php echo $pdfUrl; ?>"></iframe>
                            <?php else: ?>
                                <!-- Empty State -->
                                <div class="text-center py-5">
                                    <i class="bi bi-file-earmark-pdf text-muted" style="font-size: 5rem; opacity: 0.3;"></i>
                                    <h5 class="text-muted mt-3">No PDF Generated Yet</h5>
                                    <p class="text-muted small">Fill in the form and click 'Submit & Preview' to view your Driving Licence PDF here.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Loader Script -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('dlprint');
    const submitBtn = document.getElementById('submitBtn');
    const btnText = document.getElementById('btnText');
    const btnSpinner = document.getElementById('btnSpinner');

    form.addEventListener('submit', function () {
        submitBtn.disabled = true;
        btnText.classList.add('d-none');
        btnSpinner.classList.remove('d-none');
    });
});
</script>

<?php include('footer.php'); ?>

<!-- Your Specific Template JS Files -->
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