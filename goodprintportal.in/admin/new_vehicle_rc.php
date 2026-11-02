<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

// --- API CONFIGURATION (Dynamic) ---
// Ensure these are defined in config/header.php
// $auth_url   = "https://apiadda.in/"; 
// $api_key    = "...";
// $secret_key = "...";

// Fetch Fee
 $price = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT * FROM pricing WHERE service_name='rc_info_fee' "));
 $fee = $price['price'] ?? 5; 

 $rcData = [];
 $showResult = false;
 $errorMsg = '';
 $rawJsonResponse = ''; // Variable to store raw JSON for copy feature

if (isset($_POST['submit_rc'])) {
    $rcNo = strtoupper(trim($_POST['rc_number']));
    $serverType = $_POST['server_type']; // '1' or '2'
    $appliedby = $udata['phone'];
    $debit_fee = $udata['balance'] - $fee;

    if ($udata['balance'] >= $fee) {
        
        // --- LOGIC BASED ON SERVER SELECTION ---
        if ($serverType == '2') {
            // API 2: new_vehicle_rc.php
            $apiUrl = $auth_url . "RC_DATA/new_vehicle_rc.php?" . http_build_query([
                'rc_number'  => $rcNo,
                'api_key'    => $api_key,
                'secret_key' => $secret_key
            ]);

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
            ]);
            $response = curl_exec($curl);
            curl_close($curl);
            
            $rawJsonResponse = $response; // Store raw response
            $resdata = json_decode($response, true);

            if (isset($resdata['success']) && $resdata['success'] === true && isset($resdata['data'])) {
                // Deduct Balance
                mysqli_query($ahk_conn, "UPDATE users SET balance = balance - $fee WHERE phone='$appliedby'");
                mysqli_query($ahk_conn, "INSERT INTO wallethistory(userid, amount, balance, purpose, status, type) VALUES ('$appliedby','$fee','$debit_fee','RC Info Server 2 ($rcNo)','1','Debit')");
                $rcData = $resdata['data'];
                $showResult = true;
            } else {
                $errorMsg = $resdata['message'] ?? 'RC Data Not Found on Server 2.';
            }

        } else {
            // API 1: Vehicle_data.php
            $apiUrl = $auth_url . "RC_DATA/Vehicle_data.php?" . http_build_query([
                'rc_number'  => $rcNo,
                'api_key'    => $api_key,
                'secret_key' => $secret_key
            ]);

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
            ]);
            $response = curl_exec($curl);
            curl_close($curl);

            $rawJsonResponse = $response; // Store raw response
            $resdata = json_decode($response, true);

            if (isset($resdata['success']) && $resdata['success'] === true && isset($resdata['data']['details'])) {
                // Deduct Balance
                mysqli_query($ahk_conn, "UPDATE users SET balance = balance - $fee WHERE phone='$appliedby'");
                mysqli_query($ahk_conn, "INSERT INTO wallethistory(userid, amount, balance, purpose, status, type) VALUES ('$appliedby','$fee','$debit_fee','RC Info Server 1 ($rcNo)','1','Debit')");

                // MAP FLAT API 1 DATA TO NESTED STRUCTURE
                $flat = $resdata['data']['details'];
                $rcData = [
                    'Ownership Details' => [
                        'Owner Name' => $flat['Owner Name'] ?? '',
                        "Father's Name" => $flat["Father's Name"] ?? '',
                        'Owner Serial No' => $flat['Owner Serial No'] ?? '',
                        'Registration Number' => $flat['rc'] ?? $rcNo,
                        'Registered RTO' => $flat['Registered RTO'] ?? ''
                    ],
                    'Vehicle Details' => [
                        'Model Name' => $flat['Model Name'] ?? '',
                        'Maker Model' => $flat['Maker Model'] ?? '',
                        'Vehicle Class' => $flat['Vehicle Class'] ?? '',
                        'Fuel Type' => $flat['Fuel Type'] ?? '',
                        'Fuel Norms' => $flat['Fuel Norms'] ?? ''
                    ],
                    'Insurance Information' => [
                        'Insurance Company' => $flat['Insurance Company'] ?? '',
                        'Insurance Expiry' => $flat['Insurance Expiry'] ?? '',
                        'Insurance No' => $flat['Insurance No'] ?? ''
                    ],
                    'Important Dates & Validity' => [
                        'Registration Date' => $flat['Registration Date'] ?? '',
                        'Fitness Upto' => $flat['Fitness Upto'] ?? '',
                        'Tax Upto' => $flat['Tax Upto'] ?? '',
                        'PUC Upto' => $flat['PUC Upto'] ?? ''
                    ]
                ];
                $showResult = true;
            } else {
                $errorMsg = $resdata['message'] ?? 'RC Data Not Found on Server 1.';
            }
        }

    } else {
        $errorMsg = "Wallet Balance is Low!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RC Information Check</title>
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
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
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
            border-color: #f5576c;
            box-shadow: 0 0 0 0.2rem rgba(245, 87, 108, 0.15);
        }

        /* Server Selection Styles */
        .server-select-group {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 12px;
        }
        
        .server-radio { display: none; }
        
        .server-label {
            flex: 1;
            text-align: center;
            padding: 12px;
            border-radius: 10px;
            cursor: pointer;
            border: 2px solid #e0e0e0;
            transition: all 0.3s;
            font-weight: 600;
            color: #666;
        }
        
        .server-radio:checked + .server-label {
            border-color: #f5576c;
            background-color: #fff0f3;
            color: #f5576c;
            box-shadow: 0 4px 10px rgba(245, 87, 108, 0.2);
        }

        .btn-search {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border: none;
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
            width: 100%;
            color: white;
            transition: transform 0.2s;
        }
        .btn-search:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(245, 87, 108, 0.3); color: white; }

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
            background: linear-gradient(to right, #f5576c, #f093fb);
            color: white;
            padding: 20px;
            border-radius: 20px 20px 0 0;
        }
        .result-group {
            padding: 20px;
            border-bottom: 1px dashed #eee;
        }
        .result-group:last-child { border-bottom: none; }
        .group-title {
            color: #f5576c;
            font-weight: 700;
            margin-bottom: 15px;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 1px;
            border-left: 4px solid #f5576c;
            padding-left: 10px;
        }
        .data-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f9f9f9;
        }
        .data-row:last-child { border-bottom: none; }
        .data-label { color: #888; font-size: 0.9rem; font-weight: 500; }
        .data-value { color: #333; font-weight: 600; font-size: 0.95rem; text-align: right; max-width: 60%; }

        .action-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.5);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: 0.3s;
        }
        .action-btn:hover { background: white; color: #f5576c; }

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
                        <li class="breadcrumb-item active" aria-current="page">RC Information</li>
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
                            <h3><i class='bx bxs-car'></i> RC Information</h3>
                            <p>Fast | Easy | Instant</p>
                        </div>
                        <div class="card-body p-4">
                            
                            <?php if(isset($errorMsg)): ?>
                                <div class="alert alert-danger bg-danger text-white border-0">
                                    <i class="bx bx-error-circle"></i> <?php echo $errorMsg; ?>
                                </div>
                            <?php endif; ?>

                            <form action="" method="POST">
                                <div class="form-floating form-floating-custom mb-3">
                                    <input type="text" class="form-control text-uppercase" id="rc_number" name="rc_number" placeholder="Vehicle Number" required>
                                    <label for="rc_number"><i class='bx bxs-car'></i> Enter Vehicle No</label>
                                </div>

                                <!-- Server Selection Buttons -->
                                <div class="server-select-group">
                                    <label>
                                        <input type="radio" name="server_type" value="2" class="server-radio" checked>
                                        <div class="server-label">
                                            API Server 2 <br><small>(Latest)</small>
                                        </div>
                                    </label>
                                    <label>
                                        <input type="radio" name="server_type" value="1" class="server-radio">
                                        <div class="server-label">
                                            API Server 1 <br><small>(Backup)</small>
                                        </div>
                                    </label>
                                </div>
                                
                                <div class="fee-box">
                                    Charges: ₹<?php echo htmlspecialchars($fee); ?>
                                </div>

                                <div class="mt-4">
                                    <button type="submit" name="submit_rc" class="btn btn-search">
                                        <i class="bx bxs-search-alt-2"></i> Get Details
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
                                <img src="https://cdn-icons-png.flaticon.com/512/1063/1063340.png" width="100" style="opacity: 0.5; margin-bottom: 20px;" alt="Search">
                                <h4 class="text-muted">No RC Details Found</h4>
                                <p class="text-muted small">Select a server and enter vehicle number to view details.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        
                        <div class="result-section">
                            <div class="result-header d-flex justify-content-between align-items-center">
                                <div>
                                    <h4 class="mb-0 fw-bold">RC Verification Details</h4>
                                    <small class="opacity-75">Verified Successfully</small>
                                </div>
                                <div class="d-flex gap-2">
                                    <!-- Print Button -->
                                    <button class="action-btn no-print" onclick="printVerification()">
                                        <i class="bx bx-printer"></i> Print
                                    </button>
                                    <!-- Copy JSON Button -->
                                    <button class="action-btn no-print" onclick="copyJson()">
                                        <i class="bx bx-copy"></i> Copy JSON
                                    </button>
                                </div>
                            </div>

                            <div class="card-body">
                                <?php
                                // Function to print sections dynamically
                                function printSection($title, $data) {
                                    if (empty($data)) return;
                                    echo "<div class='result-group'>";
                                    echo "<div class='group-title'>$title</div>";
                                    foreach ($data as $label => $value) {
                                        if (!empty($value)) {
                                            echo "<div class='data-row'>
                                                <span class='data-label'>" . htmlspecialchars($label) . "</span>
                                                <span class='data-value'>" . htmlspecialchars($value) . "</span>
                                            </div>";
                                        }
                                    }
                                    echo "</div>";
                                }

                                // Print all sections
                                printSection('Ownership Details', $rcData['Ownership Details'] ?? []);
                                printSection('Vehicle Details', $rcData['Vehicle Details'] ?? []);
                                printSection('Insurance Information', $rcData['Insurance Information'] ?? []);
                                printSection('Important Dates & Validity', $rcData['Important Dates & Validity'] ?? []);
                                printSection('Other Information', $rcData['Other Information'] ?? []);
                                ?>
                            </div>
                        </div>

                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Pass raw JSON from PHP to JS variable
    var rawApiResponse = <?php echo json_encode($rawJsonResponse); ?>;

    function printVerification() {
        var content = document.getElementById("printSection").innerHTML;
        var printWindow = window.open('', '_blank', 'width=900,height=700');
        printWindow.document.write(`
            <html>
                <head>
                    <title>RC Information Report</title>
                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; background-color: white; }
                        .result-group { border: 1px solid #eee; margin-bottom: 20px; border-radius: 8px; padding: 15px; }
                        .group-title { font-weight: bold; color: #f5576c; margin-bottom: 10px; border-bottom: 2px solid #f5576c; padding-bottom: 5px; }
                        .data-row { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid #f9f9f9; }
                        .data-row:last-child { border-bottom: none; }
                        h4 { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
                    </style>
                </head>
                <body>
                    <h4>RC Verification Report</h4>
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

    function copyJson() {
        if (!rawApiResponse || rawApiResponse === "") {
            Swal.fire('Error', 'No JSON data available to copy.', 'error');
            return;
        }
        
        // Parse and Stringify with 2 spaces indentation (Pretty Print)
        var formattedJson = JSON.stringify(JSON.parse(rawApiResponse), null, 2);
        
        navigator.clipboard.writeText(formattedJson).then(function() {
            Swal.fire({
                title: 'Copied!',
                text: 'JSON Response copied to clipboard.',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
        }, function(err) {
            console.error('Could not copy text: ', err);
            Swal.fire('Error', 'Failed to copy JSON.', 'error');
        });
    }
</script>

<?php include('footer.php'); ?>

<!-- Required JS -->
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>
</body>
</html>