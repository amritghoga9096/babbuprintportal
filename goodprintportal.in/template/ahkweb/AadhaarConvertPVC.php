<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Bootstrap & Font Awesome -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    
    <!-- jQuery & SweetAlert -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    
    <style>
        .spinner-border {
            display: none;
            width: 1rem;
            height: 1rem;
            border-width: 0.15em;
        }
    </style>
</head>
<body>

<?php
include('header.php');
if (isset($_POST['pvc']) && $_POST['pvc'] == "AxenApi") {

    $price = mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT price FROM pricing WHERE service_name='convert_PVC_fee'")); 
    $fee = $price['price'];
    $username = $udata['phone'];
    $wallet = $udata['balance'];

    if ($wallet > $fee) {

        $file = $_FILES['file'];
        $password = $_POST['password'];
        $type = $_POST['type'];
        $mobile = $_POST['mobile'];

        $ch = curl_init();
        $url = "https://" . htmlspecialchars($flexsecureapiurl) . "/serviceApi/FlexSecureV1/Aadhaar/AadharPdfconvert";
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $postData = array(
            'apiKey' => urlencode($flexsecure_key),
            'mobile' => $mobile,
            'type' => $type,
            'password' => $password,
            'file' => new CURLFile($file['tmp_name'], $file['type'], $file['name'])
        );

        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            echo "<script>alert('Error: $error_msg')</script>";
        } else {
            $json = json_decode($response, true);
            $status = $json['statusMessage'];
            $error_api = $json['error'];

            if ($status === 'Success') {
                date_default_timezone_set('Asia/Kolkata');
                $timestamp = date("Y-m-d H:i:s");
                $front = $json["front"];
                $back = $json["back"];
                $pdf = $json["pdf"];
                $debit_fee = $wallet - $fee;

                mysqli_query($ahk_conn,"UPDATE `users` SET balance='$debit_fee' WHERE phone='$username'");
                mysqli_query($ahk_conn,"INSERT INTO `wallethistory`(`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES ('$username','$fee','$debit_fee','AADHAR PDF Convert PVC','1','Debit')");
                $insert = mysqli_query($ahk_conn, "INSERT INTO `AxenAadhar_PVC`(`front`, `back`, `pdf`, `username`, `date`) VALUES ('$front', '$back','$pdf', '$username', '$timestamp')");

                if($insert){
                    echo "<script>
                        $(function(){
                            Swal.fire('PVC Converted Successfully', 'Success', 'success');
                        });
                        setTimeout(() => {
                            window.location='AadhaarConvertPVC_List.php';
                        }, 1200);
                    </script>";
                } else {
                    echo "<script>alert('Data Insert Failed | Please Upload SQL In Database')</script>";
                }

            } else {
                echo "<script>alert('$error_api')</script>";
            }
        }

        curl_close($ch);

    } else {
        echo "<script>
            $(function(){
                Swal.fire('Oops', 'Wallet Balance Insufficient! Please Recharge.', 'error');
            });
            setTimeout(function(){
                window.location.href='wallet.php';
            }, 1500);
        </script>";
    }
}
?>

<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb with Action Buttons -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">Aadhaar PVC Converter</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="AadhaarConvertPVC_List" class="btn btn-success">
                    <i class="bi bi-list-ul me-1"></i> List
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
        <div class="content-wrap">
            <div class="main">
                <div class="col-md-12">
                    <div class="main-content">
                        <div class="section-header">
                            <div class="container-fluid"></div>
                        </div>

                        <div class="container-fluid">
                            <div class="card col-12">
                                <hr>
                                <div class="row">
                                    <div class="col-lg-4 col-md-6 col-sm-6">
                                        <div class="card">
                                            
                                            <div class="card-body">
                                                <div class="alert alert-dark" role="alert">
                                                    AADHAR PVC CONVERTER <i class="fa fa-check-circle" style="color:green"></i>
                                                    <h6><b>Note: आधार UIDAI के Official वेबसाइट से ही डाउनलोड करें, तभी PVC बनेगा  
                                                    <i class="fa fa-check-circle" style="color:black"></i></b></h6>
                                                </div>
                                                <div class="form-group d-flex justify-content-center">
                                                    <h5 class="text-warning">
                                                        Application Fee: 
                                                        ₹<?php  
                                                            $price = mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT price FROM pricing WHERE service_name='convert_PVC_fee'")); 
                                                            echo $price['price'];
                                                        ?>
                                                    </h5>
                                                </div>

                                                <form action="" method="POST" enctype="multipart/form-data">
                                                    <div class="form-group">
                                                        <label for="file">Aadhar PDF</label>
                                                        <input type="file" class="form-control" name="file" accept="application/pdf" required>
                                                        <input type="hidden" name="pvc" value="AxenApi" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="password">PDF Password</label>
                                                        <input type="text" class="form-control" name="password" placeholder="PUNI2002" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="type">Background Color<span class="text-danger">*</span></label>
                                                        <select name="type" class="form-control" required>
                                                            <option value="Yellow">Yellow</option>
                                                            <option value="White">White</option>
                                                        </select>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="mobile">Mobile<span class="text-danger">*</span></label>
                                                        <select name="mobile" class="form-control" required>
                                                            <option value="Yes">Yes</option>
                                                            <option value="No">No</option>
                                                        </select>
                                                    </div>

                                                    <button type="submit" name="get_aadhar" id="submitBtn" class="btn btn-primary">
                                                        <span class="btn-text"><b>CONVERT PVC</b></span>
                                                        <i class="fa fa-check-circle icon-check" style="color:black"></i>
                                                        <span class="" role="status" aria-hidden="true"></span>
                                                    </button>
                                                </form>
                                                
                                                
                                            </div>
                                        </div>
                                    </div>
                                </div><!-- /.row -->
                            </div><!-- /.card -->
                        </div><!-- /.container-fluid -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loader Script -->
<script>
$(document).ready(function () {
    $('form').on('submit', function () {
        const btn = $('#submitBtn');
        btn.find('.icon-check').hide();
        btn.find('.btn-text').text('Processing...');
        btn.find('.spinner-border').show();
        btn.prop('disabled', true);
    });
});
</script>

</body>
</html>


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