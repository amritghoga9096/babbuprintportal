<!DOCTYPE html>
<?php
include('header.php');

// PARI PRINT KE APNE KEYS
 $apiadda_key = 'e5742e-4c57fc-d1c4ac-0390b5-8cbd0e';
 $apiadda_secret = 'e06450a0867d62c';

// ============================
// AJAX: LOAD CAPTCHA (API 1)
// ============================
if (isset($_POST['action']) && $_POST['action'] === 'load_captcha') {
    header('Content-Type: application/json');
    $ch = curl_init("https://apiadda.in/RC_DATA/RCPDF/captcha_api.php");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_TIMEOUT => 15]);
    $res = curl_exec($ch);
    curl_close($ch);
    echo $res ?: json_encode(["status"=>"error","message"=>"Captcha failed"]);
    exit;
}

// ============================
// AJAX: SUBMIT RC & GET PDF (API 2)
// ============================
if (isset($_POST['action']) && $_POST['action'] === 'submit_rc') {
    header('Content-Type: application/json');
    
    $rc_no = strtoupper(trim($_POST['rc_dl']));
    $captcha = trim($_POST['captcha']);
    $session_id = trim($_POST['session_id']);
    $user_sid = trim($_POST['user_sid']);
    $card_type = trim($_POST['card_type']) === '1' ? 'chip' : 'non chip';

    if (empty($rc_no) || empty($captcha) || empty($session_id) || empty($user_sid)) {
        echo json_encode(["status"=>"error","message"=>"Missing RC, Captcha or Session"]); exit;
    }

    // Balance Check
    $price = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT price FROM pricing WHERE service_name='rc_vehical_fee'"));
    $fee = $price['price'];
    $username = $udata['phone'];
    $wallet_amount = $udata['balance'];

    // MARK - Duplicate RC check (AJAX Server two): same user + same RC already paid & saved
    $dupCheck = mysqli_fetch_assoc(mysqli_query($ahk_conn,
        "SELECT id FROM rc_vehical WHERE username = '$username' AND rc_vehical_no = '$rc_no' AND status = 'Success' LIMIT 1"
    ));
    if ($dupCheck) {
        echo json_encode(["status"=>"duplicate","message"=>"Ye RC already generate ho chuka hai. History se download karein.","redirect"=>"rc_get_list?rc=".rawurlencode($rc_no)."&dup=1"]);
        exit;
    }

    if ($wallet_amount < $fee) {
        echo json_encode(["status"=>"error","message"=>"Wallet Balance Insufficient! Please Recharge"]); exit;
    }

    // API 2 CALL
    $apiUrl = "https://apiadda.in/RC_DATA/RCPDF/rc_pdf_api.php?" . http_build_query([
        'rc_no' => $rc_no,
        'captcha' => $captcha,
        'session_id' => $session_id,
        'user_sid' => $user_sid,
        'card_type' => $card_type,
        'api_key' => $apiadda_key,
        'secret_key' => $apiadda_secret
    ]);

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_FOLLOWLOCATION => true
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        echo json_encode(["status"=>"error","message"=>"API Connection Failed"]); exit;
    }

    $result = json_decode($res, true);

    if (isset($result['status']) && $result['status'] === 'success' && !empty($result['pdf_base64'])) {
        
        $rawPdf = $result['pdf_base64'];
        $cleanBase64 = preg_replace('#^data:application/pdf;base64,#i', '', $rawPdf);
        $fileForDb = "data:application/pdf;base64," . $cleanBase64;

        // LOCAL BALANCE CUT (Agar API me already cut ho raha hai toh ye 3 lines hata dena)
        $debit_fee = $wallet_amount - $fee;
        mysqli_query($ahk_conn, "UPDATE users SET balance = balance - $fee WHERE phone = '$username'");
        
        date_default_timezone_set("Asia/Kolkata");
        $time_hkb = date('d/m/Y g:i:s');

        mysqli_query($ahk_conn, "INSERT INTO rc_vehical (username, rc_vehical_no, status, pdf, date) VALUES ('$username','$rc_no','Success','$fileForDb','$time_hkb')");
        mysqli_query($ahk_conn, "INSERT INTO wallethistory (userid, amount, balance, purpose, status, type) VALUES ('$username','$fee','$debit_fee','RC Vehical PDF - $rc_no','1','Debit')");

        echo json_encode(["status"=>"success", "message"=>"PDF Generated", "pdf" => $fileForDb, "rc" => $rc_no]);
    } else {
        $errMsg = $result['message'] ?? 'RC Not Found or Invalid Captcha';
        echo json_encode(["status"=>"error","message"=>$errMsg]);
    }
    exit;
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
                        <li class="breadcrumb-item active text-muted" aria-current="page">Rc Pdf Server (VAHAN API)</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="rc_get_list" class="btn btn-success">
                    <i class="bi bi-list-ul me-1"></i> RC Pdf List
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

    <div class="container-fluid pt-4 px-4">
      <div class="row">
        <!-- Form Section -->
        <div class="col-lg-5 mb-4">
          <div class="card">
            <div class="card-body">
              <div class="p-3 mb-4 rounded text-white" style="background: linear-gradient(135deg, #0062E6, #33AEFF);">
                <h3 class="text-center m-0 text-white">
                  <i class="bi bi-file-earmark-pdf-fill me-2"></i>
                  <strong>RC Owner Book PDF Download Instant</strong>
                </h3>
              </div>
            </div>
            
            <form id="rcForm">
              <div class="card-body">
                <div class="form-group">
                  <label for="rc_dl" class="card-title">Enter Vehicle Number</label>
                  <input type="text" name="rc_dl" id="rc_dl" class="form-control" required placeholder="MH12AB1234" oninput="removeSpaces(this)" style="text-transform:uppercase;">
                </div>

                <!-- Captcha Section -->
                <div class="form-group mt-3">
                  <label class="card-title">Captcha Verification</label>
                  <div class="input-group">
                    <input type="text" name="captcha" id="captcha" class="form-control" placeholder="Enter Captcha" required autocomplete="off">
                    <div class="input-group-text bg-white border" id="captchaBox" style="min-width: 140px; height: 38px; display:flex; align-items:center; justify-content:center; padding: 4px 8px; overflow:hidden;">
                        <span class="text-muted" style="font-size:12px;">Loading...</span>
                    </div>
                    <button type="button" class="btn btn-outline-secondary" id="refreshCaptcha" title="Refresh Captcha">↻</button>
                  </div>
                  <input type="hidden" id="session_id">
                  <input type="hidden" id="user_sid">
                </div>

                <div class="form-group mt-3">
                  <label for="type" class="card-title">Chip Type</label>
                  <select name="type" id="type" class="form-control">
                    <option value="2">Without Chip</option>
                    <option value="1">With Chip</option>
                  </select>
                </div>
              </div>
              
              <div class="text-center mb-3 px-3">
                <button type="submit" id="submitBtn" class="btn btn-success btn-block">
                  <i class="fa fa-check-circle"></i> Submit & Generate PDF
                </button>
              </div>

              <div class="col-12 d-flex justify-content-center">
                <div class="fee-container text-center">
                 <h5 class="text-warning">Application Fee: 
            <?php  
                $price = mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT price FROM pricing WHERE service_name='rc_vehical_fee'")); 
                echo "₹" . $price['price'];
            ?>
        </h5>
                </div>
              </div>
            </form>
          </div>
        </div>

        <!-- PDF Preview Section -->
        <div class="col-lg-7 mb-4">
          <div class="card h-100">
            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
              <h5 class="mb-0"><i class="bi bi-eye-fill me-2"></i>PDF Preview</h5>
              <a id="downloadBtn" href="#" download="RC_PDF.pdf" class="btn btn-light btn-sm" style="display:none;">
                    <i class="bi bi-download me-1"></i> Download PDF
              </a>
            </div>
            <div class="card-body text-center">
              <div id="pdfPreviewArea">
                <p class="text-muted mt-4">No preview available. Submit a valid vehicle number to view the RC PDF.</p>
              </div>
              
              <div class="mt-4">
                <p class="text-danger"><strong>Note:</strong> If any issue occurs, try Server Two or Three.</p>
                <a href="rc_get" class="btn btn-primary me-2">Try Server One</a>
                <a href="rc_get_father" class="btn btn-primary me-2">Try Father Server Three</a>
                <small class="text-danger d-block mt-1">*Use this if Father Name is missing or incorrect</small>
                <a href="rc_get_list" class="btn btn-secondary mt-2">PDF List</a>
              </div>
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
    width: 100%;
    margin-top: 10px;
  }
  .fee-container h5 {
    color: #ff9800;
    font-size: 18px;
    font-weight: 600;
    margin: 0;
  }
  /* Loading overlay for submit button */
  .btn-loading { position: relative; color: transparent !important; pointer-events: none; }
  .btn-loading::after {
    content: ""; position: absolute; width: 20px; height: 20px; top: 50%; left: 50%; margin-top: -10px; margin-left: -10px;
    border-radius: 50%; border: 3px solid #fff; border-top-color: transparent; animation: spin 1s ease-in-out infinite;
  }
  @keyframes spin { to { transform: rotate(360deg); } }
</style>

<!-- JS -->
<script>
function removeSpaces(input) {
    input.value = input.value.replace(/\s/g, "").toUpperCase();
}

 $(document).ready(function() {

    // Load Captcha Function (API 1)
    function loadCaptcha() {
        $('#captchaBox').html('<span class="text-muted spinner-border spinner-border-sm" role="status"></span>');
        $('#session_id').val('');
        $('#user_sid').val('');

        $.post('', {action: 'load_captcha'}, function(res) {
            if(res.status === 'success') {
                $('#captchaBox').html(res.captcha_svg);
                $('#session_id').val(res.session_id);
                $('#user_sid').val(res.user_sid);
            } else {
                $('#captchaBox').html('<span class="text-danger" style="font-size:11px;">Failed</span>');
                Swal.fire('Error', res.message || 'Captcha failed', 'error');
            }
        }).fail(function() {
            $('#captchaBox').html('<span class="text-danger" style="font-size:11px;">Error</span>');
        });
    }

    // Auto load captcha on page load
    loadCaptcha();
    
    // Refresh button click
    $('#refreshCaptcha').on('click', loadCaptcha);

    // Form Submit (API 2)
    $('#rcForm').on('submit', function(e) {
        e.preventDefault();

        var rc = $('#rc_dl').val().trim();
        var cap = $('#captcha').val().trim();
        var sid = $('#session_id').val();
        var usid = $('#user_sid').val();

        if(!rc || !cap || !sid || !usid) {
            Swal.fire('Missing', 'Enter RC Number and Captcha', 'warning');
            return;
        }

        var btn = $('#submitBtn');
        btn.addClass('btn-loading').prop('disabled', true);

        $.post('', {
            action: 'submit_rc',
            rc_dl: rc,
            captcha: cap,
            session_id: sid,
            user_sid: usid,
            card_type: $('#type').val()
        }, function(res) {
            btn.removeClass('btn-loading').prop('disabled', false);

            if(res.status === 'duplicate') {
                Swal.fire('Already Generated', res.message || 'Ye RC pehle se bana hua hai. History se download karein.', 'info').then(function(){
                    window.location.href = res.redirect || ('rc_get_list?rc=' + encodeURIComponent(rc));
                });
                return;
            }

            if(res.status === 'success') {
                // PRG pattern: direct list page pe bhejo taaki refresh/back pe dobara charge na ho
                var listUrl = 'rc_get_list?rc=' + encodeURIComponent(res.rc || rc) + '&success=1';
                Swal.fire({
                    title: 'Success!',
                    text: 'RC PDF Generated: ' + (res.rc || rc) + '\nHistory page pe redirect ho rahe hain...',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false,
                    allowOutsideClick: false,
                    allowEscapeKey: false
                });
                setTimeout(function(){ window.location.href = listUrl; }, 1200);
            } else {
                Swal.fire('Failed', res.message || 'Something went wrong', 'error');
                loadCaptcha(); // Refresh captcha on error
                $('#captcha').val('');
            }
        }).fail(function() {
            btn.removeClass('btn-loading').prop('disabled', false);
            Swal.fire('Error', 'Server request failed', 'error');
        });
    });
});
</script>

<?php include("footer.php");?>
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
    });
</script>
<script>
    $(document).ready(function() {
        var table = $('#example2').DataTable( {
            lengthChange: false,
            buttons: [ 'copy', 'excel', 'pdf', 'print']
        });
        table.buttons().container()
            .appendTo( '#example2_wrapper .col-md-6:eq(0)' );
    });
</script>
</body>
</html>