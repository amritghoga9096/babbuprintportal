<?php
include('../template/ahkweb/header.php');

// Fetch fee from database
$priceQuery = mysqli_query($ahk_conn, "SELECT price FROM pricing WHERE service_name='Driving_Licence_Find' LIMIT 1");
$price = mysqli_fetch_assoc($priceQuery);
$fee = isset($price['price']) ? (float)$price['price'] : 0.00;

$resdata = [];
$showResult = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['name']) && !empty($_POST['dob'])) {
    $name = mysqli_real_escape_string($ahk_conn, trim($_POST['name']));
    $dob_raw = mysqli_real_escape_string($ahk_conn, trim($_POST['dob']));

    // Validate and keep date in DD-MM-YYYY format
    $dob_parts = preg_split('/[\s\/\-\.]+/', $dob_raw);
    if (count($dob_parts) === 3) {
        $dd = $dob_parts[0];
        $mm = $dob_parts[1];
        $yyyy = $dob_parts[2];

     if (checkdate((int)$mm, (int)$dd, (int)$yyyy)) {
    // Valid date - format the DOB and continue
    $dob = sprintf("%02d-%02d-%04d", $dd, $mm, $yyyy); // DD-MM-YYYY
    // continue processing normally
} else {
    // Invalid date - show error alert with timer and auto-redirect
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Invalid Date',
            text: 'Please enter a valid date in DD-MM-YYYY format.',
            timer: 2000,
            timerProgressBar: true,
            didClose: () => {
                window.location.href = 'DL_Find';
            }
        }).then((result) => {
            if (result.dismiss === Swal.DismissReason.timer) {
                // Redirect handled in didClose
            } else {
                // User clicked OK, redirect now
                window.location.href = 'DL_Find';
            }
        });
    </script>";
    exit;
}


    } else {
        echo "<script>Swal.fire('Invalid Format', 'Date format should be DD-MM-YYYY.', 'error');</script>";
        exit;
    }

    $appliedby = $udata['phone'] ?? null;
    $current_balance = (float)($udata['balance'] ?? 0);
    $debit_fee = $current_balance - $fee;

    if ($appliedby && $current_balance >= $fee) {
        $api_key = urlencode($flexsecure_key ?? '');
        $url = "https://{$flexsecureapiurl}/api/v1/dlFind.php?apiKey={$api_key}&name=" . urlencode($name) . "&dob=" . urlencode($dob);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($curl);
        $curl_error = curl_error($curl);
        curl_close($curl);

        if ($curl_error) {
            echo "<script>Swal.fire('Error', 'API request failed: " . addslashes($curl_error) . "', 'error');</script>";
            exit;
        }

        $resdata = json_decode($response, true);

        if (isset($resdata['status']) && (int)$resdata['status'] === 100 && isset($resdata['data']['data'])) {
            mysqli_query($ahk_conn, "UPDATE users SET balance = balance - $fee WHERE phone = '$appliedby'");
            mysqli_query($ahk_conn, "INSERT INTO `wallethistory`(`userid`, `amount`, `balance`, `purpose`, `status`, `type`) 
                VALUES ('$appliedby','$fee','$debit_fee','DL Find Verification','1','Debit')");
            $showResult = true;
        } else {
            $error_message = $resdata['error'] ?? 'Something went wrong.';
            echo "<script>Swal.fire('Error', '" . addslashes($error_message) . "', 'error');</script>";
        }
    } else {
        echo "<script>Swal.fire('Wallet Balance is Low!', 'Please Recharge Now!', 'error'); setTimeout(() => { window.location='wallet.php'; }, 1200);</script>";
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
                        <li class="breadcrumb-item active text-muted" aria-current="page">Driving Licence Find</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
               
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        <!-- Scripts -->
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.inputmask/5.0.8/jquery.inputmask.min.js"></script>

        <style>.hidden { display: none; }</style>

        <div class="container-fluid pt-4 px-4">
            <div class="row">

                <!-- Form Section -->
                <div class="col-lg-5 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <h3 class="card-title text-dark text-center"><strong>DL Find Verification</strong></h3>
                            <form action="" method="POST" class="row g-3">
                                <div class="col-md-12">
                                    <label for="name">Enter Full Name</label>
                                    <input name="name" type="text" id="name" placeholder="Enter Name" class="form-control" required>

                                    <label for="dob" class="mt-2">Enter Date of Birth (DD-MM-YYYY)</label>
                                    <input name="dob" type="text" id="dob" placeholder="DD-MM-YYYY" class="form-control" required>

                                    <hr>
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <input class="form-control" value="Fee ₹ <?php echo htmlspecialchars($fee); ?>" readonly>
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

                <!-- Result Section -->
                <?php if ($showResult): ?>
                    <div class="col-lg-7 col-md-6 col-sm-6">
                        <div class="card" style="background-color: #E8F8F5;" id="printSection">
                            <div class="card-body">
                                <h5 class="mb-3">DL Find Verification Result</h5>
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>DL Number</th>
                                            <th>Name</th>
                                            <th>Father's Name</th>
                                            <th>DOB</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($resdata['data']['data'] as $item): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($item['dlno']) ?></td>
                                                <td><?= htmlspecialchars($item['name']) ?></td>
                                                <td><?= htmlspecialchars($item['fathername']) ?></td>
                                                <td><?= htmlspecialchars($item['dob']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <div class="text-end mt-3">
                                    <button class="btn btn-primary" onclick="printVerification()">🖨️ Print</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>

        <script>
            $(document).ready(function () {
                $('#dob').inputmask('99-99-9999'); // DD-MM-YYYY
            });

            function printVerification() {
                var content = document.getElementById("printSection").innerHTML;
                var printWindow = window.open('', '_blank', 'width=800,height=600');
                printWindow.document.write(`
                    <html>
                        <head>
                            <title>DL Find Verification Result</title>
                            <style>
                                body { font-family: Arial, sans-serif; padding: 20px; }
                                table { width: 100%; border-collapse: collapse; }
                                th, td { border: 1px solid #000; padding: 8px; text-align: left; }
                                th { background-color: #f2f2f2; }
                                h5 { margin-bottom: 20px; }
                            </style>
                        </head>
                        <body>
                            ${content}
                            <script>
                                window.onload = function() { window.print(); };
                            <\/script>
                        </body>
                    </html>
                `);
                printWindow.document.close();
            }
        </script>
    </div>
</div>




<?php include('footer.php'); ?>

<!-- Required JS -->
<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="../template/ahkweb/assets/js/app.js"></script>
