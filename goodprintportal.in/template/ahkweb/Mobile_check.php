<?php include('header.php'); ?>

<?php
// fetch fee from database
 $price = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT * FROM pricing WHERE service_name='Mobile_Operator_check' "));
 $fee = $price['price'];

 $resdata = [];
 $showResult = false;
 $resultsList = [];

if (isset($_POST['submit'])) {
    $Mobileno = trim($_POST['Mobileno']);
    $appliedby = $udata['phone'];
    $debit_fee = $udata['balance'] - $fee;

    if ($udata['balance'] >= $fee) {
        
        // --- API CONFIGURATION (Dynamic) ---
        // Ensure these are defined in config/header.php
        // $auth_url   = "https://apiadda.in/"; 
        // $api_key    = "...";
        // $secret_key = "...";

        $url = $auth_url . "api_service_v5/mob_info.php?" . http_build_query([
            'api_key'    => $api_key,
            'secret_key' => $secret_key,
            'mobile'     => $Mobileno
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
        $curlError = curl_error($curl);
        curl_close($curl);
        
        $resdata = json_decode($response, true) ?? [];

        // Error Handling
        if (isset($resdata['status']) && $resdata['status'] == 5000) {
            $error_msg = "Low Balance at API Provider end.";
        } elseif (isset($resdata['msg'])) {
            $error_msg = $resdata['msg'];
        } elseif ($curlError) {
            $error_msg = "Connection Error: " . $curlError;
        } elseif (isset($resdata['total_found']) && $resdata['total_found'] > 0) {
            // SUCCESS
            mysqli_query($ahk_conn, "UPDATE users SET balance=balance-$fee WHERE phone='$appliedby'");
            mysqli_query($ahk_conn, "INSERT INTO `wallethistory`(`userid`, `amount`, `balance`, `purpose`, `status`, `type`) 
                VALUES ('$appliedby','$fee','$debit_fee','Mobile Info Verify','1','Debit')");
            
            $resultsList = $resdata['data'];
            $showResult = true;
        } else {
            // No Data Found
            $error_msg = $resdata['message'] ?? 'No data found for this mobile number.';
        }
    } else {
        $error_msg = "Wallet Balance is Low!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mobile Info Verify</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

        .result-item {
            background: #fff;
            border: 1px solid #eee;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            position: relative;
        }
        
        .result-item::before {
            content: '';
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 5px;
            background: #667eea;
            border-top-left-radius: 15px;
            border-bottom-left-radius: 15px;
        }

        .result-label { color: #888; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; margin-bottom: 2px; }
        .result-value { color: #333; font-weight: 500; font-size: 1rem; margin-bottom: 12px; word-break: break-word;}
        
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
            .modern-card { border: 1px solid #ddd; box-shadow: none; }
            body { background-color: white; }
            .result-item { border: 1px solid #ccc; break-inside: avoid; }
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
                        <li class="breadcrumb-item active" aria-current="page">Mobile Info Verify</li>
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
                            <h3><i class='bx bxs-mobile-alt'></i> Mobile Info Check</h3>
                            <p>Get Name, Address & Circle Details</p>
                        </div>
                        <div class="card-body p-4">
                            
                            <?php if(isset($error_msg)): ?>
                                <div class="alert alert-danger bg-danger text-white border-0">
                                    <i class="bx bx-error-circle"></i> <?php echo $error_msg; ?>
                                </div>
                            <?php endif; ?>

                            <form action="" method="POST">
                                <div class="form-floating form-floating-custom mb-3">
                                    <input type="text" class="form-control" id="Mobileno" name="Mobileno" placeholder="Mobile Number" required pattern="\d{10}" maxlength="10" minlength="10">
                                    <label for="Mobileno"><i class='bx bx-phone'></i> Enter 10 Digit Mobile</label>
                                </div>
                                
                                <div class="fee-box">
                                    Charges: ₹<?php echo htmlspecialchars($fee); ?>
                                </div>

                                <div class="mt-4">
                                    <button type="submit" name="submit" class="btn btn-search">
                                        <i class="bx bxs-search-alt-2"></i> Verify Number
                                    </button>
                                </div>
                            </form>

                            <div class="text-center mt-4">
                                <small class="text-muted">
                                    💻 Powered by <strong>goodprintportal.in</strong><br>
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
                                <img src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" width="100" style="opacity: 0.5; margin-bottom: 20px;" alt="Search">
                                <h4 class="text-muted">No Details Found Yet</h4>
                                <p class="text-muted small">Enter a mobile number on the left to view verification details here.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        
                        <div class="modern-card">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                                    <div>
                                        <h4 class="mb-0 fw-bold text-dark">Verification Results</h4>
                                        <small class="text-success"><i class='bx bx-check-circle'></i> Successfully Found Data</small>
                                    </div>
                                    <button class="btn btn-sm print-btn-custom no-print" onclick="printVerification()">
                                        <i class="bx bx-printer"></i> Print
                                    </button>
                                </div>

                                <div class="alert alert-info py-2 text-center">
                                    Total Records Found: <strong><?php echo count($resultsList); ?></strong>
                                </div>

                                <?php foreach ($resultsList as $index => $data): ?>
                                    <div class="result-item">
                                        <?php if(count($resultsList) > 1): ?>
                                            <h6 class="text-primary mb-3 fw-bold">Record #<?php echo $index + 1; ?></h6>
                                        <?php endif; ?>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="result-label">Mobile Number</div>
                                                <div class="result-value"><?php echo htmlspecialchars($data['mobile'] ?? ''); ?></div>
                                                
                                                <div class="result-label">Name</div>
                                                <div class="result-value fw-bold"><?php echo htmlspecialchars($data['name'] ?? ''); ?></div>
                                                
                                                <div class="result-label">Father's Name</div>
                                                <div class="result-value"><?php echo htmlspecialchars($data['father_name'] ?? ''); ?></div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="result-label">Address</div>
                                                <div class="result-value"><?php echo htmlspecialchars($data['address'] ?? ''); ?></div>
                                                
                                                <div class="result-label">Circle / Operator</div>
                                                <div class="result-value"><?php echo htmlspecialchars($data['circle'] ?? ''); ?></div>
                                                
                                                <div class="result-label">Reference ID</div>
                                                <div class="result-value small text-muted"><?php echo htmlspecialchars($data['id'] ?? ''); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
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
                    <title>Mobile Verification Report</title>
                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; background-color: white; }
                        .result-item { border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; border-radius: 8px; }
                        .result-label { font-weight: bold; color: #555; font-size: 12px; }
                        .result-value { margin-bottom: 8px; }
                        h4 { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
                    </style>
                </head>
                <body>
                    <h4>Mobile Information Verification</h4>
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