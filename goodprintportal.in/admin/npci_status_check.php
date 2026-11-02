<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

// --- API CONFIGURATION ---
// Ensure these are defined in config/header.php
// $auth_url   = "https://apiadda.in/"; 
// $api_key    = "...";
// $secret_key = "...";

// Fetch Fee (Defaulting to 30 as per your request)
 $price = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT * FROM pricing WHERE service_name='aadhar_npci_verify_fee' "));
 $fee = $price['price'] ?? 30; 

 $bankData = [];
 $showResult = false;
 $errorMsg = '';

if (isset($_POST['submit_verify'])) {
    // Map input variable to new API parameter name
    $aadhar_number = trim($_POST['aadhar_no']);
    $appliedby = $udata['phone'];
    $debit_fee = $udata['balance'] - $fee;

    if ($udata['balance'] >= $fee) {
        
        // --- NEW API URL CONSTRUCTION ---
        $url = "https://apiadda.in/RC_DATA/npci_status.php?api_key=$api_key&secret_key=$secret_key&aadhar_number=$aadhar_number";

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30, // Set timeout to 30s
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
        ));
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        
        $resdata = json_decode($response, true);

        // --- RESPONSE HANDLING ---
        
        // 1. Check for API Key/Secret Errors (Response returns 'msg')
        if (isset($resdata['msg'])) {
            $errorMsg = $resdata['msg'];

        // 2. Check for Success (Response returns status: true)
        } elseif (isset($resdata['status']) && $resdata['status'] === true) {
            
            // Deduct Balance Only on Success
            mysqli_query($ahk_conn, "UPDATE users SET balance = balance - $fee WHERE phone='$appliedby'");
            mysqli_query($ahk_conn, "INSERT INTO wallethistory(userid, amount, balance, purpose, status, type) VALUES ('$appliedby','$fee','$debit_fee','Aadhar NPCI Verify ($aadhar_number)','1','Debit')");
            
            // Map new API data structure to display variables
            $rawData = $resdata['data'];
            
            $bankData = [
                'bank_name'       => $rawData['bank_name'] ?? 'Unknown Bank',
                'account_status'  => $rawData['npci_status'] ?? 'N/A', // Mapping npci_status to account_status
                'last_updated'    => $rawData['last_updated_date'] ?? 'N/A',
                'uid'             => $rawData['aadhaar_number'] ?? $aadhar_number,
                'reference_id'    => 'N/A' // New API doesn't seem to return a ref ID in sample
            ];
            
            $showResult = true;

        // 3. Handle Failures (Low Balance, Server Down, etc.)
        } else {
            $errorMsg = $resdata['message'] ?? 'Bank Details Not Found or Server Error.';
        }

    } else {
        $errorMsg = "Wallet Balance is Low! Please Recharge ₹$fee.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aadhar NPCI Bank Verify</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        
        .modern-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            background: #fff;
            overflow: hidden;
            transition: transform 0.3s;
        }
        
        .card-header-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); /* Purple Theme */
            color: white;
            padding: 25px;
            text-align: center;
        }
        
        .card-header-custom h3 { margin: 0; font-weight: 700; letter-spacing: 0.5px; }
        .card-header-custom p { margin: 5px 0 0; opacity: 0.9; font-size: 0.9rem; }

        .form-floating-custom > .form-control {
            border-radius: 10px;
            border: 2px solid #eef2f7;
            height: 55px;
        }
        .form-floating-custom > .form-control:focus {
            border-color: #764ba2;
            box-shadow: 0 0 0 0.2rem rgba(118, 75, 162, 0.15);
        }

        .btn-search {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
            width: 100%;
            color: white;
            transition: transform 0.2s;
        }
        .btn-search:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(118, 75, 162, 0.3); color: white; }

        .fee-box {
            background-color: #eef2f7;
            border-radius: 10px;
            padding: 10px;
            text-align: center;
            margin-top: 20px;
            color: #555;
            font-weight: 600;
        }

        /* Result Styling */
        .result-section {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            border: none;
        }
        .result-header {
            background: linear-gradient(to right, #764ba2, #667eea);
            color: white;
            padding: 20px;
            border-radius: 20px 20px 0 0;
        }
        .bank-card {
            background: #fdfdfd;
            border: 2px dashed #764ba2;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            margin: 20px;
        }
        .bank-icon {
            font-size: 3rem;
            color: #764ba2;
            margin-bottom: 15px;
        }
        .bank-name {
            font-size: 1.5rem;
            font-weight: 800;
            color: #333;
            margin-bottom: 5px;
        }
        .status-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 600;
            background-color: #e0e0e0;
            color: #555;
            margin-top: 10px;
        }
        .status-active { background-color: #d1e7dd; color: #0f5132; }
        .status-na { background-color: #fff3cd; color: #856404; }

        .print-btn-custom {
            background: #333;
            color: white;
            border-radius: 50px;
            padding: 10px 25px;
            border: none;
            transition: 0.3s;
        }
        .print-btn-custom:hover { background: #000; }

        @media print {
            .no-print { display: none !important; }
            .page-wrapper { box-shadow: none; margin: 0; }
            body { background-color: white; }
            .result-section { box-shadow: none; border: 1px solid #ddd; }
            .result-header { background: #333 !important; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item"><a href="index"><i class="bx bx-home-alt"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Aadhar NPCI Verify</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group no-print">
                <a href="javascript:history.back()" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        <div class="container-fluid pt-4 px-4">
            <div class="row">
                
                <!-- LEFT COLUMN: SEARCH FORM -->
                <div class="col-lg-5 mb-4 no-print">
                    <div class="card modern-card">
                        <div class="card-header-custom">
                            <h3><i class='bx bx-credit-card'></i> Bank Verify</h3>
                            <p>Aadhar NPCI Link Check</p>
                        </div>
                        <div class="card-body p-4">
                            
                            <?php if(isset($errorMsg)): ?>
                                <div class="alert alert-danger bg-danger text-white border-0">
                                    <i class="bx bx-error-circle"></i> <?php echo $errorMsg; ?>
                                </div>
                            <?php endif; ?>

                            <form action="" method="POST">
                                <div class="form-floating form-floating-custom mb-3">
                                    <input type="text" class="form-control" id="aadhar_no" name="aadhar_no" placeholder="12 Digit Aadhar Number" required pattern="\d{12}" maxlength="12" title="Enter 12 digit Aadhar Number">
                                    <label for="aadhar_no"><i class='bx bxs-id-card'></i> Enter Aadhar No.</label>
                                </div>
                                
                                <div class="fee-box">
                                    Service Charge: ₹<?php echo htmlspecialchars($fee); ?>
                                </div>

                                <div class="mt-4">
                                    <button type="submit" name="submit_verify" class="btn btn-search">
                                        <i class="bx bx-search-alt-2"></i> Get Bank Details
                                    </button>
                                </div>
                            </form>

                            <div class="text-center mt-4">
                                <small class="text-muted">
                                    💻 Powered by <strong>PARI PRINT</strong><br>
                                    🚀 Developed by <strong>LALA WEB DEVELOPER</strong>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN: RESULTS -->
                <div class="col-lg-7 mb-4" id="printSection">
                    
                    <?php if (!$showResult): ?>
                        <!-- Empty State -->
                        <div class="card modern-card h-100 d-flex align-items-center justify-content-center" style="background-color: #f8f9fa; min-height: 400px;">
                            <div class="text-center">
                                <img src="https://cdn-icons-png.flaticon.com/512/2128/2128212.png" width="100" style="opacity: 0.5; margin-bottom: 20px;" alt="Bank">
                                <h4 class="text-muted">No Bank Details Found</h4>
                                <p class="text-muted small">Enter Aadhar number to check linked bank details.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        
                        <div class="result-section">
                            <div class="result-header d-flex justify-content-between align-items-center">
                                <div>
                                    <h4 class="mb-0 fw-bold">Bank Verification Result</h4>
                                    <small class="opacity-75">Verified Successfully</small>
                                </div>
                                <button class="btn btn-sm btn-light text-dark no-print" onclick="printVerification()">
                                    <i class="bx bx-printer"></i> Print
                                </button>
                            </div>

                            <div class="card-body">
                                <div class="bank-card">
                                    <i class='bx bxs-bank bank-icon'></i>
                                    <div class="bank-name"><?php echo htmlspecialchars($bankData['bank_name']); ?></div>
                                    <div class="uid-display text-muted">Aadhar: <?php echo htmlspecialchars($bankData['uid']); ?></div>
                                    
                                    <!-- Logic update: Check if status string contains 'Active' for color -->
                                    <div class="status-badge <?php echo (stripos($bankData['account_status'], 'Active') !== false) ? 'status-active' : 'status-na'; ?>">
                                        <?php echo htmlspecialchars($bankData['account_status']); ?>
                                    </div>
                                </div>

                                <table class="table table-borderless mt-4">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted">Last Updated</td>
                                            <td class="text-end fw-bold"><?php echo htmlspecialchars($bankData['last_updated']); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Reference ID</td>
                                            <td class="text-end fw-bold small"><?php echo htmlspecialchars($bankData['reference_id']); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function printVerification() {
        var content = document.getElementById("printSection").innerHTML;
        var printWindow = window.open('', '_blank', 'width=900,height=700');
        printWindow.document.write(`
            <html>
                <head>
                    <title>Bank Verification Report</title>
                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; background-color: white; text-align: center; }
                        .bank-card { border: 2px solid #764ba2; padding: 40px; border-radius: 15px; margin: 20px 0; }
                        .bank-name { font-size: 24px; font-weight: bold; color: #333; }
                        .status-badge { background: #e0e0e0; padding: 5px 15px; border-radius: 20px; font-weight: bold; }
                        h4 { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
                    </style>
                </head>
                <body>
                    <h4>Bank Verification Report</h4>
                    ${content}
                    <script>
                        window.onload = function() {
                            window.print();
                            window.close();
                        };
                    <\/script>
                </body>
            </html>
        `);
        printWindow.document.close();
    }
</script>

<?php include('footer.php'); ?>

<!-- Required JS -->
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>
</body>
</html>