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
 $price = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT * FROM pricing WHERE service_name='ration_aadhar_find_fee' "));
 $fee = $price['price'] ?? 30; // Default 20 based on API sample

 $membersList = [];
 $showResult = false;
 $errorMsg = '';

if (isset($_POST['submit_search'])) {
    $rationNo = trim($_POST['ration_no']);
    $appliedby = $udata['phone'];
    $debit_fee = $udata['balance'] - $fee;

    if ($udata['balance'] >= $fee) {
        
        // Construct URL
        $url = $auth_url . "RC_DATA/ration_to_uidno_up.php?" . http_build_query([
            'rationNumber' => $rationNo,
            'api_key'      => $api_key,
            'secret_key'   => $secret_key
        ]);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
        ]);
        $response = curl_exec($curl);
        curl_close($curl);
        
        $resdata = json_decode($response, true);

        if (isset($resdata['status']) && $resdata['status'] === "success" && isset($resdata['members'])) {
            
            // Deduct Balance
            mysqli_query($ahk_conn, "UPDATE users SET balance = balance - $fee WHERE phone='$appliedby'");
            mysqli_query($ahk_conn, "INSERT INTO wallethistory(userid, amount, balance, purpose, status, type) VALUES ('$appliedby','$fee','$debit_fee','Ration Aadhar Find ($rationNo)','1','Debit')");
            
            $membersList = $resdata['members'];
            $showResult = true;

        } else {
            $errorMsg = $resdata['message'] ?? 'Ration Data Not Found or Invalid Ration Number.';
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
    <title>UP Ration to Aadhar Search</title>
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
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
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
            border-color: #38ef7d;
            box-shadow: 0 0 0 0.2rem rgba(56, 239, 125, 0.15);
        }

        .btn-search {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            border: none;
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
            width: 100%;
            color: white;
            transition: transform 0.2s;
        }
        .btn-search:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(56, 239, 125, 0.3); color: white; }

        .fee-box {
            background-color: #eef2f7;
            border-radius: 10px;
            padding: 10px;
            text-align: center;
            margin-top: 20px;
            color: #555;
            font-weight: 600;
        }

        /* Result Table Styling */
        .result-section {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            border: none;
            overflow: hidden;
        }
        .result-header {
            background: linear-gradient(to right, #11998e, #38ef7d);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .table-custom thead {
            background-color: #e8f8f5;
            color: #11998e;
        }
        .table-custom th {
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.85rem;
            border-bottom: 2px solid #38ef7d;
        }
        .table-custom td {
            vertical-align: middle;
            color: #444;
            font-size: 0.95rem;
        }
        .uid-badge {
            background-color: #e0f2fe;
            color: #0284c7;
            padding: 5px 10px;
            border-radius: 6px;
            font-family: monospace;
            font-weight: 600;
            font-size: 0.9rem;
            border: 1px solid #bae6fd;
        }

        .print-btn-custom {
            background: white;
            color: #11998e;
            border: 1px solid rgba(255,255,255,0.5);
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 600;
            transition: 0.3s;
        }
        .print-btn-custom:hover { background: white; color: #0d9488; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }

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
                        <li class="breadcrumb-item active" aria-current="page">Ration to Aadhar Search</li>
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
                            <h3><i class='bx bxs-id-card'></i> Ration Search</h3>
                            <p>UP Ration to Aadhar Instant</p>
                        </div>
                        <div class="card-body p-4">
                            
                            <?php if(isset($errorMsg)): ?>
                                <div class="alert alert-danger bg-danger text-white border-0">
                                    <i class="bx bx-error-circle"></i> <?php echo $errorMsg; ?>
                                </div>
                            <?php endif; ?>

                            <form action="" method="POST">
                                <div class="form-floating form-floating-custom mb-3">
                                    <input type="text" class="form-control text-uppercase" id="ration_no" name="ration_no" placeholder="Enter Ration Number" required>
                                    <label for="ration_no"><i class='bx bxs-basket'></i> Ration Card No.</label>
                                </div>
                                
                                <div class="fee-box">
                                    Service Charge: ₹<?php echo htmlspecialchars($fee); ?>
                                </div>

                                <div class="mt-4">
                                    <button type="submit" name="submit_search" class="btn btn-search">
                                        <i class="bx bxs-search-alt-2"></i> Search Members
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
                                <img src="https://cdn-icons-png.flaticon.com/512/3163/3163286.png" width="100" style="opacity: 0.5; margin-bottom: 20px;" alt="Search">
                                <h4 class="text-muted">No Member Details Found</h4>
                                <p class="text-muted small">Enter Ration Number to find linked Aadhar details.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        
                        <div class="result-section">
                            <div class="result-header">
                                <div>
                                    <h4 class="mb-0 fw-bold">Family Members Details</h4>
                                    <small class="opacity-75">Total Members: <strong><?php echo count($membersList); ?></strong></small>
                                </div>
                                <button class="btn print-btn-custom no-print" onclick="printVerification()">
                                    <i class="bx bx-printer"></i> Print List
                                </button>
                            </div>

                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-custom table-striped table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th width="5%">Sr.</th>
                                                <th width="25%">Name</th>
                                                <th width="15%">Relation</th>
                                                <th width="10%">Gender</th>
                                                <th width="15%">DOB</th>
                                                <th width="30%">Aadhar Number</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($membersList as $member): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($member['srno'] ?? ''); ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($member['Nameof_Family_Member_EN'] ?? ''); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($member['Father_Name_EN'] ?? ''); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($member['RELATION'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($member['Gender'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($member['DOB'] ?? ''); ?></td>
                                                <td>
                                                    <span class="uid-badge"><?php echo htmlspecialchars($member['UIDNo'] ?? ''); ?></span>
                                                <?php if(!empty($member['UIDNo'])): ?>
                                                <a href="data:text/plain;charset=utf-8,<?php echo $member['UIDNo']; ?>" class="btn btn-sm btn-outline-secondary ms-2" title="Copy Aadhar">
                                                    <i class="bx bx-copy"></i>
                                                </a>
                                                <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="p-3 text-center text-muted small border-top">
                                    Ration Number: <?php echo htmlspecialchars($resdata['rationNumber'] ?? ''); ?>
                                </div>
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
                    <title>Ration Member Details</title>
                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; background-color: white; }
                        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        th, td { border: 1px solid #000; padding: 10px; text-align: left; }
                        th { background-color: #11998e; color: white; }
                        h4 { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
                        .uid-badge { background-color: #eee; padding: 2px 5px; border-radius: 3px; font-family: monospace; font-weight: bold; }
                    </style>
                </head>
                <body>
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