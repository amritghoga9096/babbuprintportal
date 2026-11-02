<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');

// Default fee if nothing matches
$normal_puc_fee = 100;

if (isset($_POST['name']) && !empty($_POST['name']) && !empty($_POST['mobile']) && !empty($_POST['gadi'])) {
    $name = mysqli_real_escape_string($ahk_conn, $_POST['name']);
    $mobile = mysqli_real_escape_string($ahk_conn, $_POST['mobile']);
    $gadi = mysqli_real_escape_string($ahk_conn, $_POST['gadi']);

    // Fetch fee from pricing table based on selected gadi
    $fee_result = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT price FROM pricing WHERE service_name='$gadi'"));
    $fee = $fee_result ? (int)$fee_result['price'] : $normal_puc_fee;

    $application_no = "PUCC" . rand(10000, 99999);
    $appliedby = $udata['phone'];
    $date = date("jS \of F Y h:i:s A");

    if ($udata['balance'] >= $fee) {
        $nbal = $udata['balance'] - $fee;

        // Upload Aadhaar
        $type = $_FILES['aadhaar']['type'];
        if ($type == "application/pdf" || $type == "image/jpeg" || $type == "image/png" || $type == "image/jpg") {
            $ext = rand(100000, 999999) . $_FILES['aadhaar']['name'];
            $upload = "uploads/";
            $link = "https://" . $host . $dir . "/uploads/" . $ext;

            if (move_uploaded_file($_FILES['aadhaar']['tmp_name'], $upload . $ext)) {
                $debit = mysqli_query($ahk_conn, "UPDATE users SET balance=balance-$fee WHERE phone='$appliedby'");
                $updatehistory = mysqli_query($ahk_conn, "INSERT INTO `wallethistory`(`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES ('$appliedby','$fee','$nbal','New Puc Apply','1','Debit')");

                if ($debit && $updatehistory) {
                    $submit = mysqli_query($ahk_conn, "INSERT INTO `New_Pucc` (`appliedby`, `application_no`, `name`, `mobile`, `gadi`, `status`, `fee`,`date`,`aadhaar_pdf`) VALUES('$appliedby','$application_no','$name','$mobile','$gadi','pending','$fee','$date','$ext')");
                    if ($submit == true) {
                        ?>
                        <script>
                            $(function () {
                                Swal.fire('Application Submitted Successfully', '', 'success');
                            });
                            setTimeout(() => {
                                window.location = '';
                            }, 1200);
                        </script>
                        <?php
                    } else {
                        ?>
                        <script>
                            Swal.fire('Oops', 'Try Again', 'error');
                            setTimeout(() => {
                                window.location = '';
                            }, 1200);
                        </script>
                        <?php
                    }
                } else {
                    ?>
                    <script>
                        Swal.fire('Oops', 'Try Again', 'error');
                        setTimeout(() => {
                            window.location = '';
                        }, 1200);
                    </script>
                    <?php
                }
            } else {
                ?>
                <script>
                    Swal.fire('Error While Uploading File!', '', 'error');
                </script>
                <?php
            }
        } else {
            ?>
            <script>
                Swal.fire('Wrong File Type!', 'Only PDF, JPG, JPEG, PNG allowed.', 'error');
            </script>
            <?php
        }
    } else {
        ?>
        <script>
            Swal.fire('Wallet Balance is Low!', 'Please Recharge Now!', 'error');
            setTimeout(() => {
                window.location = 'wallet';
            }, 1200);
        </script>
        <?php
    }
}
?>

<!-- HTML START -->
<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb with Action Buttons -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">Puc</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="puc_apply_list" class="btn btn-success">
                    <i class="bi bi-list-ul me-1"></i>Puc List
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0"></ol>
                </nav>
            </div>
        </div>

        <div class="col-18">
            <div class="bg-info p-2 mb-3 font-weight-bold">
                <div class="row">
                    <h3><strong>New Pucc Certificate</strong></h3>
                    <h5>DELIVERY TIME - 02-06 HOURS</h5>
                    <h5>Disclaimer: <b>Will be made from all states</b></h5>
                </div>
            </div>
        </div>

        <h8><b>After purchasing a vehicle and completing the PUC test, a certificate is issued. It must be renewed every 6 months. If emissions are hazardous, the nearest RTO is notified.</b></h8>
        <br><br>

        <div class="card border-top border-0 border-4 border-primary">
            <div class="card-body p-5">
                <form action="" method="POST" class="row g-3" enctype="multipart/form-data">
                    <div class="col-md-3">
                        <label class="form-label">Gadi Number <span style='color:red'>*</span></label>
                        <input name="name" type="text" placeholder="Enter Gadi Number" required class="form-control">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Mobile Number <span style='color:red'>*</span></label>
                        <input name="mobile" type="text" placeholder="Enter Mobile Number" required class="form-control">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Upload Image <span style='color:red'>*</span></label>
                        <input type="file" name="aadhaar" accept=".pdf,.jpeg,.jpg,.png" required class="form-control">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Gadi Type <span style='color:red'>*</span></label>
                        <select name="gadi" class="form-control" required>
                            <option value="">Select Gadi</option>
                            <option value="bike_2_whiller_fee">ALL BIKE 2 WHILLER ₹<?php  
                                $p = mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT price FROM pricing WHERE service_name='bike_2_whiller_fee'")); 
                                echo $p['price'];
                            ?></option>

                            <option value="all_petrol_and_cng_fee">ALL PETROL & CNG ₹<?php  
                                $p = mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT price FROM pricing WHERE service_name='all_petrol_and_cng_fee'")); 
                                echo $p['price'];
                            ?></option>

                            <option value="all_diesel_fee">ALL DIESEL ₹<?php  
                                $p = mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT price FROM pricing WHERE service_name='all_diesel_fee'")); 
                                echo $p['price'];
                            ?></option>
                        </select>
                    </div>

                    <div class="col-12">
                        <button class="btn btn-success" type="submit" onclick="return confirm('Are You Sure ?');">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- HTML END -->

										 		 		 <!--<button class="btn btn-danger font-weight-bold" type="button">Service Down</button>-->
								<!--start list page -->
 
<!--end page wrapper -->
<?php 
include('footer.php');
?>
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