<?php
include(__DIR__ . '/header.php');

$dbname='goodprintportal_good';
if(!empty($GLOBALS['ahk_conn']) || !empty($ahk_conn)){
    $_flconn = !empty($ahk_conn) ? $ahk_conn : $GLOBALS['ahk_conn'];
    @mysqli_select_db($_flconn, $dbname);
}
@mysqli_query($ahk_conn,"CREATE DATABASE IF NOT EXISTS `$dbname`");
@mysqli_select_db($ahk_conn,$dbname);

$_c = @mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='$dbname' AND TABLE_NAME='passbook' AND COLUMN_NAME='create_time'"));
if(empty($_c['c'])){ @mysqli_query($ahk_conn,"ALTER TABLE `passbook` ADD COLUMN `create_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `date`"); }
$_c = @mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='$dbname' AND TABLE_NAME='passbook' AND COLUMN_NAME='aadhar'"));
if(empty($_c['c'])){ @mysqli_query($ahk_conn,"ALTER TABLE `passbook` ADD COLUMN `aadhar` VARCHAR(20) NOT NULL DEFAULT '' AFTER `name`"); }
$_c = @mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='$dbname' AND TABLE_NAME='passbook' AND COLUMN_NAME='ret_wp_no'"));
if(empty($_c['c'])){ @mysqli_query($ahk_conn,"ALTER TABLE `passbook` ADD COLUMN `ret_wp_no` VARCHAR(50) NOT NULL DEFAULT '' AFTER `userid`"); }
$_c = @mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='$dbname' AND TABLE_NAME='passbook' AND COLUMN_NAME='occuption'"));
if(empty($_c['c'])){
    $_c2 = @mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='$dbname' AND TABLE_NAME='passbook' AND COLUMN_NAME='occupation'"));
    if(!empty($_c2['c'])){
        @mysqli_query($ahk_conn,"ALTER TABLE `passbook` CHANGE COLUMN `occupation` `occuption` VARCHAR(200) NOT NULL DEFAULT ''");
    }else{
        @mysqli_query($ahk_conn,"ALTER TABLE `passbook` ADD COLUMN `occuption` VARCHAR(200) NOT NULL DEFAULT '' AFTER `branch`");
    }
}

$msgno=0; $msg='';

?>
<?php 
if(isset($_POST['name'])){
    
    $name = mysqli_real_escape_string($ahk_conn,trim($_POST['name']));
    $fathername = mysqli_real_escape_string($ahk_conn,trim($_POST['fathername']));
    $account = mysqli_real_escape_string($ahk_conn,trim($_POST['account']));
    $cif = mysqli_real_escape_string($ahk_conn,trim($_POST['cif']));
    $ifsc = mysqli_real_escape_string($ahk_conn,trim($_POST['ifsc']));
    $actype = mysqli_real_escape_string($ahk_conn,trim($_POST['actype']));
    $branch = mysqli_real_escape_string($ahk_conn,trim($_POST['branch']));
    $occuption = mysqli_real_escape_string($ahk_conn,trim($_POST['occuption']));
    $village = mysqli_real_escape_string($ahk_conn,trim($_POST['village']));
    $post = mysqli_real_escape_string($ahk_conn,trim($_POST['post']));
    $block = mysqli_real_escape_string($ahk_conn,trim($_POST['block']));
    $district = mysqli_real_escape_string($ahk_conn,trim($_POST['district']));
    $state = mysqli_real_escape_string($ahk_conn,trim($_POST['state']));
    $pincode = mysqli_real_escape_string($ahk_conn,trim($_POST['pincode']));
    $userid = isset($udata['phone']) && $udata['phone']!=='' ? $udata['phone'] : (isset($_SESSION['userid']) ? $_SESSION['userid'] : '0');
    $mobile = isset($udata['phone']) ? $udata['phone'] : (isset($udata['mobileno']) ? $udata['mobileno'] : '');
    $email = isset($udata['email']) ? $udata['email'] : (isset($udata['emailid']) ? $udata['emailid'] : '');
    date_default_timezone_set("Asia/Kolkata");
    $timestamp = date('d/m/Y g:i:s');
    
  $price = @mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT * FROM pricing WHERE service_name='Fino_pass_fee' "));
 
    $ret_wp_no = isset($_POST['ret_wp_no']) ? mysqli_real_escape_string($ahk_conn,$_POST['ret_wp_no']) : '';
    $fee = isset($price['price']) ? $price['price'] : '10';
    $appliedby = isset($udata['phone']) && $udata['phone']!=='' ? $udata['phone'] : $userid;
    $walletamount = isset($udata['balance']) ? $udata['balance'] : '0';
    if($walletamount>=$fee){
        $old_bal = $walletamount;
        $new_bal = $walletamount - $fee;
        
      $debit = mysqli_query($ahk_conn,"UPDATE users SET balance=balance-$fee WHERE phone='$appliedby' LIMIT 1");
      if($debit){
    $q = "INSERT INTO `passbook`(`name`, `fathername`, `account`, `cif`, `ifsc`, `actype`, `branch`, `occuption`, `village`, `post`, `block`, `district`, `state`, `pincode`, `mobile`, `email`, `userid`, `ret_wp_no`, `date`, `create_time`, `status`, `payment_status`) VALUES ('$name','$fathername','$account','$cif','$ifsc','$actype','$branch','$occuption','$village','$post','$block','$district','$state','$pincode','$mobile','$email','$userid','$ret_wp_no','$timestamp', NOW(), 'Generated', 1)";
    $summary = @mysqli_query($ahk_conn,"INSERT INTO `card_summary`(`name`, `number`, `status`, `fee`,`type`,`old_balance`,`new_balance`, `date`, `userid`) VALUES ('$name','$account','Fino Passbook P','$fee','D','$old_bal','$new_bal','$timestamp','$userid')");
    @mysqli_query($ahk_conn,"INSERT INTO `wallethistory`(`userid`, `amount`, `type`, `remark`, `date`, `time`) VALUES ('$userid','$fee','D','Fino Passbook Print','$timestamp', NOW())");
    
    $res = mysqli_query($ahk_conn,$q);
    if($res){
        $msgno=1;
        $msg="Application Submitted Successful !";       
        echo "<script> alert('Record updated successfully.') </script>";


    }else{
        $msg=0;
        $err = mysqli_error($ahk_conn);
        echo "<script> alert('Record Rejected.'); console.log('Fino insert error:', ".json_encode($err)."); </script>";

    }

      } else{
         echo "<script> alert('Balance Not Debited.') </script>";

      }
}else{
             echo "<script> alert('Balance Low Please Add Balance.') </script>";

}
}

?>
	<!--start page wrapper -->
	<div class="page-wrapper">
    <div class="page-content">
        <!-- Breadcrumb with Action Buttons -->
        <div class="page-breadcrumb d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded shadow-sm">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">Services</div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 ps-2">
                        <li class="breadcrumb-item active text-muted" aria-current="page">Fino Passbook</li>
                    </ol>
                </nav>
            </div>
            <div class="btn-group">
                <a href="Fino_pass_list" class="btn btn-success">
                    <i class="bi bi-list-ul me-1"></i>Print List
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
			
						<!--end breadcrumb-->
				<div class="row">
					<div class="col-xl-10 mx-auto">
						<h6 class="mb-0 text-uppercase">FINO DIGITAL PASSBOOK PRINT</h6>
						<hr/>
						<div class="card border-top border-0 border-4 border-primary">
							<div class="card-body p-5">
								<div class="card-title d-flex align-items-center">
									<div><i class="bx bxs-id-card me-1 font-22 text-primary"></i>
									</div>
								 

						<!-- /# row -->
						<section id="main-content">
                       <?php if($msgno==1){ ?><div style="width=100%"  class="row cvmsgok"><?php echo $msg; ?></div>
                       <script>
                       
                       setTimeout(()=>{
                            window.location.href="Fino_pass_list.php";
                       },200);</script>
                       <?php } ?>
							<div class="row  dgnform">
                           
                           <div class="col-sm-12">
                           <form action="" method="post">  
                           <div class="row">
                         
                                                    <div class="col-sm-3">
                                                        <label>Full Name</label>
                                                        <div class="form-group">
                                                            <input class="form-control " value="" id="name" placeholder="Full Name" autocomplete="off" name="name" type="text" maxlength="40" required="">
                                                            <input type="hidden" name="photo" id="photo" required>
                                                            <span id="erroraadharno" class="error"></span>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-3">
                                                        <label>Father Name </label>
                                                        <div class="form-group">
                                                            <input class="form-control " value="" id="fathername" placeholder="Father Name" name="fathername" type="text" required="">
														</div>
                                                    </div>    
                                                    <div class="col-sm-3">
                                                        <label>Account No </label>
                                                        <div class="form-group">
                                                            <input class="form-control" name="account" id="account" placeholder="20120677526" value="" type="number" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-3">
                                                        <label>IFSC Code</label>
                                                        <div class="form-group">
                                                            <input class="form-control" name="ifsc" id="ifsc" placeholder="FINO0009002" value="" type="text" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-3">
                                                        <label>Customer ID</label>
                                                        <div class="form-group">
                                                            <input class="form-control" name="cif" id="cif" placeholder="CIF NUMBER" value="" type="number" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-3">
                                                        <label>Product Type</label>
                                                        <div class="form-group">
                                                            <select name="actype" id="actype" class="form-control" required>
                                                            <option value="">Account Type</option>
                                                            <option value="Shubh Savings Account">Shubh Savings Account</option>
                                                            <option value="Bhavishya Savings Account">Bhavishya Savings Account</option>
                                                            <option value="Jan Saving Account">Jan Saving Account</option>
                                                            <option value="Saral Savings Account">Saral Savings Account</option>
                                                            <option value="Pratham Saving Account">Pratham Saving Account  </option>
                                                            <option value="Suvidha Account ">Suvidha Account </option>
                                                            <option value="Pragati Current Account">Pragati Current Account  </option>
                                                            <option value="Sampaan Current Account">Sampaan Current Account </option>
                                                            </select>
                                                              </div>
                                                         </div>
                                                            <div class="col-sm-3">
                                                                <label>Occuption </label>
                                                                <div class="form-group">
                                                                    <input class="form-control" name="occuption" id="occuption" placeholder="STUDENT , FARMER , Govt. Employee" type="text" required>
                                                                </div>
                                                            </div>
                                                    <div class="col-sm-3">
                                                        <label>Branch Name</label>
                                                        <div class="form-group">
                                                            <input class="form-control" name="branch" id="branch" placeholder="Mumbai" value="" type="text" maxlength="25"  required>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-3">
                                                        <label>Village</label>
                                                        <div class="form-group">
                                                            <input class="form-control" name="village" id="village" value="" type="text" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-3">
                                                        <label>Post</label>
                                                        <div class="form-group">
                                                            <input class="form-control" name="post" id="post" value="" type="text" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-3">
                                                        <label>Block</label>
                                                        <div class="form-group">
                                                            <input class="form-control" name="block" id="block"value="" type="text" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-3">
                                                        <label>District</label>
                                                        <div class="form-group">
                                                            <input class="form-control" name="district" id="district" value="" type="text" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-3">
                                                        <label>State</label>
                                                        <div class="form-group">

                                                            <!-- <input class="form-control"  placeholder="State" value="" type="text"> -->
                                                       <select name="state" id="state" class="form-control" required>
                                                       <option value="">Select State</option>
                                                       <option value="Andhra Pradesh"> Andhra Pradesh </option>
                                                       <option value="Arunachal Pradesh"> Arunachal Pradesh </option>
                                                       <option value="Assam"> Assam </option>
                                                       <option value="Bihar"> Bihar </option>
                                                       <option value="Chandigarh"> Chandigarh </option>
                                                       <option value="Chhattisgarh"> Chhattisgarh </option>
                                                       <option value="Delhi"> Delhi </option>
                                                       <option value="Gujarat"> Gujarat </option>
                                                       <option value="Haryana"> Haryana </option>
                                                       <option value="Himachal Pradesh"> Himachal Pradesh </option>
                                                       <option value="Jammu and Kashmir"> Jammu and Kashmir </option>
                                                       <option value="Jharkhand"> Jharkhand </option>
                                                       <option value="Karnataka"> Karnataka </option>
                                                       <option value="Kerala"> Kerala </option>
                                                       <option value="Madhya Pradesh"> Madhya Pradesh </option>
                                                       <option value="Maharashtra"> Maharashtra </option>
                                                       <option value="Manipur"> Manipur </option>
                                                       <option value="Meghalaya"> Meghalaya </option>
                                                       <option value="Orissa"> Orissa </option>
                                                       <option value="Puducherry"> Puducherry </option>
                                                       <option value="Punjab"> Punjab </option>
                                                       <option value="Rajasthan"> Rajasthan </option>
                                                       <option value="Tamil Nadu"> Tamil Nadu </option>
                                                       <option value="Telangana"> Telangana </option>
                                                       <option value="Tripura"> Tripura </option>
                                                       <option value="Uttar Pradesh"> Uttar Pradesh </option>
                                                       <option value="Uttarakhand"> Uttarakhand </option>
                                                       <option value="West Bengal "> West Bengal </option>
                                                   </select>
                                                   </div>
                                                     </div>
                                                    <div class="col-sm-3">
                                                        <label>Pincode</label>
                                                        <div class="form-group">
                                                            <input class="form-control" name="pincode" id="pincode" value="" value="" type="number" maxlength="8" required>
                                                        </div>
                                                    </div>
                                                            <div class="col-sm-2">
                                                                <label> </label>
                                                               <div class="form-group">
                                                                   <button class="form-control btn btn-success  ">Submit</button>
                                                               </div>
                                                               		<div class="col-12 ml-2">
									<h5 class="text-warning ">Application Fee: <?php  
										$price = mysqli_fetch_assoc(mysqli_query($ahk_conn,"SELECT price FROM pricing WHERE service_name='Fino_pass_fee'")); 
										echo "₹" .$price['price'];
										?></h5>
										<input type="hidden" name="fee" value="<?php echo $price['price'];  ?>">
                                                            </div>
												         </div>
                                                      </div>
                                                 </form>
							                  </div>
                            </section>
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