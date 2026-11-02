<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');
date_default_timezone_set('Asia/Kolkata');
$date_today = date("d-m-Y");
$date_time =  date("d-m-Y h:i:s A");


$price = mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT * FROM pricing WHERE service_name='gazette_certificate_fireapi' "));
$fee = !empty($price['price']) ? $price['price'] : 10;
      
        $balance = $udata['balance'];

$username = $udata['phone'];


if ($balance < $fee+1) {?>
    <script>
                    $(function(){
                        Swal.fire(
                            'Wallet amount is Low Please Maintain Wallet Above From Fee ',
                            '',
                            'error'
                        )
                    })
                    setTimeout(() => {
                        window.location='wallet.php';
                    }, 1000);
                </script>
<?php }
if(isset($_POST['o_name'])){
  
	$o_name = mysqli_real_escape_string($ahk_conn,$_POST['o_name']);
	$n_name = mysqli_real_escape_string($ahk_conn,$_POST['n_name']);
	$fname = mysqli_real_escape_string($ahk_conn,$_POST['fname']);
	$dob = "";
	$state = mysqli_real_escape_string($ahk_conn,$_POST['state']);
	$pincode = mysqli_real_escape_string($ahk_conn,$_POST['pincode']);
	$district = mysqli_real_escape_string($ahk_conn,$_POST['district']);
	$block = mysqli_real_escape_string($ahk_conn,$_POST['block']);
	$village = mysqli_real_escape_string($ahk_conn,$_POST['village']);
	$post = mysqli_real_escape_string($ahk_conn,$_POST['post']);
	$landmark = mysqli_real_escape_string($ahk_conn,$_POST['landmark']);
	$house = mysqli_real_escape_string($ahk_conn,$_POST['house']);

	if($balance >= $fee){
  
        $username = $udata['phone'];
          $userid = $udata['userid'];
                                  


$data = [
    'o_name' => $o_name,
    'n_name' => $n_name,
    'house' => $house,
    'fname' => $fname,
    'dob' => $dob,
    'landmark' => $landmark,
    'pincode' => $pincode,
    'state' => $state,
    'district' => $district,
    'block' => $block,
    'post' => $post,
    'village' => $village,
    'apiKey' => "FAf171389120dde67fd7da2f9514dc76d32c84341a7edd3d3e65e05baae99191",
    'order_id' => $application_no
];

// Build query string
$query = http_build_query($data);

// Append to URL
$url = 'https://fireapi.info/serviceApi/V1/gazette_certificate.php?' . $query;

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_HTTPGET => true, // Make sure it's GET
]);

$response = curl_exec($curl);
        curl_close($curl);

        $resdata = json_decode($response, true);
        $message = $resdata['message'];
        $StatusCode = $resdata['StatusCode'];
        $pdf = $resdata['pdf'];


 if ($resdata['StatusCode'] == "100") {

		
		$res = mysqli_query($ahk_conn,"INSERT INTO `gazette_certificate_fireapi`(`o_name`, `n_name`, `fname`, `dob`, `state`, `pincode`, `district`, `block`, `village`, `post`, `landmark`, `house`, `date`,`pdf`,`username`) VALUES  ('$o_name','$n_name','$fname','$dob','$state','$pincode', '$district', '$block','$village', '$post','$landmark', '$house',  '$date_time','$pdf','$username')");
		if($res){
		    
		    $debit = mysqli_query($ahk_conn,"UPDATE users SET balance=balance-$fee WHERE phone='$username'");
                                                    
                          
		    $nbalance = $balance - $fee;
		  
		    
		     $updatehistory = mysqli_query($ahk_conn,"INSERT INTO `wallethistory`(`userid`, `amount`, `balance`, `purpose`, `status`, `type`,`date_today`, `charge`) VALUES ('$username','$fee','$nbalance','Gazette Certificate Manual ','1','Debit','$date_today','$charge')");
                                 
                                 
                                 
		    
		    
            ?>
			<script>
            $(function(){
                Swal.fire(
                    'Success',
                    'Application Submitted Successfully!',
                    'success'
                )
            });
              window.setTimeout(function() {
  window.location.href = "gazette_certificate_list_fireapi.php";
  }, 1500);
        </script>
        <?php 
    }else{
        ?>
        <script>
            $(function(){
                Swal.fire(
                    'Opps',
                    'Internal Server Error!',
                    'error'
                )
            });
             window.setTimeout(function() {
  window.location.href = "gazette_certificate_fireapi.php";
  }, 1500);
        </script>
        <?php
    }




 }else{
            ?>
        <script>
            $(function(){
                Swal.fire(
                    'Error',
                    '<?php echo $message ?>',
                    'error'
                );
            });
           
        </script>
        <?php
        }
        
        
    }else{
        ?>
        <script>
            $(function(){
                Swal.fire(
                    'Opps',
                    'Wallet Balance Insufficient ! Please Recharge ',
                    'error'
                )
            });
            window.setTimeout(function(){
                window.location.href='wallet.php';
            },1500);
            
        </script>
        <?php
    }


    
}
?>

   
    <style>
    @media print {

        html,
        body {
            display: none;
        }
    }
    </style>
</head>

<body>

 	<div class="page-wrapper">
			<div class="page-content">
			    
			    
			    
			      <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">Gazette Certificate (Manual)</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="gazette_certificate_list_fireapi.php" class="btn btn-success">
                    <i class="bi bi-list-ul me-1"></i>Print List
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
        
        
				<!--breadcrumb-->
				
				<!--end breadcrumb-->
				<div class="row">
					<div class="col-xl-10 mx-auto">
					
						<div class="card border-top border-0 border-4 border-primary">
							<div class="card-body p-5">
								
								<hr>

            
            
							
				
								<?php if($msg !='') { ?>
									<div style="width=100%"  class="row cvmsgok"><?php echo $msg; ?></div>
								<?php } elseif($msgno !='') { ?>
									<div style="width=100%"  class="row cvmsgno"><?php echo $msgno; ?></div>
								<?php  } ?>
						
								  <form id="regform" method="post" enctype="multipart/form-data" class="form-horizontal"
                            autocomplete="off" onsubmit="myButton.disabled = true; return true;">
                            <div class="form-group">
                                <div class="row">
                                  
                                  <div class="col-md-4">
    <label for="o_name" style="color: blue;">Old Name</label>
    <input 
        type="text" 
        placeholder="Enter Old Name" 
        id="o_name" 
      style="text-transform:uppercase;"
        name="o_name" 
        class="form-control" 
        required
        
    >
</div>
   <div class="col-md-4">
    <label for="n_name" style="color: blue;">New Name</label>
    <input 
        type="text" 
        placeholder="Enter New Name" 
        id="n_name" 
      style="text-transform:uppercase;"
        name="n_name" 
        class="form-control" 
        required
        
    >
</div>



<div class="col-md-4">
    <label for="fname" style="color: blue;">Father Name</label>
    <input id="fname" style="text-transform:uppercase;"  placeholder="Enter Father Name" required  name="fname" type="text" class="form-control" required>
</div>

<div class="col-md-4">
    <label for="house" style="color: blue;">House/Flat No.</label>
    <input type="text" oninput="capitalizeFirst(this)" placeholder="Enter House/Flat No." required id="house" name="house" class="form-control" required>
</div>

<div class="col-md-4">
    <label for="landmark" style="color: blue;">Landmark</label>
    <input type="text" oninput="capitalizeFirst(this)"  placeholder="Enter Landmark" required id="landmark" name="landmark" class="form-control" required>
</div>
<div class="col-md-4">
    <label for="village" style="color: blue;">Village/Locality</label>
    <input type="text" oninput="capitalizeFirst(this)"  placeholder="Enter Village/Locality" required id="village" name="village" class="form-control" required>
</div>
<div class="col-md-4">
    <label for="post" style="color: blue;">Post</label>
    <input type="text" oninput="capitalizeFirst(this)"  placeholder="Enter Post" required id="post" name="post" class="form-control" required>
</div>
<div class="col-md-4">
    <label for="block" style="color: blue;">Block</label>
    <input type="text" oninput="capitalizeFirst(this)"  placeholder="Enter Block" required id="block" name="block" class="form-control" required>
</div>

<div class="col-md-4">
    <label for="district" style="color: blue;">District</label>
    <input type="text" oninput="capitalizeFirst(this)"  placeholder="Enter District" required id="district" name="district" class="form-control" required>
</div>
<div class="col-md-4">
    <label for="pincode" style="color: blue;">PIN Code</label>
    <input type="number"  placeholder="Enter PIN Code" required id="pincode" name="pincode" class="form-control" required>
</div>

<div class="col-md-4">
    <label for="state" style="color: blue;" class="form-label">State</label>
                             <select name="state" id="state" required="" class="form-control">
										      <option value="">Select</option>
                                                            <option value="ANDAMAN AND NICOBAR ISLANDS">ANDAMAN AND NICOBAR ISLANDS</option>
                                                            <option value="ANDHRA PRADESH">ANDHRA PRADESH</option>
                                                             <option value="ARUNACHAL PRADESH">ARUNACHAL PRADESH</option>
                                                              <option value="ASSAM">ASSAM</option>
                                                               <option value="BIHAR">BIHAR</option>
                                                                <option value="CHANDIGARH">CHANDIGARH</option>
                                                                 <option value="CHHATTISGARH">CHHATTISGARH</option>
                                                                  <option value="DADRA AND NAGAR HAVELI AND DAMAN AND DIU">DADRA AND NAGAR HAVELI AND DAMAN AND DIU</option>
                                                                   <option value="DELHI">DELHI</option>
                                                                    <option value="GOA">GOA</option>
                                                                     <option value="GUJARAT">GUJARAT</option>
                                                                      <option value="HARYANA">HARYANA</option>
                                                                       <option value="HIMACHAL PRADESH">HIMACHAL PRADESH</option>
                                                                        <option value="JAMMU AND KASHMIR">JAMMU AND KASHMIR</option>
                                                                         <option value="JHARKHAND">JHARKHAND</option>
                                                                          <option value="KARNATAKA">KARNATAKA</option>
                                                                           <option value="KERALA">KERALA</option>
                                                                            <option value="LAKSHADWEEP">LAKSHADWEEP</option>
                                                                            <option value="MADHYA PRADESH">MADHYA PRADESH</option>
                                                                            <option value="MAHARASHTRA">MAHARASHTRA</option>
                                                                            <option value="MANIPUR">MANIPUR</option>
                                                                            <option value="MEGHALAYA">MEGHALAYA</option>
                                                                            <option value="MIZORAM">MIZORAM</option>
                                                                            <option value="NAGALAND">NAGALAND</option>
                                                                            <option value="ODISHA">ODISHA</option>
                                                                            <option value="PUDUCHERRY">PUDUCHERRY</option>
                                                                            <option value="PUNJAB">PUNJAB</option>
                                                                            <option value="RAJASTHAN">RAJASTHAN</option>
                                                                            <option value="SIKKIM">SIKKIM</option>
                                                                            <option value="TAMIL NADU">TAMIL NADU</option>
                                                                            <option value="TELANGANA">TELANGANA</option>
                                                                            <option value="TRIPURA">TRIPURA</option>
                                                                            <option value="UTTAR PRADESH">UTTAR PRADESH</option>
                                                                            <option value="UTTARAKHAND">UTTARAKHAND</option>
                                                                            <option value="WEST BENGAL">WEST BENGAL</option>
                                                                           
                                                           
                                                        </select>
</div>
</div>
              <br>
                            	<div class="col-12 ml-2">
									<h5 class="text-warning ">Application Fee: ₹<?php  
										
										echo $fee;
										?></h5><br>
									 <button type="submit" name="myButton" class="btn btn-success btn-block"><i
                                        class="fa fa-floppy-o"></i>Submit</button>
									</div>
									
									
									
                                    
                                        </div><div class="form-group">
                               
                                   </div>
                                    </div>
                                    <div class="col-md-1"></div>
                                </div>
                            </div>
                            
                        </form>
					
							</div>
							<!-- /# row -->
						</section>
					</div>
				</div>
            </div>
        </div>


      


  </script>
<div id="myModal" class="modal fade" role="dialog">
  <div class="modal-dialog">

    <!-- Modal content-->
    <div class="modal-content">
      
      <div class="modal-body">
        <img src="<?php echo $slct['aafile'];?>" width="<?php echo $slct['iwidth'];?>" height="<?php echo $slct['iheight'];?>"/>
      </div>
      
    </div>
<button type="button" class="btn btn-default" data-dismiss="modal" style="    position: absolute;
    top: -20px;
    right: -422px;
    border-radius: 50%;
    background: red;
	border-color:red;
    color: white;">X</button>
  </div>
</div>
<style>
.modal-body
{
	flex:inherit !important;
	padding:inherit !important;
}
.modal-footer
{
	border:none !important;
}
.modal-dialog {
	margin: 30px 248px auto !important;
    max-width: auto !important;
	width : 100% !important;
}
</style>
	<!--end page wrapper -->
		<?php 
		include('footer.php');
		?>
	<!-- Bootstrap JS -->
	
<script>
document.getElementById("dob").max = new Date().toISOString().split("T")[0];
document.getElementById("doi").max = new Date().toISOString().split("T")[0];
document.getElementById("dor").max = new Date().toISOString().split("T")[0];
</script>

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
	$('#pan_no').inputmask();
	$('#timea').inputmask("hh:mm:ss", {
        placeholder: "00:00:00", 
        insertMode: false, 
        showMaskOnHover: false,
        hourFormat: 12
      });
	});
</script>
<script>
function capitalizeFirst(input) {
input.value = input.value.toLowerCase().replace(/\b\w/g, function(char) {
return char.toUpperCase();
});
}
</script>
</html>