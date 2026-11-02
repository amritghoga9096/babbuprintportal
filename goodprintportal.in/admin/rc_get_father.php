<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');
$pdf_preview = '';
$show_preview = false;

if (isset($_POST['find'])) {
    // Sanitize input
    $rc_no = strtoupper(trim(mysqli_real_escape_string($ahk_conn, $_POST['rc_dl'])));
    $type = mysqli_real_escape_string($ahk_conn, $_POST['type']);
   // Father name: auto-fill N/A if empty
$FName = trim($_POST['fathername'] ?? '');
$FName = $FName === '' ? 'N/A' : strtoupper($FName);
$FatherName = urlencode($FName);

    // Get price
    $price = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT price FROM pricing WHERE service_name='rc_vehical_fee'"));
    $fee = (float)$price['price'];
    $username = $udata['phone'];
    $wallet_amount = (float)$udata['balance'];
    $debit_fee = $wallet_amount - $fee;

    if ($wallet_amount >= $fee) {
        // Prepare API URL
        $api_key = urlencode($flexsecure_key);
        $api_url = "https://" . htmlspecialchars($flexsecureapiurl) . "/serviceApi/FlexSecureV1/RC/rc_father_pdf.php?apiKey={$api_key}&rcno={$rc_no}&type={$type}&FatherName={$FatherName}";

        // Call API
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $api_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 90,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        $result = json_decode($response, true);

        $statusMessage = $result['statusMessage'] ?? '';
        $check_code = $result['statusCode'] ?? '';
        $MSG = $result['message'] ?? 'No message from API';
        $pdf_base64 = $result['pdf'] ?? '';

        if (empty($pdf_base64) || $check_code != "200") {
            echo "
            <script>
                $(function () {
                    Swal.fire('$MSG', 'Contact ADMIN', 'warning');
                });
                setTimeout(() => window.location.href = 'rc_get_father', 20000);
            </script>";
        } else {
            // Store PDF and update wallet
            $file = $pdf_base64;
            mysqli_query($ahk_conn, "UPDATE users SET balance=balance-$fee WHERE phone='$username'");
            date_default_timezone_set("Asia/Kolkata");
            $time_hkb = date('d/m/Y g:i:s');

            mysqli_query($ahk_conn, "INSERT INTO `rc_vehical`(`username`, `rc_vehical_no`, `status`, `pdf`, `date`) 
                                     VALUES ('$username','$rc_no', '$MSG','$file','$time_hkb')");

            mysqli_query($ahk_conn, "INSERT INTO `wallethistory`(`userid`, `amount`, `balance`, `purpose`, `status`, `type`) 
                                     VALUES ('$username','$fee','$debit_fee','RC Vehical PDF','1','Debit')");

            $pdf_preview = $file;
            $show_preview = true;

            echo "
            <script>
                $(function () {
                    Swal.fire(
                        'Download Successful: RC $rc_no',
                        'PDF Generated Successfully',
                        'success'
                    );
                });
            </script>";
        }
    } else {
        echo "
        <script>
            $(function () {
                Swal.fire('Oops', 'Wallet Balance Insufficient! Please Recharge', 'error');
            });
            setTimeout(() => window.location.href = 'wallet', 4000);
        </script>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<!-- Page HTML -->
<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb with Action Buttons -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">Rc Father Server</li>
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
                            <div class="p-3 mb-4 text-white rounded" style="background: linear-gradient(135deg, #0062E6, #33AEFF);">
                                <h3 class="text-center m-0"><i class="bi bi-file-earmark-pdf-fill me-2"></i><strong>RC Owner Book PDF Download Instant</strong></h3>
                            </div>
                            <form method="post" id="Job_print">
                                <div class="card-body">
                                    <div class="form-group">
                                        <label for="rc_dl">Enter Vehicle Number</label>
                                        <input type="text" required class="form-control" name="rc_dl" id="rc_dl" placeholder="BH01XX1454" oninput="removeSpaces(this)">
                                    </div>
                                  <div class="form-group mt-3">
    <label for="fathername">Enter Father Name</label>
    <input type="text" class="form-control" name="fathername" id="fathername" placeholder="Owner Father Name">
    <small class="text-danger mt-1 d-block">
        *Father Name is optional. If not found via RC API, the entered value will be used.*
    </small>
</div>

                                    <div class="form-group mt-3">
                                        <label for="type">Chip Type</label>
                                        <select class="form-control" name="type">
                                            <option value="new">With Chip</option>
                                            <option value="old">Without Chip</option>
                                        </select>
                                    </div>
                                    <div class="text-center mt-4">
                                        <button type="submit" name="find" class="btn btn-success">
                                            <i class="fa fa-check-circle"></i> Submit
                                        </button>
                                    </div>
                                    <div class="col-12 d-flex justify-content-center mt-3">
                                        <div class="fee-container text-center">
                                           <h5 class="text-warning">Application Fee: 
            <?php  
                $price = mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT price FROM pricing WHERE service_name='rc_vehical_fee'")); 
                echo "₹" . $price['price'];
            ?>
        </h5>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Preview Section -->
                <div class="col-lg-7 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-info text-white">
                            <h5><i class="bi bi-eye-fill me-2"></i>PDF Preview</h5>
                        </div>
                        <div class="card-body text-center">
                            <?php if ($show_preview && !empty($pdf_preview)) { ?>
                                <embed src="<?php echo $pdf_preview; ?>" width="100%" height="500px" type="application/pdf">
                                <div class="mt-3">
                                    <a href="<?php echo $pdf_preview; ?>" download="RC_PDF_<?php echo $rc_no; ?>.pdf" class="btn btn-success">
                                        <i class="bi bi-download"></i> Download PDF
                                    </a>
                                </div>
                            <?php } else { ?>
                                <p class="text-muted mt-4">No preview available. Submit a valid vehicle number to view the RC PDF.</p>
                            <?php } ?>
                            <!-- Additional Buttons -->
                           <div class="mt-4">
    <p class="text-danger"><strong>Note:</strong> If any issue occurs, try Server Two or Three.</p>

    <a href="rc_get_two" class="btn btn-primary me-2">Try Server Two</a>
    <a href="rc_get_father" class="btn btn-primary me-2">Try Father Server Three</a>
    <small class="text-danger d-block mt-1">*Use this if Father Name is missing or incorrect*</small>

    <a href="rc_get_list" class="btn btn-secondary mt-2">PDF List</a>
</div>

                        </div>
                    </div>
                </div>
            </div> <!-- /.row -->
        </div> <!-- /.container -->
    </div>
</div>

<!-- Styling -->
<style>
    .fee-container {
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        padding: 10px 15px;
        max-width: 300px;
        margin-top: 10px;
    }
    .fee-container h5 {
        color: #ff9800;
        font-weight: 600;
    }
</style>

<!-- JS -->
<script>
function removeSpaces(input) {
    input.value = input.value.replace(/\s/g, "");
}
</script>
</html>



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