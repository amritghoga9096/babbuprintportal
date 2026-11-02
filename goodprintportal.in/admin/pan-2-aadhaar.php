<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');
date_default_timezone_set('Asia/Kolkata');

$resdata = [];

if (isset($_POST['check']) && $_POST['check'] === "pan") {
    $panNumber = strtoupper(trim(mysqli_real_escape_string($ahk_conn, $_POST['panNumber'])));
    $application_no = "INSTANT" . rand(100000, 999999);
    $username = $udata['phone'];
    $appliedby = $udata['phone'];
    $mobile = $udata['phone'];
    $clint_id = $clint;

    // Get service price
    $price_query = mysqli_query($ahk_conn, "SELECT price FROM pricing WHERE service_name='Pan_To_Aadhaar_find'");
    $price_row = mysqli_fetch_assoc($price_query);
    $fee = floatval($price_row['price']);
    $wallet_amount = floatval($udata['balance']);
    $debit_fee = $wallet_amount - $fee;

    if ($wallet_amount >= $fee) {
        // API CALL
        $url = "https://" . htmlspecialchars($flexsecureapiurl) . "/api/v2/Pan2Uid/Apply.php?apiKey=" . urlencode($flexsecure_key) . "&panNumber=" . urlencode($panNumber);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "GET",
        ]);
        $response = curl_exec($curl);
        curl_close($curl);

        $resdata = json_decode($response, true);

        if (isset($resdata['status']) && $resdata['status'] == "100") {
            // Only values from API
            $statusMessage = mysqli_real_escape_string($ahk_conn, $resdata['statusMessage']);
            $message_code = $resdata['status'];
            $requestId = isset($resdata['requestId']) ? mysqli_real_escape_string($ahk_conn, $resdata['requestId']) : '';
            $apiPan = isset($resdata['panNumber']) ? mysqli_real_escape_string($ahk_conn, $resdata['panNumber']) : $panNumber;

            // Deduct Wallet Balance
            $debit = mysqli_query($ahk_conn, "UPDATE users SET balance = balance - $fee WHERE phone = '$appliedby'");

            // Add to Wallet History
            mysqli_query($ahk_conn, "INSERT INTO wallethistory (userid, amount, balance, purpose, status, type)
                VALUES ('$appliedby', '$fee', '$debit_fee', 'Aadhaar Request (PAN)', '1', 'Debit')");

            if ($debit) {
                // Final INSERT with nulls where needed
                $query = "INSERT INTO pan_to_uid_find (
                    application_no, panNumber, name, dob, gender, uid, mobile, username, clint_id,
                    status, status_code, fee, old_balance, new_balance, message
                ) VALUES (?, ?, NULL, NULL, NULL, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                $stmt = mysqli_prepare($ahk_conn, $query);

                if ($stmt) {
                    mysqli_stmt_bind_param(
                        $stmt,
                        "sssssssddds",
                        $application_no,
                        $apiPan,
                        $mobile,
                        $username,
                        $clint_id,
                        $status = 'Requested',
                        $message_code,
                        $fee,
                        $wallet_amount,
                        $debit_fee,
                        $statusMessage
                    );

                    $success = mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);

                   if ($success) {
    echo "<script>
        $(function() {
            Swal.fire('Success', 'Request Submitted for $apiPan', 'success').then(function() {
                window.location.href = 'pan-2-aadhaar-list';
            });
        });
    </script>";

                    } else {
                        $error = mysqli_error($ahk_conn);
                        echo "<script>$(function(){ Swal.fire('Insert Failed', 'DB Error: $error', 'error') });</script>";
                    }
                } else {
                    $error = mysqli_error($ahk_conn);
                    echo "<script>$(function(){ Swal.fire('Statement Error', '$error', 'error') });</script>";
                }
            } else {
                echo "<script>$(function(){ Swal.fire('Balance Error', 'Balance debited but insert failed.', 'warning') });</script>";
            }
        } else {
            $message = isset($resdata['error']) ? $resdata['error'] : "Unknown API Error";
            echo "<script>$(function(){ Swal.fire('API Error', '" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "', 'error') });</script>";
        }
    } else {
        echo "<script>$(function(){ Swal.fire('Insufficient Wallet Balance', 'Please Recharge', 'error') }); setTimeout(() => { window.location='wallet'; }, 1500);</script>";
    }
}
?>




<!-- HTML UI -->
<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb with Action Buttons -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">Pan To Aadhaar Find Service</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="pan-2-aadhaar-list" class="btn btn-success">
                    <i class="bi bi-list-ul me-1"></i> Pan To Aadhaar List
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
        <div class="container-fluid pt-4 px-4">
            <div class="row">
                <div class="col-lg-5 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <h3 class="card-title text-center text-dark"><strong>PAN No To Get Aadhaar No Instant</strong></h3>
                            <form method="POST">
                                <div class="form-group mb-3">
                                    <label for="panNumber">PAN Number <span class="text-danger">*</span></label>
                                    <input name="panNumber" type="text" id="panNumber" placeholder="Enter PAN Number" class="form-control" required>
                                    <input type="hidden" name="check" value="pan">
                                </div>
                                <div class="text-center">
                                    <button type="submit" class="btn btn-primary px-5">Verify Now</button>
                                </div>
                            </form>
                            <div class="text-center mt-3">
                                <div class="fee-container">
                                    <h5 class="text-warning">Application Fee:
                                        <?php  
                                        $price = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT price FROM pricing WHERE service_name='Pan_To_Aadhaar_find'")); 
                                        echo "₹" . $price['price'];
                                        ?>
                                    </h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>




		
		<!--end page wrapper -->
		<?php 
		include('footer.php');
		?>
	<!-- Bootstrap JS -->
	

	<script src="../template/ahkweb/assets/js/bootstrap.bundle.min.js"></script>
	<!--plugins-->
	<!-- <script src="../template/ahkweb/assets/js/jquery.min.js"></script> -->
	<script src="../template/ahkweb/assets/plugins/simplebar/js/simplebar.min.js"></script>
	<script src="../template/ahkweb/assets/plugins/metismenu/js/metisMenu.min.js"></script>
	<script src="../template/ahkweb/assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
	<!--app JS-->
	<script src="../template/ahkweb/assets/js/app.js"></script>
</body>

<script>
	$(document).ready(function() {
	
	$('#eid').inputmask();
	$('#date').inputmask();
	$('#timea').inputmask("hh:mm:ss", {
        placeholder: "00:00:00", 
        insertMode: false, 
        showMaskOnHover: false,
        hourFormat: 12
      });
	});
</script>
</html>