<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="https://jeremyfagis.github.io/dropify/dist/css/dropify.min.css">
    <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
</head>
<body>
<?php
include('header.php');

if (isset($_POST['find'])) {
    $aadhaar = mysqli_real_escape_string($ahk_conn, $_POST['aadhaar']);

    $price = mysqli_fetch_assoc(mysqli_query($ahk_conn, "SELECT * FROM pricing WHERE service_name='esharm_fee' "));
    $fee = $price['price'];
    
    // $fee = "50";
    $username = $udata['phone'];
    $wallet_amount = $udata['balance'];
    $debit_fee = $wallet_amount - $fee;

    if ($wallet_amount > $fee) {
        $api_key =   urlencode($flexsecure_key); // Replace with actual API key             //aadhar advance fingerprint api key power by axen
        $url = "https://" . htmlspecialchars($flexsecureapiurl) . "/serviceApi/FlexSecureV1/sharm/aaShramDetail.php?apiKey=$api_key&aadhaar=$aadhaar";
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ));

         $response = curl_exec($curl);
        curl_close($curl);
        $result = json_decode($response, true);
        // Continue processing the result
        $statusMessage = $result['message'];
        $check_code = $result['StatusCode'];
        $name = $result['name'];
        $fathername = $result['fathername'];
        $error = $result['error'];
        if ($result['StatusCode'] == "100") {
            $debit = mysqli_query($ahk_conn, "UPDATE users SET balance=balance-$fee WHERE phone='$username'");
            $updatehistory = mysqli_query($ahk_conn,"INSERT INTO `wallethistory`(`userid`, `amount`, `balance`, `purpose`, `status`, `type`) VALUES ('$username','$fee','$debit_fee','sharm PDF','1','Debit')");

        if ($updatehistory) {
                ?>
                <script>
                    $(function(){
                        Swal.fire(
                            'Data Fetched Successfully',
                            'Server : <?php echo $statusMessage ?>!',
                            'success'
                        )
                    });
                </script>
                <?php
            }
            }else{
                
                ?>
                <script>
                    $(function(){
                        Swal.fire(
                            '<?php echo $error ?>',
                            'Please Contact ADMIN!',
                            'warning'
                        )
                    })
                    setTimeout(() => {
                        window.location='#';
                    }, 3000);
                </script>
                <?php
            }
    } else {
        ?>
        <script>
            $(function(){
                Swal.fire(
                    'Opps',
                    'Wallet Balance Insufficient! Please Recharge ',
                    'error'
                )
            });
            window.setTimeout(function(){
                window.location.href='wallet.php';
            },4000);
        </script>
        <?php
    }
}
?>

<!--start page wrapper -->
		<div class="page-wrapper">
			<div class="page-content">
			
<div class="content-wrap">
    <div class="main">
        <div class="col-md-12">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-lg-6 col-md-6 col-sm-6">
                        <div class="card">
                            <div class="card-body">
                                <div class="alert alert-dark" role="alert">
                                    We Are Trying Our Best
                                    <a href="#" class="alert-link">AADHAAR NO TO AADHAR PHOTO DETAILS INSTANT</a>
                                </div>
                                <form name="" action="" method="post" id="Job_print">
                                    <div class="card-body">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label class="card-title" for="aadhaar">Enter Aadhaar Number</label>
                                                <input type="number" required="" class="form-control" name="aadhaar" id="aadhaar" placeholder="12 Digit Aadhaar Number"  oninput="removeSpaces(this)">
                                            </div>
                                        </div>
                                    </div>
                                     		<div class="col-12 ml-2">
									<h5 class="text-warning ">Application Fee: <?php  
										$price = mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT price FROM pricing WHERE service_name='esharm_fee'")); 
										echo "₹" .$price['price'];
										?></h5>
                            </div>
                                    <div class="row row-sm mg-t-20">
                                        <div class="col">
                                            <button type="submit" name="find" class="btn btn-primary w-100"><i class="fa fa-check-circle"></i> Submit</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
         <?php    if ($result['statusCode'] == "100") { ?>
                <div class="col-lg-6 col-md-6 col-sm-6">
               <div class="card">
                    <table class="table table-bordered" >
                        <thead >
                            <tr>
                                <th style="text-align:center;">Attribute</th>
                                <th style="text-align:center;">Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Name</td>
                                <td><?php echo $result['name'];?></td>
                            </tr>
                            <tr>
                                <td>Father's Name</td>
                                <td><?php echo $result['c/o'];?></td>
                            </tr>
                            <tr>
                                <td>Date OF Birth</td>
                                <td><?php echo $result['dobadhar'];?></td>
                            </tr>
                            <tr>
                                <td>Gender</td>
                                <td><?php echo $result['gender'];?></td>
                            </tr>
                            <tr>
                                <td>Mobile</td>
                                <td><?php echo $result['mobile'];?></td>
                            </tr>
                            <tr>
                                <td>Email</td>
                                <td><?php echo $result['email'];?></td>
                            </tr>
                            <tr>
                                <td>e-Sharm Number</td>
                                <td><?php echo $result['uan'];?></td>
                            </tr>
                            <tr>
                                <td>Address</td>
                                <td><?php echo $result['address'];?></td>
                            </tr>
                            <tr>
                                <td>Photo</td>
                                <td><img src="<?php echo $result['image'];?>" alt="Photo" style="width: 100px; height: 100px;"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                </div>
            <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function removeSpaces(inputElement) {
    inputElement.value = inputElement.value.replace(/\s/g, '');
}
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