<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

$success = $error = '';
$mobileNo = '';
$otpSent = isset($_SESSION['aadhaar_otp_sent']) && $_SESSION['aadhaar_otp_sent'] === true;
$api_key = urlencode($flexsecure_key);

// === RESET SESSION ===
if (isset($_POST['reset_session'])) {
    unset($_SESSION['aadhaar_otp_sent']);
    unset($_SESSION['aadhaar_mobile']);
    unset($_SESSION['authToken']);
    unset($_SESSION['enMobileNo']);
    $success = "Session reset successfully. You can start again.";
    $otpSent = false;
}

// === SEND OTP ===
if (isset($_POST['send_otp'])) {
    $mobileNo = trim($_POST['mobileNo']);

    if (!preg_match('/^[6-9][0-9]{9}$/', $mobileNo)) {
        $error = "Please enter a valid 10-digit mobile number.";
    } else {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://{$flexsecureapiurl}/serviceApi/FlexSecureV1/Aadhaar/ekyc_Otp/send_otp",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'mobileNo' => $mobileNo,
                'apiKey' => $api_key
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
        ]);

        $response = curl_exec($curl);
        curl_close($curl);
        $res = json_decode($response, true);

        if (isset($res['status']) && $res['status'] == '100') {
            $_SESSION['aadhaar_mobile'] = $mobileNo;
            $_SESSION['aadhaar_otp_sent'] = true;
            $success = $res['message'] ?? "OTP sent successfully!";
            $otpSent = true; // show OTP form immediately
        } else {
            $error = $res['error'] ?? "Failed to send OTP. Please try again.";
        }
    }
}

// === VERIFY OTP ===
if (isset($_POST['verify_otp'])) {
    $mobileNo = $_SESSION['aadhaar_mobile'] ?? '';
    $otp = trim($_POST['otp']);

    if (empty($mobileNo)) {
        $error = "Session expired. Please resend OTP.";
        $otpSent = false;
    } elseif (!preg_match('/^[0-9]{8}$/', $otp)) {
        $error = "Please enter a valid 8-digit OTP.";
        $otpSent = true;
    } else {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://{$flexsecureapiurl}/serviceApi/FlexSecureV1/Aadhaar/ekyc_Otp/verify_otp",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'mobileNo' => $mobileNo,
                'otp' => $otp,
                'apiKey' => $api_key
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
        ]);

        $response = curl_exec($curl);
        curl_close($curl);
        $res = json_decode($response, true);
    if ($res['status'] == true) {
        $success = $res['message'];
        $loggedIn = true;
        $jwtToken = $res['token'];
        $_SESSION['es_jwtToken'] = $jwtToken;
        $_SESSION['es_saltValue'] = $otp;
        $_SESSION['es_mobileNo'] = $mobileNo;
        echo '<script>alert("'.$res['message'].'");window.location.replace("aadhar_flx_take.php");</script>';
            exit;
        } else {
            $error = $res['error'] ?? "Invalid OTP. Please try again.";
            $otpSent = true;
        }
    }
}
?>

<!-- ✅ UI STYLE -->
<style>
body {
  background: #f3f6fc;
  font-family: 'Poppins', sans-serif;
}
/*.page-wrapper { padding: 50px 0; }*/
.verification-card {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 8px 25px rgba(0,0,0,0.08);
  display: flex;
  flex-wrap: wrap;
  overflow: hidden;
}
.left-pane, .right-pane {
  flex: 1;
  min-width: 320px;
  padding: 40px;
}
.right-pane {
  background: linear-gradient(135deg, #72c98b, #81b2e5);
  color: white;
  text-align: center;
  display: flex;
  flex-direction: column;
  justify-content: center;
}
.right-pane img {
  width: 120px;
  margin: 0 auto 20px;
}
.right-pane h2 { font-size: 24px; font-weight: 700; }
.right-pane p { font-size: 15px; opacity: .95; margin-top: 10px; }

h4.section-title {
  text-align: center;
  color: #004aad;
  font-weight: 700;
  margin-bottom: 25px;
}
label { font-weight: 600; color: #333; margin-bottom: 5px; }
input.form-control {
  border-radius: 8px;
  border: 1px solid #ccc;
  height: 45px;
  padding: 10px;
  font-size: 15px;
}
.btn-submit {
  background: #004aad;
  color: white;
  border: none;
  border-radius: 8px;
  padding: 12px 18px;
  font-weight: 600;
  width: 100%;
  transition: 0.3s;
}
.btn-submit:hover { background: #0060df; }
.alert {
  padding: 10px 15px;
  border-radius: 6px;
  margin-bottom: 15px;
  font-weight: 500;
}
.alert-success { background: #d1e7dd; color: #0f5132; }
.alert-danger { background: #f8d7da; color: #842029; }
.footer-note {
  text-align: center;
  font-size: 13px;
  color: #777;
  margin-top: 20px;
}
.reset-btn {
  margin-top: 10px;
  background: #dc3545;
  color: white;
  border: none;
  border-radius: 8px;
  padding: 10px 18px;
  font-weight: 600;
  width: 100%;
}
.reset-btn:hover { background: #c82333; }
@media(max-width:991px){
  .verification-card { flex-direction: column; }
  .right-pane { padding: 30px; }
}
</style>

<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb with Action Buttons -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">Aadhaar Ekyc S3</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="aadhar_advance_list" class="btn btn-success">
                    <i class="bi bi-list-ul me-1"></i> All List
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

    <div class="container">
      <div class="verification-card">

        <!-- LEFT FORM -->
        <div class="left-pane">
          <h4 class="section-title">Mobile OTP Verification</h4>

          <?php if($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
          <?php elseif($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>

          <?php if(!$otpSent): ?>
          <!-- Step 1: Send OTP -->
          <form method="POST" action="">
            <div class="mb-3">
              <label for="mobileNo">Registered Mobile Number</label>
              <input name="mobileNo" type="text" maxlength="10" class="form-control" required placeholder="Enter 10-digit Mobile Number" pattern="[6-9][0-9]{9}" />
            </div>
            <button type="submit" name="send_otp" class="btn-submit">Send OTP</button>
          </form>
          <?php else: ?>
          <!-- Step 2: Verify OTP -->
          <form method="POST" action="">
            <div class="mb-3">
              <label for="otp">Enter 8-digit OTP</label>
              <input name="otp" type="text" maxlength="8" class="form-control" required placeholder="Enter 8-digit OTP" pattern="[0-9]{8}" />
            </div>
            <button type="submit" name="verify_otp" class="btn-submit">Verify OTP</button>
          </form>
          <form method="POST" action="">
            <button type="submit" name="reset_session" class="reset-btn">Reset Session</button>
          </form>
          <?php endif; ?>

          <div class="footer-note">
            <small>All verifications are encrypted and secured with <?php echo ahkweb('webname');  ?> API.</small>
          </div>
        </div>

        <!-- RIGHT INFORMATION PANEL -->
        <div class="right-pane">
          <img src="../admin/uploads/Services/aadhar.png" alt="Aadhaar Logo">
          <h2>Verify Your Aadhaar</h2>
          <p>
            Only mobile number verification — simple, secure, and lightning fast.  
  Instantly verify your number using OTP powered by <?php echo ahkweb('webname');  ?> API.
          </p>
          <ul style="text-align:left;margin-top:20px;line-height:1.6;font-size:14px;">
            <li>✔ Instant OTP Delivery</li>
            <li>✔ Secure & Encrypted Validation</li>
            <li>✔ Government UIDAI API Integrated</li>
            <li>✔ Smooth User Experience</li>
          </ul>
        </div>

      </div>
    </div>
  </div>
</div>

<?php include('footer.php'); ?>

                </div>
</div>

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
		  } );
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