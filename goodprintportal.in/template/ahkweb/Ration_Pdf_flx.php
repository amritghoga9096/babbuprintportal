<?php
include('header.php');

// --- API CONFIGURATION ---
// Make sure these are defined in your config/header.php
// $auth_url   = "https://apiadda.in/";
// $api_key    = "YOUR_API_KEY";
// $secret_key = "YOUR_SECRET_KEY";

 $pdfData = '';
 $application_no = '';
 $showPreview = false;

if (isset($_POST['submit_pdf'])) {
    $docno = trim($_POST['docno']);
    $application_no = "apiadda_" . rand(100000, 999999);

    // Pricing & Balance Logic
    $price = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT * FROM pricing WHERE service_name='ration_pdf_flx_print_fee' "));
    $fee = $price['price'];
    $username = $udata['phone'];
    $wallet_amount = $udata['balance'];

    if ($wallet_amount >= $fee) {
        
        // --- API CALL (V4 for All States) ---
        $apiEndpoint = "api_service_v4/ration_pdf.php";
        
        $url = $auth_url . $apiEndpoint . "?" . http_build_query([
            'ration_card_number' => $docno,
            'api_key'            => $api_key,
            'secret_key'         => $secret_key
        ]);

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_SSL_VERIFYPEER => false,
        ));

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        $resdata = json_decode($response, true);

        // --- RESPONSE HANDLING ---
        // Success: statush_code 100 AND r_card_pdf exists
        if (isset($resdata['statush_code']) && $resdata['statush_code'] == 100 && isset($resdata['r_card_pdf'])) {
            
            // NOTE: Key changed to 'r_card_pdf' based on your API response
            $pdfData = $resdata['r_card_pdf'];
            $debit_fee = $wallet_amount - $fee;

            // Deduct Balance
            $debit = mysqli_query($ahk_conn, "UPDATE users SET balance='$debit_fee' WHERE phone='$username'");
            
            if ($debit) {
                // Insert Record
                $insert = mysqli_query($ahk_conn, "INSERT INTO rasn_print (application_no, username, rasan_no, status, fee, pdf) VALUES ('$application_no','$username','$docno','success', '$fee', '$pdfData')");

                if ($insert) {
                    $showPreview = true;
                    echo "<script>
                        Swal.fire({
                            title: 'Success!',
                            text: 'Ration Card: $docno PDF generated successfully!',
                            icon: 'success',
                            confirmButtonText: 'OK'
                        });
                    </script>";
                }
            }

        } else {
            // Error Handling
            $msg = "Unknown Error";
            if (isset($resdata['message'])) {
                $msg = $resdata['message'];
            } elseif (isset($resdata['msg'])) {
                $msg = $resdata['msg'];
            } elseif ($curlError) {
                $msg = "Connection Failed: " . $curlError;
            } elseif (isset($resdata['statush_code']) && $resdata['statush_code'] != 100) {
                 // Specific check for low balance or other codes
                 $msg = "API Error Code: " . $resdata['statush_code'];
            }

            echo "<script>
                Swal.fire({
                    title: 'Failed',
                    text: '$msg',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            </script>";
        }

    } else {
        echo "<script>
            Swal.fire({
                title: 'Low Balance',
                text: 'Wallet Balance Insufficient! Please Recharge ₹$fee.',
                icon: 'warning',
                confirmButtonText: 'Recharge'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'wallet.php';
                }
            });
        </script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ration PDF Download - All State</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <style>
        body { background-color: #f4f6f9; }
        .service-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            overflow: hidden;
            transition: transform 0.3s ease;
            background: #fff;
        }
        .service-card:hover { transform: translateY(-5px); }
        .card-header-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .form-control-lg {
            padding: 15px;
            border-radius: 10px;
            border: 2px solid #eef2f7;
            font-size: 16px;
            transition: all 0.3s;
        }
        .form-control-lg:focus {
            border-color: #764ba2;
            box-shadow: 0 0 0 0.2rem rgba(118, 75, 162, 0.25);
        }
        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 15px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 18px;
            width: 100%;
            color: white;
            transition: all 0.3s;
        }
        .btn-submit:hover {
            transform: scale(1.02);
            box-shadow: 0 5px 15px rgba(118, 75, 162, 0.4);
            color: white;
        }
        .fee-badge {
            background-color: #fff3cd;
            color: #856404;
            padding: 10px 20px;
            border-radius: 50px;
            font-weight: 700;
            border: 1px solid #ffeeba;
            display: inline-block;
            margin-top: 15px;
        }
        .preview-container iframe {
            border-radius: 10px;
            border: 1px solid #ddd;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .branding {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: #6c757d;
        }
        .branding span {
            color: #764ba2;
            font-weight: 700;
        }
    </style>
</head>
<body>

<!-- Start Page Wrapper -->
<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item"><a href="index"><i class="bx bx-home-alt"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Ration PDF (All State)</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="Ration_Pdf_flx_list" class="btn btn-success btn-sm">
                    <i class="bi bi-list-ul me-1"></i> History
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        <div class="container-fluid pt-4 px-4">
            <div class="row">
                <!-- Left Side: Form -->
                <div class="col-lg-5 mb-4">
                    <div class="card service-card">
                        <div class="card-header-custom">
                            <h3 class="mb-0"><i class="bx bxs-file-pdf me-2"></i>Ration PDF Download</h3>
                            <p class="mb-0 opacity-75">Instant Download for All States</p>
                        </div>
                        <div class="card-body p-4">
                            
                            <div class="alert alert-info bg-light border-0 text-center">
                                <small>💡 Enter your <strong>Ration Card Number</strong> to download the PDF instantly.</small>
                            </div>

                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Ration Card Number <span class="text-danger">*</span></label>
                                    <input type="text" name="docno" class="form-control form-control-lg" placeholder="e.g. 123456789012" required>
                                </div>
                                
                                <!-- Fee Display -->
                                <div class="text-center">
                                    <?php 
                                        $price = mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT price FROM pricing WHERE service_name='ration_pdf_flx_print_fee'")); 
                                        $fee_amt = "₹" . $price['price'];
                                    ?>
                                    <div class="fee-badge">
                                        Application Fee: <?php echo $fee_amt; ?>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <button type="submit" name="submit_pdf" class="btn btn-submit">
                                        <i class="bx bxs-download me-2"></i> Download PDF
                                    </button>
                                </div>
                            </form>

                            <div class="branding">
                                💻 Powered by <span>PARI PRINT</span> &nbsp;|&nbsp; 🚀 Developed by <span>LALA WEB DEVELOPER</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Side: PDF Preview -->
                <div class="col-lg-7 mb-4">
                    <div class="card service-card h-100">
                        <div class="card-body p-4 preview-container">
                            <?php if ($showPreview && !empty($pdfData)): ?>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h4 class="mb-0 fw-bold text-primary">PDF Preview</h4>
                                    <a href="data:application/pdf;base64,<?php echo $pdfData; ?>" class="btn btn-success" download="Ration_<?php echo $application_no; ?>.pdf">
                                        <i class="bx bxs-download"></i> Save File
                                    </a>
                                </div>
                                <iframe src="data:application/pdf;base64,<?php echo $pdfData; ?>" width="100%" height="650px"></iframe>
                            <?php else: ?>
                                <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted">
                                    <i class="bx bxs-file-blank fs-1 mb-3"></i>
                                    <h4>No PDF Generated Yet</h4>
                                    <p>Enter your Ration Card number on the left and click Download to view the preview here.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>

</body>
</html>

<?php include('footer.php'); ?>