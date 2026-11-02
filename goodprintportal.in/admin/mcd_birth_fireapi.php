<?php
include('../includes/session.php');
include('../includes/config.php');
include('../template/ahkweb/header.php');
date_default_timezone_set('Asia/Kolkata');
$date_today = date("d-m-Y");
$date_time =  date("d-m-Y h:i:s A");


$price = mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT * FROM pricing WHERE service_name='mcd_birth_fireapi' "));
$fee = !empty($price['price']) ? $price['price'] : 15;
      
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
if(isset($_POST['name'])){
  
	$name = mysqli_real_escape_string($ahk_conn,$_POST['name']);
$gender = mysqli_real_escape_string($ahk_conn,$_POST['gender']);
	$fname = mysqli_real_escape_string($ahk_conn,$_POST['fname']);
	$dob = mysqli_real_escape_string($ahk_conn,$_POST['dob']);
	$pob = mysqli_real_escape_string($ahk_conn,$_POST['pob']);
	$mname = mysqli_real_escape_string($ahk_conn,$_POST['mname']);
	$tob = mysqli_real_escape_string($ahk_conn,$_POST['tob']);
	$pa = mysqli_real_escape_string($ahk_conn,$_POST['pa']);
	$doi = mysqli_real_escape_string($ahk_conn,$_POST['doi']);
	$dor = mysqli_real_escape_string($ahk_conn,$_POST['dor']);
	$regno = mysqli_real_escape_string($ahk_conn,$_POST['regno']);

	if($balance >= $fee){
  
        $username = $udata['phone'];
          $userid = $udata['userid'];
                                  


$data = [
    'name' => $name,
    'gender' => $gender,
    'fname' => $fname,
    'dob' => $dob,
    'pob' => $pob,
    'mname' => $mname,
    'tob' => $tob,
    'pa' => $pa,
    'doi' => $doi,
    'dor' => $dor,
    'regno' => $regno,
    'apiKey' => "FAf171389120dde67fd7da2f9514dc76d32c84341a7edd3d3e65e05baae99191",
    'order_id' => $application_no
];

// Build query string
$query = http_build_query($data);

// Append to URL
$url = 'https://fireapi.info/serviceApi/V1/mcd_birth.php?' . $query;

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

		
		$res = mysqli_query($ahk_conn,"INSERT INTO `mcd_birth_fireapi`(`name`, `gender`, `fname`, `dob`, `pob`, `mname`, `tob`, `pa`, `doi`, `dor`, `regno`, `username`, `date`,`pdf`) VALUES  ('$name','$gender','$fname','$dob','$pob','$mname', '$tob', '$pa','$doi', '$dor','$regno', '$username',  '$date_time','$pdf')");
		if($res){
		    
		    $debit = mysqli_query($ahk_conn,"UPDATE users SET balance=balance-$fee WHERE phone='$username'");
                                                    
                          
		    $nbalance = $balance - $fee;
		  
		    
		     $updatehistory = mysqli_query($ahk_conn,"INSERT INTO `wallethistory`(`userid`, `amount`, `balance`, `purpose`, `status`, `type`,`date_today`, `charge`) VALUES ('$username','$fee','$nbalance','MCD Birth Manual ','1','Debit','$date_today','$charge')");
                                 
                                 
                                 
		    
		    
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
  window.location.href = "mcd_birth_list_fireapi.php";
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
  window.location.href = "mcd_birth_fireapi.php";
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
				<!--breadcrumb-->
				
				<!--end breadcrumb-->
				<div class="row">
					<div class="col-xl-10 mx-auto">
						<h6 class="mb-0 text-uppercase">MCD Birth Manual (Fire API)</h6>
						<hr/>
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
    <label for="name" style="color: blue;">Full Name</label>
    <input 
        type="text" 
        placeholder="Enter Full Name" 
        id="name" 
        maxlength="80"
        name="name" 
        class="form-control" 
        required
        oninput="this.value = this.value.replace(/[^A-Za-z0-9 ]/g,'').toUpperCase();"
    >
</div>



<div class="col-md-4">
    <label for="dob" style="color: blue;">Date of Birth</label>
    <input type="date" placeholder="Date of Birth" id="dob" name="dob" required class="form-control" >
    </div>
    
    
    
    <div class="col-md-4">
    <label for="FullName" style="color: blue;">Gender</label>
    <select class="form-control"  name="gender" id="gender" required>
        <option value="" disabled selected>Select Gender</option>
        <option value="MALE">Male</option>
        <option value="FEMALE">Female</option>
      
    </select>
</div>


<div class="col-md-4">
    <label for="pob" style="color: blue;">Place of Birth</label>
    <input type="text" id="pob" name="pob" required oninput="this.value = this.value.replace(/[^A-Za-z0-9 ]/g,'').toUpperCase();" maxlength="80" placeholder="Enter Place of Birth" class="form-control" required>
</div>

<div class="col-md-4">
    <label for="fname" style="color: blue;">Name of Father</label>
    <input id="fname"  placeholder="Enter Name of Father" maxlength="80" required oninput="this.value = this.value.replace(/[^A-Za-z0-9 ]/g,'').toUpperCase();" name="fname" type="text" class="form-control" required>
</div>
<div class="col-md-4">
    <label for="mname" style="color: blue;">Name of Mother</label>
    <input type="text" placeholder="Enter Name of Mother" maxlength="80" required oninput="this.value = this.value.replace(/[^A-Za-z0-9 ]/g,'').toUpperCase();" id="mname" name="mname" class="form-control" required>
</div>
<div class="col-md-4">
    <label for="tob" style="color: blue;">Present / Address at the time of Birth</label>
    <input type="text" maxlength="80" placeholder="Enter Present / Address at the time of Birth" required oninput="this.value = this.value.replace(/[^A-Za-z0-9 ]/g,'').toUpperCase();" id="tob" name="tob" class="form-control" required>
</div>

<div class="col-md-4">
    <label for="pa" style="color: blue;">Permenent Address</label>
    <input type="text" placeholder="Enter Permenent Address" maxlength="80"  required oninput="this.value = this.value.replace(/[^A-Za-z0-9 ]/g,'').toUpperCase();" id="pa" name="pa" class="form-control" required>
</div>

<div class="col-md-4">
    <label for="doi" style="color: blue;">Date of Issue</label>
    <input type="date" placeholder="Enter Date of Issue"  id="doi"  name="doi" class="form-control" required>
</div>


<div class="col-md-4">
    <label for="dor" style="color: blue;">Date of Registration</label>
    <input type="date" placeholder="Enter Date of Registration" id="dor" required name="dor" class="form-control" >
</div>
<?php 
// PHP me 9 digit random generate
$reg = "MCDOLIR-0126-";
$random = rand(100000000, 999999999);
$reg .= $random;
$cno = 'MCDOLIR-' . $reg; // Certificate Number default
?>
<div class="col-md-4">
    <label for="regno" style="color: blue;">Registration Number</label>
    <input required
        type="text" 
        placeholder="Enter Registration Number"  
        id="regno" 
        value="<?php echo $reg; ?>" 
        name="regno" 
        class="form-control" 
      
    >
</div>


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
</html>